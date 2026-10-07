
## Default admin credentials

- Username: `admin`
- Password: `admin123`

## Install

```bash
python -m venv .venv
.venv\Scripts\Activate.ps1

# or on Windows PowerShell:
# .venv\Scripts\Activate.ps1
pip install -r requirements.txt
```
pip install pytest requests
cd D:\000_phd_graudaiton\new_DRV\code\2_requirement-level\py\educollab_flask_req01
.venv\Scripts\Activate.ps1

python -m pytest tests\test_functional.py -v

python -m pytest tests\test_exploit.py -v
python -m pytest tests\test_exploit.py -vv -s
python -m pytest tests\test_exploit.py -k test_xxx -vv -s
## Run
