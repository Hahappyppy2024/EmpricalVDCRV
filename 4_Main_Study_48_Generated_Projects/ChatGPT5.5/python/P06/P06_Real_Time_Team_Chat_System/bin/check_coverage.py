#!/usr/bin/env python3
import json
import sys
from pathlib import Path

root=Path(__file__).resolve().parent.parent
source=Path(sys.argv[1]) if len(sys.argv)>1 else root/"var/coverage.json"
data=json.loads(source.read_text(encoding="utf-8"))
totals=data["totals"]
branch=round(100*totals["covered_branches"]/totals["num_branches"],2) if totals["num_branches"] else 100.0
line=round(100*totals["covered_lines"]/totals["num_statements"],2) if totals["num_statements"] else 100.0
summary={"line_hit":totals["covered_lines"],"line_total":totals["num_statements"],"line_percent":line,"branch_hit":totals["covered_branches"],"branch_total":totals["num_branches"],"branch_percent":branch,"threshold":85.0,"passed":branch>=85.0}
(root/"var/coverage-summary.json").write_text(json.dumps(summary,indent=2),encoding="utf-8")
print(json.dumps(summary,indent=2))
raise SystemExit(0 if summary["passed"] else 1)
