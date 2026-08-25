import fs from 'node:fs/promises';
import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';

const inputPath = 'C:/Users/julio.vilcherrez/Downloads/MORA - Cartera_General_01-07-2025_23-08-2026.xlsx';
const outputDir = 'C:/xampp/htdocs/jalud-prestamos/.codex-tmp/modificacion-mora-xlsx';

await fs.mkdir(outputDir, { recursive: true });
const input = await FileBlob.load(inputPath);
const workbook = await SpreadsheetFile.importXlsx(input);

const summary = await workbook.inspect({
  kind: 'workbook,sheet,table,drawing',
  maxChars: 8000,
  tableMaxRows: 8,
  tableMaxCols: 10,
  tableMaxCellChars: 100,
});
console.log('SUMMARY');
console.log(summary.ndjson);

const sheets = await workbook.inspect({ kind: 'sheet', include: 'id,name', maxChars: 4000 });
console.log('SHEETS');
console.log(sheets.ndjson);

for (const sheetName of ['Cartera General', 'ejemplo', 'Cartera']) {
  try {
    const region = await workbook.inspect({
      kind: 'region',
      sheetId: sheetName,
      range: sheetName === 'Cartera' ? 'A1:J40' : 'A1:H40',
      maxChars: 12000,
      tableMaxRows: 40,
      tableMaxCols: 10,
      tableMaxCellChars: 120,
    });
    console.log(`REGION ${sheetName}`);
    console.log(region.ndjson);

    const preview = await workbook.render({
      sheetName,
      range: sheetName === 'Cartera' ? 'A1:J40' : 'A1:H40',
      scale: 1.5,
      format: 'png',
    });
    await fs.writeFile(`${outputDir}/${sheetName.replaceAll(' ', '-')}.png`, new Uint8Array(await preview.arrayBuffer()));
  } catch (error) {
    console.log(`SKIP ${sheetName}: ${error.message}`);
  }
}
