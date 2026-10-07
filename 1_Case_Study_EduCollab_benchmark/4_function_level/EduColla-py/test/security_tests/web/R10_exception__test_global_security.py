# ============================================================
# Global - Basic security header sanity (best-effort)
# ============================================================

def test_global_no_server_debug_traceback_leak_on_404(client):
    rv = client.get("/this-route-should-not-exist-xyz", follow_redirects=False)
    assert rv.status_code in (404, 302)
    assert b"Traceback" not in rv.data
