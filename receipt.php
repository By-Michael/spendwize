<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
sw_send_cors_headers();

// ── OCR.space config ──────────────────────────────────────────────────────
define('SW_OCRSPACE_URL', 'https://api.ocr.space/parse/image');
// OCR.space's free-tier limit applies to the request payload, and base64
// encoding adds ~33% overhead — so target the raw image well under 1MB
// to keep the encoded base64Image field under that cap.
define('SW_OCRSPACE_MAX_BYTES', 700 * 1024);

// ── Groq config (reuse same model as ai.php) ────────────────────────────────
define('SW_RECEIPT_GROQ_MODEL', 'llama-3.1-8b-instant');
define('SW_RECEIPT_GROQ_URL', 'https://api.groq.com/openai/v1/chat/completions');

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Decode a base64 (optionally data-URL) image, resize and re-compress it as
 * JPEG with imagecopyresampled + imagejpeg so the result fits under
 * SW_OCRSPACE_MAX_BYTES, then return it as a base64 data URL ready for
 * OCR.space.
 *
 * Always runs the image through GD (re-encoding to JPEG) so formats GD can
 * read but OCR.space may not accept directly (e.g. WEBP) are normalized,
 * and so unsupported formats (e.g. AVIF, HEIC) fail with a clear message
 * instead of being forwarded as garbage.
 */
function sw_receipt_prepare_image(string $base64Image): string
{
    // Strip any data URL prefix to get raw base64, and detect the declared
    // MIME type from the data URL if present (used only for error messages).
    $raw = $base64Image;
    $declaredMime = null;
    if (str_starts_with($raw, 'data:')) {
        $comma = strpos($raw, ',');
        if ($comma !== false) {
            $header = substr($raw, 0, $comma);
            if (preg_match('#^data:([^;]+)#', $header, $m)) {
                $declaredMime = $m[1];
            }
            $raw = substr($raw, $comma + 1);
        } else {
            $raw = '';
        }
    }

    $bytes = base64_decode($raw, true);
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('Could not decode receipt image.');
    }

    if (!function_exists('imagecreatefromstring') || !function_exists('imagecopyresampled') || !function_exists('imagejpeg')) {
        // No GD available — only safe to pass through if it's already small
        // enough and a format OCR.space accepts directly.
        if (strlen($bytes) <= SW_OCRSPACE_MAX_BYTES && in_array($declaredMime, ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/tiff'], true)) {
            return 'data:' . $declaredMime . ';base64,' . base64_encode($bytes);
        }
        throw new RuntimeException('This image format is not supported on the server. Please try a JPG or PNG photo.');
    }

    $src = @imagecreatefromstring($bytes);
    if ($src === false) {
        $formatHint = $declaredMime !== null ? ' (' . $declaredMime . ')' : '';
        throw new RuntimeException('Unsupported image format' . $formatHint . '. Please use a JPG or PNG photo instead.');
    }

    // Correct orientation based on EXIF data (common for phone camera photos).
    if (function_exists('exif_read_data') && function_exists('imagerotate')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($bytes));
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation > 1) {
            $angle = match ($orientation) {
                3 => 180,
                6 => 270,
                8 => 90,
                default => 0,
            };
            if ($angle !== 0) {
                $rotated = imagerotate($src, $angle, 0);
                if ($rotated !== false) {
                    imagedestroy($src);
                    $src = $rotated;
                }
            }
        }
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    // Progressively scale down and/or lower JPEG quality until under the
    // OCR.space size limit, capping at a reasonable max dimension first.
    $maxDim   = 2000;
    $quality  = 80;
    $output   = '';

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $scale = min(1, $maxDim / max($srcW, $srcH));
        $dstW  = max(1, (int) round($srcW * $scale));
        $dstH  = max(1, (int) round($srcH * $scale));

        $dst = imagecreatetruecolor($dstW, $dstH);
        // Flatten transparency onto white so JPEG output looks correct.
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        imagejpeg($dst, null, $quality);
        $output = (string) ob_get_clean();
        imagedestroy($dst);

        if (strlen($output) <= SW_OCRSPACE_MAX_BYTES) {
            break;
        }

        // Shrink further and/or drop quality for the next attempt.
        if ($quality > 50) {
            $quality -= 10;
        } else {
            $maxDim = (int) ($maxDim * 0.75);
        }
    }

    imagedestroy($src);

    if ($output === '') {
        throw new RuntimeException('Could not process receipt image.');
    }

    if (strlen($output) > SW_OCRSPACE_MAX_BYTES) {
        throw new RuntimeException('Receipt image is too large to process even after compression. Try a clearer, smaller photo.');
    }

    return 'data:image/jpeg;base64,' . base64_encode($output);
}

/**
 * Send the receipt image (base64 data URL or raw base64) to OCR.space and
 * return the extracted plain text.
 */
