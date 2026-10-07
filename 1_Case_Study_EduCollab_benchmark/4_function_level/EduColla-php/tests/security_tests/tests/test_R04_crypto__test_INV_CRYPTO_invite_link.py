import os
import re
import sqlite3
from pathlib import Path

import pytest
import requests

from common import (
    BASE_URL,
    api_login_admin,
    api_register_and_login_student,
    api_create_course,
)


TOKEN_RE = re.compile(r"token=([A-Za-z0-9_\-]+)")


def _extract_token(invite_link: str) -> str:
    m = TOKEN_RE.search(invite_link or "")
    assert m, f"No token in invite_link: {invite_link!r}"
    return m.group(1)


def _sha256_hex(s: str) -> str:
    import hashlib
    return hashlib.sha256(s.encode("utf-8")).hexdigest()


def _guess_db_path() -> Path | None:
    """
    Best-effort DB path discovery.
    Prefer env DB_PATH. Otherwise probe common locations.
    Return None if not found (then tests will skip DB-dependent assertions).
    """
    env = os.getenv("DB_PATH")
    if env:
        p = Path(env)
        return p if p.exists() else None

    # probe common relative paths from repo root
    here = Path(__file__).resolve()
    root = here.parents[3]  # repo root (tests/security_tests/tests/ -> root)
    candidates = [
        root / "data" / "app.db",
        root / "data" / "educollab.db",
        root / "data" / "database.sqlite",
        root / "app.db",
        root / "database.sqlite",
    ]
    for p in candidates:
        if p.exists():
            return p
    return None


def _open_db_or_skip() -> sqlite3.Connection:
    dbp = _guess_db_path()
    if not dbp:
        pytest.skip("DB file not found (set DB_PATH to enable DB-backed assertions).")
    return sqlite3.connect(str(dbp))


def _invite_create(admin: requests.Session, cid: int, ttl_minutes: int = 60) -> tuple[str, str]:
    r = admin.post(
        f"{BASE_URL}/api/courses/{cid}/invites",
        json={"role_in_course": "student", "ttl_minutes": ttl_minutes},
        timeout=10,
    )
    assert r.status_code == 200, r.text
    j = r.json()
    invite_link = j.get("invite_link") or ""
    token = _extract_token(invite_link)
    return token, invite_link


def test_invite_tokens_are_unique_and_urlsafe():
    admin = api_login_admin()
    course = api_create_course(admin, title="InviteSec", description="")
    cid = int(course["course_id"])

    tokens = set()
    for _ in range(20):
        token, _link = _invite_create(admin, cid, ttl_minutes=60)
        assert len(token) >= 20
        assert re.fullmatch(r"[A-Za-z0-9_\-]+", token), f"Token not urlsafe: {token!r}"
        tokens.add(token)

    assert len(tokens) == 20, "Tokens are not unique (collision detected)."


def test_invite_token_single_use_and_hash_only_storage():
    admin = api_login_admin()
    course = api_create_course(admin, title="InviteOnce", description="")
    cid = int(course["course_id"])

    token, _link = _invite_create(admin, cid, ttl_minutes=60)
    h = _sha256_hex(token)

    # --- DB assertions (skip if DB not accessible)
    try:
        con = _open_db_or_skip()
        try:
            row = con.execute(
                "SELECT invite_id, token_hash, used_at, used_by FROM invite_tokens WHERE token_hash=?",
                (h,),
            ).fetchone()
            assert row, "Invite token row not found by token_hash (storage mismatch)."
            invite_id, token_hash, used_at, used_by = row
            assert token_hash == h

            # Ensure plaintext token is not stored in any obvious text column (best-effort)
            # This is a heuristic: we check the raw DB file bytes for the token string.
            # If the DB is compressed/encrypted, this may be inconclusive; we only fail on positive evidence.
            dbp = _guess_db_path()
            assert dbp is not None
            raw = dbp.read_bytes()
            assert token.encode("utf-8") not in raw, "Plaintext token appears in DB file bytes (should store only hash)."
        finally:
            con.close()
    except pytest.skip.Exception:
        # DB not accessible: still test single-use semantics via API (black-box)
        pass

    # First use succeeds
    s1, _me1 = api_register_and_login_student()
    r1 = s1.post(f"{BASE_URL}/api/join", json={"token": token}, timeout=10)
    assert r1.status_code == 200, r1.text

    # Second use must be rejected (allow common patterns)
    s2, _me2 = api_register_and_login_student()
    r2 = s2.post(f"{BASE_URL}/api/join", json={"token": token}, timeout=10)
    assert r2.status_code in (400, 403, 404, 409), r2.text

    # Optional: verify used_at written (if DB available)
    dbp = _guess_db_path()
    if dbp and dbp.exists():
        con = sqlite3.connect(str(dbp))
        try:
            row2 = con.execute(
                "SELECT used_at, used_by FROM invite_tokens WHERE token_hash=?",
                (h,),
            ).fetchone()
            if row2:
                used_at, used_by = row2
                assert used_at is not None, "Token was used but used_at not set."
                assert used_by is not None, "Token was used but used_by not set."
        finally:
            con.close()


def test_invite_token_expiry_enforced():
    admin = api_login_admin()
    course = api_create_course(admin, title="InviteExpiry", description="")
    cid = int(course["course_id"])

    token, _link = _invite_create(admin, cid, ttl_minutes=60)
    h = _sha256_hex(token)

    # Force expiry in DB for THIS token only (avoid cross-test interference)
    con = _open_db_or_skip()
    try:
        # repo stores expires_at as ISO string; SQLite datetime('now', ...) is fine as TEXT
        con.execute(
            "UPDATE invite_tokens SET expires_at = datetime('now','-10 minutes') WHERE token_hash=?",
            (h,),
        )
        con.commit()
    finally:
        con.close()

    s, _me = api_register_and_login_student()
    rj = s.post(f"{BASE_URL}/api/join", json={"token": token}, timeout=10)

    # Reject expired token (allow common patterns)
    assert rj.status_code in (400, 403, 404), rj.text