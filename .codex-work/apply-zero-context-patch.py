import re
from pathlib import Path

root = Path(r"C:\xampp\htdocs\jalud-prestamos")
source = root / ".codex-work" / "original-report-export.php"
patch_path = root / ".codex-work" / "reporte-export-semantic-u0.patch"
output = root / ".codex-work" / "reconstructed-report-export.php"

lines = source.read_text(encoding="utf-8").splitlines()
patch_lines = patch_path.read_text(encoding="utf-8-sig").splitlines()
hunks = []
current = None

for line in patch_lines:
    match = re.match(r"@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@", line)
    if match:
        current = {
            "old_start": int(match.group(1)),
            "old_count": int(match.group(2) or 1),
            "new": [],
        }
        hunks.append(current)
        continue

    if current is None or line.startswith("\\ No newline"):
        continue

    if line.startswith("+") and not line.startswith("+++"):
        current["new"].append(line[1:])

for hunk in reversed(hunks):
    start = hunk["old_start"] if hunk["old_count"] == 0 else hunk["old_start"] - 1
    lines[start:start + hunk["old_count"]] = hunk["new"]

output.write_text("\n".join(lines) + "\n", encoding="utf-8", newline="\n")
print(f"Applied {len(hunks)} semantic hunks to {output}")
