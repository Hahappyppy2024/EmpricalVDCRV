#!/usr/bin/env python3
from pathlib import Path
import os, sys
ROOT=Path(__file__).resolve().parent.parent
sys.path.insert(0,str(ROOT))
from app.seed import seed
path=os.getenv("DATABASE_PATH",str(ROOT/"var/team_chat.sqlite"))
seed(ROOT,path)
print(f"Database reset: {path}")
