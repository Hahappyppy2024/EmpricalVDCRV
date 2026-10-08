<?php

declare(strict_types=1);

namespace Shop\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shop\Service\AccountService;
use Shop\SessionManager;
use Shop\View;

/**
 * SHOP-01 Accounts browser workflows: register, sign in, recover, sign out.
 */
final class AuthController extends Controller
{
    public function __construct(
        private View $view,
        private AccountService $accounts,
        private SessionManager $sessions
    ) {
    }

    public function loginPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->user($request) !== null) {
            return $this->redirect($response, '/');
        }
        return $this->html($response, $this->view->render('auth/login'));
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $this->body($request);
        try {
            $user = $this->accounts->login((string) ($data['email'] ?? ''), (string) ($data['password'] ?? ''), (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''));
            $this->sessions->start((int) $user['id'], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''), (string) ($request->getServerParams()['HTTP_USER_AGENT'] ?? ''));
            return $this->redirect($response, '/' . flash_query(null, 'Welcome back, ' . $user['name'] . '!'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/login' . flash_query($this->messageOf($e)));
        }
    }

    public function registerPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->user($request) !== null) {
            return $this->redirect($response, '/');
        }
        return $this->html($response, $this->view->render('auth/register'));
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $this->body($request);
        try {
            $user = $this->accounts->register($data);
            $this->sessions->start((int) $user['id'], (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''), (string) ($request->getServerParams()['HTTP_USER_AGENT'] ?? ''));
            return $this->redirect($response, '/' . flash_query(null, 'Account created. Welcome, ' . $user['name'] . '!'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/register' . flash_query($this->messageOf($e)));
        }
    }

    public function forgotPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->html($response, $this->view->render('auth/forgot'));
    }

    public function forgot(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $data = $this->body($request);
        $email = (string) ($data['email'] ?? '');
        $notice = 'If the account exists, a reset link has been sent.';
        if (!\Shop\Validation::email($email)) {
            $notice = 'Enter a valid email address.';
        } else {
            $this->accounts->requestReset($email);
        }
        return $this->html($response, $this->view->render('auth/forgot', ['notice' => $notice]));
    }

    public function resetPage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->html($response, $this->view->render('auth/reset', ['token' => (string) ($args['token'] ?? '')]));
    }

    public function reset(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = (string) ($args['token'] ?? '');
        try {
            $this->accounts->resetPassword($token, (string) ($this->body($request)['password'] ?? ''));
            return $this->redirect($response, '/login' . flash_query(null, 'Password reset. You can now sign in.'));
        } catch (\Throwable $e) {
            return $this->redirect($response, '/reset/' . urlencode($token) . flash_query($this->messageOf($e)));
        }
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $token = $_COOKIE[SessionManager::COOKIE] ?? '';
        if ($token !== '') {
            $this->sessions->destroy($token);
        }
        return $this->redirect($response, '/' . flash_query(null, 'You have been signed out.'));
    }

    private function messageOf(\Throwable $e): string
    {
        if ($e instanceof \Shop\DomainException || $e instanceof \Shop\ValidationException) {
            return $e->getMessage();
        }
        return 'Something went wrong. Please try again.';
    }
}