function sw_receipt_ocr(string $base64Image): string
{
    if (SW_OCRSPACE_API_KEY === '') {
        throw new RuntimeException('OCR API key is not configured.');
    }

    // Resize/compress so the payload fits under OCR.space's free-tier limit.
    $base64Image = sw_receipt_prepare_image($base64Image);

    $postFields = [
        'apikey'         => SW_OCRSPACE_API_KEY,
        'base64Image'    => $base64Image,
        'OCREngine'      => '2',
        'scale'          => 'true',
        'isTable'        => 'true',
        'detectOrientation' => 'true',
    ];

    $caBundle = sw_ca_bundle_path();

    if (function_exists('curl_init')) {
        $ch = curl_init(SW_OCRSPACE_URL);
        if ($ch === false) {
            throw new RuntimeException('Could not initialise OCR request.');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            // Send as a urlencoded body rather than multipart — OCR.space's
            // base64Image field can be megabytes long and some PHP/cURL
            // builds mishandle that as a multipart part.
            CURLOPT_POSTFIELDS     => http_build_query($postFields, '', '&'),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($caBundle !== null) {
            $opts[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($ch, $opts);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            throw new RuntimeException('OCR request failed: ' . ($err ?: 'empty response (HTTP ' . $status . ')'));
        }
        $raw = (string) $raw;

        if ($status >= 400) {
            $errData = json_decode($raw, true);
            $msg = null;
            if (is_array($errData)) {
                $msg = $errData['ErrorMessage'] ?? $errData['ErrorDetails'] ?? $errData['error'] ?? null;
                if (is_array($msg)) {
                    $msg = implode(' ', array_map('strval', $msg));
                }
            }
            if ($msg === null || $msg === '') {
                $msg = substr($raw, 0, 300);
            }
            throw new RuntimeException('OCR request failed (HTTP ' . $status . '): ' . $msg);
        }
    } else {
        $boundary = '----SpendWiseReceipt' . bin2hex(random_bytes(8));
        $body = '';
        foreach ($postFields as $key => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
            $body .= $value . "\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: multipart/form-data; boundary={$boundary}\r\n",
                'content' => $body,
                'timeout' => 60,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'cafile'           => $caBundle,
            ],
        ]);
        $raw = @file_get_contents(SW_OCRSPACE_URL, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('OCR request failed.');
        }

        $statusLine = (string) ($http_response_header[0] ?? '');
        if (preg_match('/\s(\d{3})\s/', $statusLine, $m) && (int) $m[1] >= 400) {
            $errData = json_decode((string) $raw, true);
            $msg = null;
            if (is_array($errData)) {
                $msg = $errData['ErrorMessage'] ?? $errData['ErrorDetails'] ?? $errData['error'] ?? null;
                if (is_array($msg)) {
                    $msg = implode(' ', array_map('strval', $msg));
                }
            }
            if ($msg === null || $msg === '') {
                $msg = substr((string) $raw, 0, 300);
            }
            throw new RuntimeException('OCR request failed (HTTP ' . $m[1] . '): ' . $msg);
        }
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid response from OCR service.');
    }

    if (!empty($data['IsErroredOnProcessing'])) {
        $msg = $data['ErrorMessage'] ?? $data['ErrorDetails'] ?? 'OCR processing failed.';
        if (is_array($msg)) {
            $msg = implode(' ', array_map('strval', $msg));
        }
        throw new RuntimeException('OCR error: ' . (string) $msg);
    }

    $results = $data['ParsedResults'] ?? [];
    if (!is_array($results) || count($results) === 0) {
        throw new RuntimeException('No text could be extracted from this receipt.');
    }

    $text = (string) ($results[0]['ParsedText'] ?? '');
    $text = trim($text);
    if ($text === '') {
        throw new RuntimeException('No text could be extracted from this receipt.');
    }

    return $text;
}

/**
 * Use the Groq (Llama) model to turn raw OCR text into structured expense
 * fields matching SpendWise's expense form.
 *
 * @return array{amount: ?float, date: ?string, category: ?string, note: ?string, merchant: ?string}
 */
