import requests
from conftest import BASE_URL
from util import api_login, api_create_course, make_zip_bytes, rand

def _extract_names(items):
    names = []
    for it in items:
        if isinstance(it, str):
            names.append(it)
        elif isinstance(it, dict):
            for k in ("name", "path", "relpath", "relative_path", "filename"):
                if k in it and isinstance(it[k], str):
                    names.append(it[k])
                    break
    return "\n".join(names)

def _json_or_raise(r, label=""):
    ct = r.headers.get("Content-Type", "")
    if "application/json" not in ct.lower():
        raise AssertionError(
            f"[{label}] Expected JSON but got ct={ct} status={r.status_code} body_head={r.text[:400]}"
        )
    return r.json()

def test_RL_MAT2_list_materials_files_functional():
    s = requests.Session()
    api_login(s, "admin", "admin123")
    course_id = api_create_course(s, "C_" + rand("c"), "desc")

    zip_bytes = make_zip_bytes({"a.txt": b"a", "dir/b.txt": b"b"})
    r_up = s.post(
        f"{BASE_URL}/api/courses/{course_id}/materials/upload-zip",
        files={"zip": ("m.zip", zip_bytes, "application/zip")},
        allow_redirects=False,
    )
    assert r_up.status_code in (200, 201), r_up.text[:200]

    # Try several possible endpoints (different versions of your app used different routes)
    candidates = [
        f"/api/courses/{course_id}/materials",         # new controller apiIndex
        f"/api/courses/{course_id}/materials/files",   # old apiListFiles
        f"/api/courses/{course_id}/materials/index",   # fallback (just in case)
    ]

    last = None
    for path in candidates:
        r = s.get(f"{BASE_URL}{path}", allow_redirects=False)
        last = (path, r)
        if r.status_code == 200 and "application/json" in r.headers.get("Content-Type", "").lower():
            data = r.json()
            break
    else:
        path, r = last
        raise AssertionError(
            f"No JSON materials endpoint matched. Last tried {path} -> status={r.status_code} "
            f"ct={r.headers.get('Content-Type','')} body_head={r.text[:400]}"
        )

    # Prefer "files" if present; some endpoints return {"files":[...]} others return {"materials":..., "files":...}
    items = data.get("files") or []
    joined = _extract_names(items)

    assert "a.txt" in joined
    assert ("dir/b.txt" in joined) or ("b.txt" in joined)