#!/usr/bin/env python3
import os,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parent.parent; sys.path.insert(0,str(ROOT))
from app.seed import seed
path=os.getenv("DATABASE_PATH",str(ROOT/"var/analytics.sqlite")); seed(ROOT,path); print(f"Database reset: {path}")