function sw_receipt_extract_fields(string $ocrText, array $categories): array
{
    if (SW_GROQ_API_KEY === '') {
        throw new RuntimeException('AI API key is not configured.');
    }

    $today = (new DateTimeImmutable('now', new DateTimeZone('Africa/Addis_Ababa')))->format('Y-m-d');
    $catList = implode(', ', $categories);

    $systemPrompt = "You extract structured data from receipt OCR text for a budgeting app called SpendWise.\n"
        . "Respond with ONLY a single JSON object, no markdown, no code fences, no explanation.\n"
        . "JSON shape:\n"
        . "{\n"
        . "  \"amount\": number or null,   // the FINAL TOTAL amount paid (not subtotal, not a line item), as a plain number without currency symbols or commas\n"
        . "  \"date\": string or null,     // the transaction date in YYYY-MM-DD format. If year is missing, assume the most recent past occurrence relative to today ({$today}). If no date is found, use null.\n"
        . "  \"category\": string or null, // pick the single best match from this exact list: [{$catList}]. If nothing fits well, use \"Other\".\n"
        . "  \"merchant\": string or null, // the store / merchant / vendor name\n"
        . "  \"note\": string or null      // a short (max 6 words) human-friendly note combining merchant name and what was purchased, suitable as an expense note\n"
        . "}\n"
        . "Rules:\n"
        . "- amount must be the grand total / amount due, never a subtotal, tax line, or change given.\n"
        . "- If multiple totals exist, prefer the one labeled TOTAL, GRAND TOTAL, AMOUNT DUE, or BALANCE DUE.\n"
        . "- If you cannot confidently determine a field, set it to null.\n"
        . "- Output raw JSON only.";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => "Receipt OCR text:\n\n" . $ocrText],
    ];

    $body = json_encode([
        'model'           => SW_RECEIPT_GROQ_MODEL,
        'messages'        => $messages,
        'temperature'     => 0.1,
        'max_tokens'      => 300,
        'response_format' => ['type' => 'json_object'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($body === false) {
        throw new RuntimeException('Could not encode AI request.');
    }

    $caBundle = sw_ca_bundle_path();
    $headers  = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . SW_GROQ_API_KEY,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init(SW_RECEIPT_GROQ_URL);
        if ($ch === false) {
            throw new RuntimeException('Could not initialise AI request.');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($caBundle !== null) {
            $opts[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($ch, $opts);

        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            throw new RuntimeException('AI request failed: ' . ($err ?: 'empty response'));
        }
        if ($status >= 400) {
            $errData = json_decode((string) $raw, true);
            $msg = (string) ($errData['error']['message'] ?? "HTTP {$status}");
            throw new RuntimeException('AI error: ' . $msg);
        }
        $raw = (string) $raw;
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'cafile'           => $caBundle,
            ],
        ]);
        $raw = @file_get_contents(SW_RECEIPT_GROQ_URL, false, $ctx);
        if ($raw === false) {
            throw new RuntimeException('AI request failed.');
        }
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid response from AI service.');
    }
    if (isset($data['error']['message'])) {
        throw new RuntimeException('AI error: ' . $data['error']['message']);
    }

    $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
    if ($content === '') {
        throw new RuntimeException('AI returned an empty reply.');
    }

    // Strip accidental markdown code fences just in case.
    $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content) ?? $content;
    $content = trim($content);

    $fields = json_decode($content, true);
    if (!is_array($fields)) {
        throw new RuntimeException('Could not parse AI response.');
    }

    // Normalize / validate
    $amount = $fields['amount'] ?? null;
    if (is_string($amount)) {
        $cleaned = preg_replace('/[^0-9.\-]/', '', $amount);
        $amount = $cleaned === '' ? null : (float) $cleaned;
    } elseif (is_int($amount) || is_float($amount)) {
        $amount = (float) $amount;
    } else {
        $amount = null;
    }
    if ($amount !== null && $amount <= 0) {
        $amount = null;
    }

    $date = $fields['date'] ?? null;
    if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
        $date = null;
    }

    $category = $fields['category'] ?? null;
    if (!is_string($category) || !in_array($category, $categories, true)) {
        // Fall back to "Other" so the category field is always filled in,
        // as long as "Other" exists in this user's category list.
        $category = in_array('Other', $categories, true) ? 'Other' : null;
    }

    $merchant = $fields['merchant'] ?? null;
    $merchant = is_string($merchant) ? trim($merchant) : null;
    if ($merchant === '') {
        $merchant = null;
    }

    $note = $fields['note'] ?? null;
    $note = is_string($note) ? trim($note) : null;
    if ($note === '') {
        $note = $merchant;
    }

    return [
        'amount'   => $amount,
        'date'     => $date,
        'category' => $category,
        'merchant' => $merchant,
        'note'     => $note,
    ];
}

// ── Request handler ──────────────────────────────────────────────────────────

try {
    $action = trim((string) ($_GET['action'] ?? ''));
    sw_require_session_user(); // 401 if not logged in

    if ($action !== 'scan') {
        sw_error('Unknown action.', 404);
    }

    $input = sw_read_input();
    $image = (string) ($input['image'] ?? '');
    $image = trim($image);

    if ($image === '') {
        sw_error('No receipt image provided.');
    }

    // Rough size guard for the base64 payload (~5 MB original image).
    if (strlen($image) > 8 * 1024 * 1024) {
        sw_error('Receipt image is too large.');
    }

    $categories = $input['categories'] ?? null;
    if (!is_array($categories) || count($categories) === 0) {
        $categories = ['Food', 'Transport', 'Entertainment', 'Health', 'Utilities', 'Shopping', 'Rent', 'Education', 'Personal Care', 'Other'];
    }
    $categories = array_values(array_filter(array_map('strval', $categories), fn($c) => $c !== ''));

    $ocrText = sw_receipt_ocr($image);
    $fields  = sw_receipt_extract_fields($ocrText, $categories);

    sw_json_response(200, [
        'ok' => true,
        'data' => [
            'fields'  => $fields,
            'rawText' => $ocrText,
        ],
    ]);

} catch (Throwable $e) {
    sw_json_response(500, ['ok' => false, 'error' => $e->getMessage()]);
}
