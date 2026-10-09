<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\UserRepository;
use Shop\Models\AuditRepository;
use Shop\Support\Mailer;
use Shop\Support\Validator;

final class AccountsController extends BaseController
{
    public function loginForm(Request $request, Response $response): Response
    {
        if (SessionManager::user() !== null) {
            return $this->redirect($response, $this->dashboardFor(SessionManager::user()['role']));
        }
        return $this->render($response, 'login.php', ['page_title' => 'Sign in']);
    }

    public function login(Request $request, Response $response): Response
    {
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/login');
        }
        $email = trim((string)$this->input($request, 'email'));
        $password = (string)$this->input($request, 'password');
        if (!Validator::email($email) || $password === '') {
            SessionManager::flash('error', 'Email and password are required.');
            return $this->redirect($response, '/login');
        }
        $user = UserRepository::findByEmail($email);
        if (!$user || $user['status'] !== 'active' || !UserRepository::verifyPassword($user, $password)) {
            SessionManager::flash('error', 'Invalid email or password.');
            return $this->redirect($response, '/login');
        }
        SessionManager::login((int)$user['id'], $user['role']);
        AuditRepository::log((int)$user['id'], 'login', 'user', (string)$user['id']);
        SessionManager::flash('success', 'Welcome back, ' . $user['display_name'] . '.');
        return $this->redirect($response, $this->dashboardFor($user['role']));
    }

    public function registerForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'register.php', ['page_title' => 'Register']);
    }

    public function register(Request $request, Response $response): Response
    {
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/register');
        }
        $email = trim((string)$this->input($request, 'email'));
        $name = trim((string)$this->input($request, 'display_name'));
        $password = (string)$this->input($request, 'password');
        $role = in_array($this->input($request, 'role'), ['customer', 'seller'], true) ? (string)$this->input($request, 'role') : 'customer';
        $errors = Validator::requireFields(['email' => $email, 'display_name' => $name, 'password' => $password], [
            'email' => 'Email', 'display_name' => 'Display name', 'password' => 'Password',
        ]);
        if (!Validator::email($email)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (!Validator::password($password)) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        if (UserRepository::findByEmail($email)) {
            $errors['email'] = 'Email is already registered.';
        }
        if ($errors) {
            SessionManager::flash('error', implode(' ', array_values($errors)));
            return $this->redirect($response, '/register');
        }
        $created = UserRepository::create($email, $password, $name, $role);
        AuditRepository::log((int)$created['id'], 'register', 'user', (string)$created['id']);
        Mailer::send($email, 'Welcome to P03', "Hello {$name}, your account has been created.");
        SessionManager::login((int)$created['id'], $created['role']);
        SessionManager::flash('success', 'Account created.');
        return $this->redirect($response, $this->dashboardFor($created['role']));
    }

    public function logout(Request $request, Response $response): Response
    {
        $user = SessionManager::user();
        if ($user) {
            AuditRepository::log((int)$user['id'], 'logout', 'user', (string)$user['id']);
        }
        SessionManager::logout();
        return $this->redirect($response, '/');
    }

    public function forgotForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'password_forgot.php', ['page_title' => 'Forgot password']);
    }

    public function forgot(Request $request, Response $response): Response
    {
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/password/forgot');
        }
        $email = trim((string)$this->input($request, 'email'));
        $user = $email ? UserRepository::findByEmail($email) : null;
        if ($user) {
            $token = UserRepository::createReset((int)$user['id']);
            Mailer::send($email, 'Reset your password', "Use this token to reset: {$token}");
            SessionManager::flash('notice', 'If the email exists, a reset token has been sent. (Token logged to outbox.)');
        } else {
            SessionManager::flash('notice', 'If the email exists, a reset token has been sent.');
        }
        return $this->redirect($response, '/password/forgot');
    }

    public function resetForm(Request $request, Response $response): Response
    {
        return $this->render($response, 'password_reset.php', [
            'page_title' => 'Reset password',
            'token' => (string)$request->getQueryParams()['token'] ?? '',
        ]);
    }

    public function reset(Request $request, Response $response): Response
    {
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/password/reset');
        }
        $token = trim((string)$this->input($request, 'token'));
        $password = (string)$this->input($request, 'password');
        if (!Validator::password($password) || $token === '') {
            SessionManager::flash('error', 'Token and a strong password are required.');
            return $this->redirect($response, '/password/reset');
        }
        if (!UserRepository::consumeReset($token, $password)) {
            SessionManager::flash('error', 'Invalid or expired token.');
            return $this->redirect($response, '/password/reset');
        }
        SessionManager::flash('success', 'Password updated. Please sign in.');
        return $this->redirect($response, '/login');
    }

    public function account(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        return $this->render($response, 'account.php', [
            'page_title' => 'Account',
            'profile_user' => $user,
        ]);
    }

    public function updateAccount(Request $request, Response $response): Response
    {
        $user = $this->requireAuth($request);
        if (!$this->verifyCsrf($request)) {
            SessionManager::flash('error', 'Invalid security token.');
            return $this->redirect($response, '/account');
        }
        $name = trim((string)$this->input($request, 'display_name'));
        if (!Validator::text($name, 2, 80)) {
            SessionManager::flash('error', 'Display name must be 2-80 characters.');
            return $this->redirect($response, '/account');
        }
        UserRepository::updateProfile((int)$user['id'], $name);
        AuditRepository::log((int)$user['id'], 'profile_update', 'user', (string)$user['id']);
        SessionManager::flash('success', 'Profile updated.');
        return $this->redirect($response, '/account');
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = SessionManager::user();
        if ($user === null) {
            return $this->json($response, ['error' => 'authentication_required'], 401);
        }
        return $this->json($response, ['user' => $user, 'self' => true]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $data = $this->jsonBody($request);
        $email = trim((string)($data['email'] ?? ''));
        $name = trim((string)($data['display_name'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $role = in_array($data['role'] ?? null, ['customer', 'seller'], true) ? (string)$data['role'] : 'customer';
        if (!Validator::email($email) || !Validator::password($password) || !Validator::text($name, 2, 80)) {
            return $this->json($response, ['error' => 'invalid_input'], 400);
        }
        if (UserRepository::findByEmail($email)) {
            return $this->json($response, ['error' => 'duplicate'], 409);
        }
        $created = UserRepository::create($email, $password, $name, $role);
        AuditRepository::log((int)$created['id'], 'register_api', 'user', (string)$created['id']);
        return $this->json($response, ['user' => $created], 201);
    }

    public function apiUpdate(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireAuth($request);
        $id = (int)($args['id'] ?? 0);
        $actor = SessionManager::user();
        if ($actor['role'] !== 'admin' && (int)$actor['id'] !== $id) {
            return $this->json($response, ['error' => 'forbidden'], 403);
        }
        $data = $this->jsonBody($request);
        $name = trim((string)($data['display_name'] ?? ''));
        if (!Validator::text($name, 2, 80)) {
            return $this->json($response, ['error' => 'invalid_input'], 400);
        }
        UserRepository::updateProfile($id, $name, null);
        AuditRepository::log((int)$actor['id'], 'profile_update_api', 'user', (string)$id);
        return $this->json($response, ['user' => UserRepository::find($id)]);
    }

    private function dashboardFor(string $role): string
    {
        return match ($role) {
            'admin' => '/admin/operations',
            'seller' => '/seller/catalog',
            'moderator' => '/reviews/moderation',
            default => '/customer/data',
        };
    }
}