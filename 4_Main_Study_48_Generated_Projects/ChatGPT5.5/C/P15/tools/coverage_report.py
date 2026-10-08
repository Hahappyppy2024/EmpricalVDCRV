#!/usr/bin/env python3
import json, pathlib, subprocess, sys
root=pathlib.Path(sys.argv[1]); sources=[]
for note in root.glob("*.gcno"):
    subprocess.run(["gcov","--json-format","--branch-probabilities",note.name],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=False)
for gz in root.glob("*.gcov.json.gz"):
    import gzip
    data=json.load(gzip.open(gz,"rt"))
    for f in data.get("files",[]):
        if f.get("file","").startswith("src/") or "/src/" in f.get("file",""):
            sources.append(f)
line_counts={}; branch_counts={}
for f in sources:
    name=f["file"]
    for line in f.get("lines",[]):
        key=(name,line["line_number"])
        line_counts[key]=max(line_counts.get(key,0),line.get("count",0))
        for index,b in enumerate(line.get("branches",[])):
            key=(name,line["line_number"],index)
            branch_counts[key]=max(branch_counts.get(key,0),b.get("count",0))
lines_total=len(line_counts); lines_hit=sum(v>0 for v in line_counts.values())
branches_total=len(branch_counts); branches_hit=sum(v>0 for v in branch_counts.values())
line_pct=100*lines_hit/lines_total if lines_total else 0
branch_pct=100*branches_hit/branches_total if branches_total else 0
print(f"LINE_COVERAGE={lines_hit}/{lines_total} ({line_pct:.2f}%)")
print(f"BRANCH_COVERAGE={branches_hit}/{branches_total} ({branch_pct:.2f}%)")
sys.exit(0 if branch_pct>=85 else 1)
