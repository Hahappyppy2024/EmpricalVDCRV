<?php
declare(strict_types=1);

namespace App;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

final class AuthAdminRoutes
{
    use Support;

    public static function register(App $app, PDO $db, Auth $auth, CloudRepository $repo, array $config): void
    {
        $app->get('/', fn(Request $request, Response $response) => $response->withHeader('Location', '/app.html')->withStatus(302));
        $app->get('/health', fn(Request $request, Response $response) => Http::json($response, ['status' => 'ok', 'database' => (bool)$db->query('SELECT 1')->fetchColumn()]));

        $app->post('/api/auth/register', function (Request $request, Response $response) use ($db, $auth, $config) {
            $data = self::body($request);
            self::required($data, ['email','password','name']);
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ApiException(422, 'validation_failed', 'Email is invalid.', ['email' => 'Enter a valid email.']);
            }
            if (strlen((string)$data['password']) < 10) {
                throw new ApiException(422, 'validation_failed', 'Password is too short.', ['password' => 'Use at least 10 characters.']);
            }
            try {
                $db->prepare("INSERT INTO users(email,password_hash,name,role) VALUES(?,?,?,'user')")
                    ->execute([strtolower(trim($data['email'])), password_hash($data['password'], PASSWORD_DEFAULT), trim($data['name'])]);
            } catch (\PDOException) {
                throw new ApiException(409, 'email_exists', 'An account already uses this email.');
            }
            $id = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO folders(name,owner_id) VALUES(?,?)')->execute(['My Files', $id]);
            $token = $auth->create($id);
            $user = self::one($db, 'SELECT id,email,name,role,status,quota_bytes FROM users WHERE id=?', [$id]);
            return Http::json($response, ['user' => $user], 201)->withAddedHeader('Set-Cookie', $auth->cookie($token, $config['sessionTtl']));
        });

        $app->post('/api/auth/login', function (Request $request, Response $response) use ($db, $auth, $config) {
            $data = self::body($request);
            self::required($data, ['email','password']);
            $statement = $db->prepare('SELECT * FROM users WHERE email=? COLLATE NOCASE');
            $statement->execute([trim($data['email'])]);
            $user = $statement->fetch();
            if (!$user || !password_verify((string)$data['password'], $user['password_hash'])) {
                throw new ApiException(401, 'invalid_credentials', 'Email or password is incorrect.');
            }
            if ($user['status'] !== 'active') {
                throw new ApiException(403, 'account_disabled', 'This account is disabled.');
            }
            $token = $auth->create((int)$user['id']);
            unset($user['password_hash']);
            return Http::json($response, ['user' => $user])->withAddedHeader('Set-Cookie', $auth->cookie($token, $config['sessionTtl']));
        });

        $app->post('/api/auth/logout', function (Request $request, Response $response) use ($auth) {
            $auth->destroy($request);
            return Http::json($response, ['message' => 'Signed out.'])->withAddedHeader('Set-Cookie', $auth->cookie('', 0));
        });

        $app->post('/api/auth/password-reset-requests', function (Request $request, Response $response) use ($db, $config) {
            $data = self::body($request);
            self::required($data, ['email']);
            $statement = $db->prepare("SELECT id FROM users WHERE email=? COLLATE NOCASE AND status='active'");
            $statement->execute([trim($data['email'])]);
            $id = $statement->fetchColumn();
            $result = ['message' => 'If that account exists, a reset instruction has been created.'];
            if ($id) {
                $token = bin2hex(random_bytes(20));
                $db->prepare('DELETE FROM password_reset_tokens WHERE user_id=?')->execute([$id]);
                $db->prepare("INSERT INTO password_reset_tokens(token_hash,user_id,expires_at) VALUES(?,?,datetime('now',?))")
                    ->execute([hash('sha256', $token), $id, '+' . $config['resetTtl'] . ' seconds']);
                if ($config['appEnv'] === 'development') {
                    $result['developmentResetToken'] = $token;
                }
            }
            return Http::json($response, $result);
        });

