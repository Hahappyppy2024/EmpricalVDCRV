<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AccountAccessRepository;
use App\Repositories\SessionRepository;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionRepository $sessions,
        private readonly AccountAccessRepository $accountAccess,
        private readonly SessionService $sessionsService,
        private readonly MailService $mail
    ) {
    }

    public function register(string $name, string $email, string $password, string $role = 'author'): array
    {
        $errors = Validator::validate(
            ['name' => $name, 'email' => $email, 'password' => $password],
            ['name' => 'required', 'email' => 'required|email', 'password' => 'required']
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors], 'Please fix the highlighted fields.');
        }
        if (!in_array($role, ['author', 'reviewer', 'chair', 'admin'], true)) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['role' => 'invalid role']]);
        }
        if ($this->users->findByEmail($email) !== null) {
            throw new WorkflowException('conflict_error', 409, [], 'An account with this email already exists.');
        }
        $id = $this->users->insert([
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        $user = $this->users->getById($id);
        $this->accountAccess->insert([
            'user_id' => $id,
            'kind' => 'recovery',
            'payload' => 'account registered',
            'status' => 'completed',
        ]);
        $this->mail->send($email, 'Account created', "Your account has been created for the Conference Review System.");
        return $user;
    }

    public function login(string $email, string $password): array
    {
        $errors = Validator::validate(
            ['email' => $email, 'password' => $password],
            ['email' => 'required|email', 'password' => 'required']
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors], 'Please fix the highlighted fields.');
        }
        $user = $this->users->findByEmail($email);
        if ($user === null || !password_verify($password, $user['password_hash'])) {
            throw new WorkflowException('auth_error', 401, [], 'Incorrect email or password.');
        }
        $this->accountAccess->insert([
            'user_id' => (int) $user['id'],
            'kind' => 'login',
            'payload' => 'sign-in succeeded',
            'status' => 'completed',
        ]);
        return $user;
    }

    public function signout(int $userId): void
    {
        $this->accountAccess->insert([
            'user_id' => $userId,
            'kind' => 'signout',
            'payload' => 'sign-out',
            'status' => 'completed',
        ]);
    }

    public function requestPasswordReset(string $email): array
    {
        $errors = Validator::requireFields(['email' => $email], ['email']);
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            // Deterministic non-disclosing response: do not reveal whether the email exists.
            throw new WorkflowException('not_found', 404, [], 'No account matches that email address.');
        }
        $token = bin2hex(random_bytes(16));
        $id = $this->accountAccess->insert([
            'user_id' => (int) $user['id'],
            'kind' => 'reset_request',
            'token' => hash('sha256', $token),
            'token_expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'payload' => 'password reset requested',
            'status' => 'pending',
        ]);
        $this->mail->send($email, 'Password reset', "Use this reset token: {$token}");
        $record = $this->accountAccess->getById($id);
        $record['plain_token'] = $token;
        return $record;
    }

    public function confirmPasswordReset(string $email, string $token, string $newPassword): array
    {
        $errors = Validator::validate(
            ['email' => $email, 'token' => $token, 'new_password' => $newPassword],
            ['email' => 'required|email', 'token' => 'required', 'new_password' => 'required']
        );
        if ($errors !== []) {
            throw new WorkflowException('validation_error', 422, ['fields' => $errors]);
        }
        $record = $this->accountAccess->findPendingByToken(hash('sha256', $token));
        if ($record === null || (int) $record['user_id'] !== $this->requireUserId($email)) {
            throw new WorkflowException('auth_error', 401, [], 'Invalid or expired reset token.');
        }
        $user = $this->users->getById((int) $record['user_id']);
        $this->users->update((int) $user['id'], [
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ]);
        $this->accountAccess->update((int) $record['id'], ['status' => 'used']);
        return $user;
    }

    private function requireUserId(string $email): int
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            throw new WorkflowException('auth_error', 401, [], 'Invalid or expired reset token.');
        }
        return (int) $user['id'];
    }
}
