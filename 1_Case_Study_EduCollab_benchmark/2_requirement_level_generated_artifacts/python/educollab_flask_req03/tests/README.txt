Usage:
1. Put these test files under your Flask project root, for example in tests/.
2. Ensure the project root contains app.py, controllers/, models/, utils/.
3. Run:
   pytest -q tests/test_r3_functional.py
   pytest -q tests/test_r3_exploit.py

Notes:
- These tests target only R3 (posts/comments/search) behavior.
- R1/R2 APIs are used only as setup helpers.
- Exploit tests intentionally assert the currently vulnerable behavior.




## Default admin credentials

- Username: `admin`
- Password: `admin123`

## Install

```bash
python -m venv .venv
venv\Scripts\Activate.ps1


pip install -r requirements.txt
```
pip install pytest requests
cd D:\000_phd_graudaiton\new_DRV\code\2_requirement-level\py\educollab_flask_req01


python -m pytest tests\test_r3_functional.py -v

python -m pytest tests\test_r3_exploit.py -v

python -m pytest tests\test_r3_exploit.py -v
python -m pytest tests\test_r3_exploit.py -vv -s
python -m pytest tests\test_r3_exploit.py -k test_xxx -vv -s
## Run
```bash
python app.py
```
