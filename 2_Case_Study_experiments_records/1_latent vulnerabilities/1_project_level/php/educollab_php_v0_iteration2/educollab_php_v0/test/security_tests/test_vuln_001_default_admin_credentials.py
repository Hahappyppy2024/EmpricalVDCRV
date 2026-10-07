#
# from helpers import login
#
# def test_exploit_default_admin_credentials_work(client):
#     session, base_url = client
#     r = login(session, base_url, "admin", "admin123")
#     assert r.status_code == 200
#     assert r.json()["user"]["username"] == "admin"
