from helpers import login, register


def _force_fixed_session_id(session, base_url, fixed_id: str):
    host = base_url.split('://', 1)[1].split('/', 1)[0].split(':', 1)[0]
    session.cookies.clear()
    session.cookies.set('PHPSESSID', fixed_id, domain=host, path='/')


def test_exploit_login_does_not_rotate_session_id(client):
    session, base_url = client
    fixed_id = 'fixatedsessionid1234567890'
    _force_fixed_session_id(session, base_url, fixed_id)

    r = login(session, base_url, 'admin', 'admin123')
    assert r.status_code == 200, r.text
    assert session.cookies.get('PHPSESSID') == fixed_id


def test_exploit_registration_does_not_rotate_session_id(client):
    session, base_url = client
    fixed_id = 'fixatedregistersid1234567890'
    _force_fixed_session_id(session, base_url, fixed_id)

    r = register(session, base_url, 'session_fix_user', 'pass123', 'Session Fix User')
    assert r.status_code == 200, r.text
    assert session.cookies.get('PHPSESSID') == fixed_id
