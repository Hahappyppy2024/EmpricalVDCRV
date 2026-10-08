$BASE_URL = "http://localhost:8080"
$SESSION_COOKIE = "hosting_session"

$USERS = @{
    alice = @{ email="alice@example.test"; password="Password123!"; id=1; role="customer"; status="active" }
    bob = @{ email="bob@example.test"; password="Password123!"; id=2; role="customer"; status="active" }
    operator = @{ email="operator@example.test"; password="Password123!"; id=3; role="operator"; status="active" }
    admin = @{ email="admin@example.test"; password="Password123!"; id=4; role="admin"; status="active" }
    disabled = @{ email="disabled@example.test"; password="Password123!"; id=5; role="customer"; status="disabled" }
}
