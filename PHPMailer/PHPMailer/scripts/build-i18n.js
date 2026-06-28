/**
 * Build i18n/am.js from spendwise_perfect_amharic_translations.xlsx (sheet XML).
 * Run: node scripts/build-i18n.js
 */
const fs = require('fs');
const path = require('path');

const sheetPath = path.join(__dirname, '..', 'xlsx_extract', 'xl', 'worksheets', 'sheet1.xml');
const outPath = path.join(__dirname, '..', 'i18n', 'am.js');

function decodeXmlEntities(s) {
  return s
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#(\d+);/g, (_, n) => String.fromCodePoint(Number(n)));
}

function extractInlineTexts(xml) {
  const rows = [];
  const rowRe = /<row r="(\d+)"[^>]*>([\s\S]*?)<\/row>/g;
  let rowMatch;
  while ((rowMatch = rowRe.exec(xml)) !== null) {
    const r = Number(rowMatch[1]);
    if (r < 2) continue;
    const rowXml = rowMatch[2];
    const cells = [];
    const cellRe = /<c r="([A-D]\d+)"[^>]*>([\s\S]*?)<\/c>/g;
    let cellMatch;
    while ((cellMatch = cellRe.exec(rowXml)) !== null) {
      const col = cellMatch[1][0];
      const body = cellMatch[2];
      const tMatch = body.match(/<t[^>]*>([\s\S]*?)<\/t>/);
      cells.push({ col, text: tMatch ? decodeXmlEntities(tMatch[1]) : '' });
    }
    const english = cells.find((c) => c.col === 'C')?.text ?? '';
    const amharic = cells.find((c) => c.col === 'D')?.text ?? '';
    if (english && amharic) rows.push({ english, amharic });
  }
  return rows;
}

const xml = fs.readFileSync(sheetPath, 'utf8');
const pairs = extractInlineTexts(xml);
const map = {};
for (const { english, amharic } of pairs) {
  map[english] = amharic;
}

const header = `/* Auto-generated from spendwise_perfect_amharic_translations.xlsx — do not edit by hand */\n`;
const body = `window.SPENDWISE_I18N_AM = ${JSON.stringify(map, null, 2)};\n`;
fs.mkdirSync(path.dirname(outPath), { recursive: true });
fs.writeFileSync(outPath, header + body, 'utf8');
console.log(`Wrote ${pairs.length} entries to ${outPath}`);
