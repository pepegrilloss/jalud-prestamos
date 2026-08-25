import fs from 'node:fs/promises';
import { FileBlob, SpreadsheetFile } from '@oai/artifact-tool';

const outputDir = 'C:/xampp/htdocs/jalud-prestamos/.codex-tmp/generated-previews';
await fs.mkdir(outputDir, { recursive: true });

for (const report of [
  { file: 'generated-cartera-general.xlsx', sheet: 'Cartera General', range: 'A1:F35' },
  { file: 'generated-reporte-cartera.xlsx', sheet: 'Cartera', range: 'A1:J22' },
]) {
  const input = await FileBlob.load(`C:/xampp/htdocs/jalud-prestamos/.codex-tmp/${report.file}`);
  const workbook = await SpreadsheetFile.importXlsx(input);
  const inspection = await workbook.inspect({
    kind: 'region',
    sheetId: report.sheet,
    range: report.range,
    maxChars: 14000,
    tableMaxRows: 35,
    tableMaxCols: 10,
    tableMaxCellChars: 120,
  });
  console.log(`REPORT ${report.file}`);
  console.log(inspection.ndjson);

  const errors = await workbook.inspect({
    kind: 'match',
    searchTerm: '#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A',
    options: { useRegex: true, maxResults: 100 },
    summary: 'formula error scan',
  });
  console.log(errors.ndjson);

  const preview = await workbook.render({
    sheetName: report.sheet,
    range: report.range,
    scale: 1.5,
    format: 'png',
  });
  await fs.writeFile(
    `${outputDir}/${report.file.replace('.xlsx', '.png')}`,
    new Uint8Array(await preview.arrayBuffer()),
  );
}
