
# PHP R3 tests (posts / comments / search only)

This package intentionally focuses on **R3 functionality only**:
- course posts
- post comments
- course search for posts/comments

R1 (auth/course CRUD) and R2 (memberships) are used only as setup dependencies.

## Files

- `tests/functional_tests/test_api_functional_r3_posts_comments.py`
- `tests/security_tests/exploit_tests/test_api_exploit_r3_posts_comments.py`
- `tests/conftest.py`
- `tests/helpers.py`

## Expected placement

Unzip this package into the PHP project root so that the project root contains:
- `public/`
- `app/`
- `config/`
- `tests/`

## Run

```bash
## Suggested run commands
python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
pip install pytest
pip install requests
```bash

pip install pytest requests
pytest -q tests/test_api_functional_r3_posts_comments.py
pytest -q tests/test_api_exploit_r3_posts_comments.py
```

## Notes

The uploaded archive references R3 routes in `public/index.php` for:
- `/api/courses/{course_id}/posts`
- `/api/courses/{course_id}/posts/{post_id}`
- `/api/courses/{course_id}/posts/{post_id}/comments`
- `/api/courses/{course_id}/posts/{post_id}/comments/{comment_id}`
- `/api/courses/{course_id}/search/posts`
- `/api/courses/{course_id}/search/comments`

These tests assume the R3 controllers/repositories for those routes exist in the runnable project.
