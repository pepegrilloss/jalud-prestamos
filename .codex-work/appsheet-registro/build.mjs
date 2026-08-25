import fs from "node:fs/promises";
import path from "node:path";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = path.resolve("outputs/appsheet-registro");
await fs.mkdir(outputDir, { recursive: true });

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("Registros");
sheet.showGridLines = false;
sheet.freezePanes.freezeRows(1);

sheet.getRange("A1:E1").values = [[
  "DNI",
  "Nombres",
  "Apellidos",
  "Direccion",
  "Ruta",
]];

sheet.getRange("A1:E1").format = {
  fill: "#1565C0",
  font: { bold: true, color: "#FFFFFF", size: 11 },
  horizontalAlignment: "center",
  verticalAlignment: "center",
  borders: { preset: "outside", style: "medium", color: "#0D47A1" },
};
sheet.getRange("A1:E1").format.rowHeight = 28;

sheet.getRange("A:A").format.numberFormat = "@";
sheet.getRange("B:E").format.numberFormat = "@";
sheet.getRange("A1:A2000").format.columnWidth = 15;
sheet.getRange("B1:C2000").format.columnWidth = 24;
sheet.getRange("D1:D2000").format.columnWidth = 38;
sheet.getRange("E1:E2000").format.columnWidth = 24;

const inspection = await workbook.inspect({
  kind: "table",
  range: "Registros!A1:E5",
  include: "values,formulas",
  tableMaxRows: 5,
  tableMaxCols: 5,
});
console.log(inspection.ndjson);

const errors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A",
  options: { useRegex: true, maxResults: 50 },
  summary: "final formula error scan",
});
console.log(errors.ndjson);

const preview = await workbook.render({
  sheetName: "Registros",
  range: "A1:E8",
  scale: 2,
  format: "png",
});
await fs.writeFile(
  path.join(outputDir, "registros-preview.png"),
  new Uint8Array(await preview.arrayBuffer()),
);

const output = await SpreadsheetFile.exportXlsx(workbook);
await output.save(path.join(outputDir, "Base_Datos_Registros.xlsx"));