        $app->post('/api/auth/password-resets', function (Request $request, Response $response) use ($db) {
            $data = self::body($request);
            self::required($data, ['resetToken','newPassword']);
            if (strlen((string)$data['newPassword']) < 10) {
                throw new ApiException(422, 'validation_failed', 'Password is too short.', ['newPassword' => 'Use at least 10 characters.']);
            }
            $row = self::one($db, "SELECT * FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>datetime('now')", [hash('sha256', $data['resetToken'])], 'reset_token_invalid');
            $db->beginTransaction();
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($data['newPassword'], PASSWORD_DEFAULT), $row['user_id']]);
            $db->prepare("UPDATE password_reset_tokens SET used_at=datetime('now') WHERE token_hash=?")->execute([$row['token_hash']]);
            $db->prepare('DELETE FROM sessions WHERE user_id=?')->execute([$row['user_id']]);
            $db->commit();
            return Http::json($response, ['message' => 'Password reset completed.']);
        });

        $app->get('/api/storage/quota', function (Request $request, Response $response) use ($auth, $repo) {
            $user = $auth->requireUser($request);
            return Http::json($response, ['quota' => $repo->quota((int)$user['id'])]);
        });
        $app->get('/api/admin/users/{userId}/quota', function (Request $request, Response $response, array $args) use ($auth, $repo) {
            $auth->requireUser($request, ['admin']);
            return Http::json($response, ['quota' => $repo->quota((int)$args['userId'])]);
        });
        $app->patch('/api/admin/users/{userId}/quota', function (Request $request, Response $response, array $args) use ($auth, $db, $repo) {
            $auth->requireUser($request, ['admin']);
            $data = self::body($request);
            self::required($data, ['quotaBytes']);
            $quota = (int)$data['quotaBytes'];
            if ($quota < 1) {
                throw new ApiException(422, 'validation_failed', 'Quota must be positive.', ['quotaBytes' => 'Use a positive integer.']);
            }
            self::one($db, 'SELECT id FROM users WHERE id=?', [(int)$args['userId']], 'user_not_found');
            $db->prepare('UPDATE users SET quota_bytes=? WHERE id=?')->execute([$quota, (int)$args['userId']]);
            return Http::json($response, ['quota' => $repo->quota((int)$args['userId'])]);
        });

        $app->get('/api/admin/users', function (Request $request, Response $response) use ($auth, $db) {
            $auth->requireUser($request, ['admin']);
            [$limit, $offset, $page] = self::page($request);
            $items = self::all($db, 'SELECT id,email,name,role,status,quota_bytes,created_at FROM users ORDER BY id LIMIT ? OFFSET ?', [$limit, $offset]);
            return Http::json($response, ['items' => $items, 'page' => $page, 'limit' => $limit]);
        });
        $app->patch('/api/admin/users/{userId}', function (Request $request, Response $response, array $args) use ($auth, $db) {
            $actor = $auth->requireUser($request, ['admin']);
            $user = self::one($db, 'SELECT * FROM users WHERE id=?', [(int)$args['userId']], 'user_not_found');
            $data = self::body($request);
            $sets = [];
            $params = [];
            if (array_key_exists('name', $data)) {
                if (trim((string)$data['name']) === '') {
                    throw new ApiException(422, 'validation_failed', 'Name is required.', ['name' => 'Required.']);
                }
                $sets[] = 'name=?';
                $params[] = trim($data['name']);
            }
            if (array_key_exists('status', $data)) {
                if (!in_array($data['status'], ['active','disabled'], true)) {
                    throw new ApiException(422, 'validation_failed', 'Status is invalid.', ['status' => 'Use active or disabled.']);
                }
                if ((int)$user['id'] === (int)$actor['id'] && $data['status'] === 'disabled') {
                    throw new ApiException(409, 'self_disable_denied', 'An administrator cannot disable the current account.');
                }
                $sets[] = 'status=?';
                $params[] = $data['status'];
            }
            if (!$sets) {
                throw new ApiException(422, 'validation_failed', 'No supported user fields supplied.');
            }
            $params[] = $user['id'];
            $db->prepare('UPDATE users SET ' . implode(',', $sets) . ' WHERE id=?')->execute($params);
            if (($data['status'] ?? '') === 'disabled') {
                $db->prepare('DELETE FROM sessions WHERE user_id=?')->execute([$user['id']]);
            }
            return Http::json($response, ['user' => self::one($db, 'SELECT id,email,name,role,status,quota_bytes FROM users WHERE id=?', [$user['id']])]);
        });
        $app->patch('/api/admin/retention-settings', function (Request $request, Response $response) use ($auth, $db) {
            $auth->requireUser($request, ['admin']);
            $data = self::body($request);
            self::required($data, ['trashDays','version']);
            $current = self::one($db, 'SELECT * FROM retention_settings WHERE id=1');
            if ((int)$data['version'] !== (int)$current['version']) {
                throw new ApiException(409, 'stale_version', 'Retention settings changed in another request.');
            }
            $days = (int)$data['trashDays'];
            if ($days < 1 || $days > 3650) {
                throw new ApiException(422, 'validation_failed', 'Retention period is invalid.', ['trashDays' => 'Use 1 through 3650.']);
            }
            $db->prepare("UPDATE retention_settings SET trash_days=?,version=version+1,updated_at=datetime('now') WHERE id=1")->execute([$days]);
            return Http::json($response, ['retentionSetting' => self::one($db, 'SELECT * FROM retention_settings WHERE id=1')]);
        });
    }
}
