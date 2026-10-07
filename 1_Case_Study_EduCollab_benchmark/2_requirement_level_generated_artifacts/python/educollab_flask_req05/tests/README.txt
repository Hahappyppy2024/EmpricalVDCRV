R5 only: question bank + quiz + student attempts

Files:
- conftest.py
- test_r5_functional.py
- test_r5_exploit.py

Run:
pytest -q test_r5_functional.py
pytest -q test_r5_exploit.py

Notes:
- These tests treat r1-r4 only as setup dependencies.
- Functional tests focus on question/quiz/attempt behavior introduced in r5.
- Exploit tests target two code-supported weaknesses in r5:
  1) answers remain editable after submission;
  2) an attempt accepts answers for question_id values not actually bound to the target quiz.


R4 tests only target the Requirement-4 functionality:
- assignments
- uploads
- submissions

They intentionally treat R1/R2/R3 as setup only.

Files:
- conftest.py
- test_r4_functional.py
- test_r4_exploit.py

Typical run:
    pytest -q test_r4_functional.py
    pytest -q test_r4_exploit.py


Usage:
1. Put these test files under your Flask project root, for example in tests/.
2. Ensure the project root contains app.py, controllers/, models/, utils/.
3. Run:

python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install pytest
pip install requests


   pytest -q tests/test_r5_functional.py
   pytest -q tests/test_r5_exploit.py

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


python -m pytest tests\test_r5_functional.py -v

python -m pytest tests\test_r5_exploit.py -v

python -m pytest tests\test_r5_exploit.py -v
python -m pytest tests\test_r5_exploit.py -vv -s
python -m pytest tests\test_r5_exploit.py -k test_xxx -vv -s
## Run
```bash
python app.py
```
