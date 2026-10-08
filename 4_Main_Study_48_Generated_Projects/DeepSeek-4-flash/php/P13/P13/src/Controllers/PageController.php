<?php

declare(strict_types=1);

namespace P13\Controllers;

use P13\Database;
use P13\Session;
use P13\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Server-rendered browser pages for every actor-facing workflow.
 */
final class PageController
{
    public function __construct(
        private Database $db,
        private Session $session,
        private View $view
    ) {
    }

    public function home(Request $request, Response $response): Response
    {
        if ($this->session->isGuest()) {
            return redirect_response($response, '/login');
        }
        return redirect_response($response, '/dashboard');
    }

    public function dashboard(Request $request, Response $response): Response
    {
        return $this->page($response, 'dashboard', 'Dashboard', 'dashboard');
    }

    public function account(Request $request, Response $response): Response
    {
        return $this->page($response, 'account', 'Account & access', 'account');
    }

    public function mailbox(Request $request, Response $response): Response
    {
        return $this->page($response, 'mailbox', 'Mailbox overview', 'mailbox');
    }

    public function compose(Request $request, Response $response, array $args): Response
    {
        $draftId = isset($args['id']) ? (int) $args['id'] : null;
        if ($draftId !== null) {
            $draft = $this->db->row(
                'SELECT * FROM message_compose WHERE id = ? AND user_id = ?',
                [$draftId, (int) $this->session->userId()]
            );
            if ($draft === null) {
                return $this->notFound($response);
            }
        }
        $data = [
            'title' => 'Compose message',
            'active' => 'compose',
            'draft_id' => $draftId,
        ];
        return $this->renderPage($response, 'compose', $data);
    }

    public function message(Request $request, Response $response, array $args): Response
    {
        $messageId = (int) $args['id'];
        $row = $this->db->row(
            'SELECT * FROM messages WHERE id = ? AND user_id = ?',
            [$messageId, (int) $this->session->userId()]
        );
        if ($row === null) {
            return $this->notFound($response);
        }
        return $this->renderPage($response, 'message', [
            'title' => 'Message',
            'active' => 'mailbox',
            'message_id' => $messageId,
        ]);
    }

    public function attachments(Request $request, Response $response): Response
    {
        return $this->page($response, 'attachments', 'Attachment handling', 'attachments');
    }

    public function contacts(Request $request, Response $response): Response
    {
        return $this->page($response, 'contacts', 'Contact management', 'contacts');
    }

    public function rules(Request $request, Response $response): Response
    {
        return $this->page($response, 'rules', 'Filters and rules', 'rules');
    }

    public function domains(Request $request, Response $response): Response
    {
        return $this->page($response, 'domains', 'Domain management', 'domains');
    }

    public function quarantine(Request $request, Response $response): Response
    {
        return $this->page($response, 'quarantine', 'Quarantine', 'quarantine');
    }

    public function audit(Request $request, Response $response): Response
    {
        return $this->page($response, 'audit', 'Admin audit logs', 'audit');
    }

    public function importExport(Request $request, Response $response): Response
    {
        return $this->page($response, 'import_export', 'Import / export', 'import_export');
    }

    public function errorsDemo(Request $request, Response $response): Response
    {
        return $this->page($response, 'errors', 'Frontend API integration & errors', 'errors');
    }

    private function page(Response $response, string $template, string $title, string $active): Response
    {
        return $this->renderPage($response, $template, ['title' => $title, 'active' => $active]);
    }

    private function renderPage(Response $response, string $template, array $data): Response
    {
        $user = $this->session->user();
        $data['user'] = $user;
        $data['csrf'] = $this->session->csrfToken();
        $html = $this->view->render($template, $data);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function notFound(Response $response): Response
    {
        $html = $this->view->render('error/404', ['title' => 'Not found', 'user' => $this->session->user(), 'active' => '']);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(404);
    }
}
