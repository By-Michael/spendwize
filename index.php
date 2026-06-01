<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$bootstrap = sw_get_bootstrap_payload();
$bootstrapJson = json_encode(
    $bootstrap,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$scriptPath = __DIR__ . '/script.js';
$stylePath = __DIR__ . '/style.css';
$assetVersion = (string) max(
    file_exists($scriptPath) ? (int) filemtime($scriptPath) : 0,
    file_exists($stylePath) ? (int) filemtime($stylePath) : 0,
    time()
);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>SpendWise</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/lucide@0.383.0/dist/umd/lucide.min.js"></script>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<link rel="stylesheet" href="style.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<div id="app-root"></div>
<div id="expense-modal-root"></div>
<div id="modal-root"></div>
<script>
window.__SPENDWISE_BOOT__ = <?php echo $bootstrapJson ?: '{"user":null,"state":null}'; ?>;
window.__SPENDWISE_BUILD__ = <?php echo json_encode($assetVersion); ?>;
</script>
<script src="script.js?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>" charset="UTF-8"></script>
</body>
</html>
