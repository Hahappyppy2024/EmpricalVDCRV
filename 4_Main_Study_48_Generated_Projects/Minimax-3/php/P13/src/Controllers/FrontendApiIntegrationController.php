<?php
declare(strict_types=1);

namespace MailServer\Controllers;

use MailServer\Auth\SessionService;
use MailServer\Helpers\ResponseHelper;
use MailServer\Repositories\ApiErrorRepository;
use MailServer\Repositories\AuditRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Views\PhpRenderer;

class FrontendApiIntegrationController
{
    public function __construct(
        private ApiErrorRepository $errors,
        private AuditRepository $audit,
        private SessionService $session,
        private PhpRenderer $view
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->errors->list();
        $audit = $this->audit->all(20);
        return $this->view->render($response, 'errors.php', [
            'user' => $user,
            'items' => $items,
            'audit' => $audit,
            'result' => null,
        ]);
    }

    public function simulate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $endpoint = trim((string) ($data['endpoint'] ?? '/api/mail/message_compose'));
        $code = (int) ($data['error_code'] ?? 422);
        $state = trim((string) ($data['error_state'] ?? 'unknown'));
        $message = trim((string) ($data['message'] ?? ''));

        $validStates = [
            200 => 'ok',
            401 => 'unauthorized',
            403 => 'permission_denied',
            404 => 'not_found',
            409 => 'conflict',
            413 => 'payload_too_large',
            415 => 'unsupported_media_type',
            422 => 'invalid_input',
            500 => 'server_error',
        ];
        if (!isset($validStates[$code])) {
            return $this->view->render($response->withStatus(422), 'errors.php', [
                'user' => $user,
                'items' => $this->errors->list(),
                'audit' => $this->audit->all(20),
                'result' => ['ok' => false, 'msg' => 'Use one of the supported error codes.'],
            ]);
        }

        $id = $this->errors->log((int) $user['id'], $endpoint, $code, $state ?: $validStates[$code], $message);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'frontend.api.log', 'api_error', (string) $id, $endpoint . ' -> ' . $code, $ip);
        return $this->view->render($response, 'errors.php', [
            'user' => $user,
            'items' => $this->errors->list(),
            'audit' => $this->audit->all(20),
            'result' => ['ok' => true, 'msg' => 'Logged API error id=' . $id, 'id' => $id],
        ]);
    }

    public function apiGet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $items = $this->errors->list();
        return ResponseHelper::json($response, ['errors' => $items, 'role' => $user['role']]);
    }

    public function apiPost(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = (array) $request->getParsedBody();
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $code = (int) ($data['error_code'] ?? 0);
        $state = trim((string) ($data['error_state'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        if ($endpoint === '' || $code === 0 || $state === '') {
            return ResponseHelper::validationError($response, ['endpoint' => 'required', 'error_code' => 'required', 'error_state' => 'required']);
        }
        $id = $this->errors->log((int) $user['id'], $endpoint, $code, $state, $message);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'frontend.api.log.api', 'api_error', (string) $id, $endpoint . ' -> ' . $code, $ip);
        return ResponseHelper::json($response, ['ok' => true, 'id' => $id], 201);
    }

    public function apiPatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) ($args['id'] ?? 0);
        $existing = $this->errors->find($id);
        if (!$existing) {
            return ResponseHelper::stableError($response, 'not_found', 404);
        }
        $data = (array) $request->getParsedBody();
        $state = trim((string) ($data['error_state'] ?? 'resolved'));
        $this->errors->resolve($id, $state);
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->session->recordAudit((int) $user['id'], $user['role'], 'frontend.api.resolve', 'api_error', (string) $id, $state, $ip);
        return ResponseHelper::json($response, ['ok' => true]);
    }
}