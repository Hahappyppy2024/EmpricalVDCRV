<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\PhaseService;
use App\Services\Workflows\AccountAccessRecoveryService;
use App\Services\Workflows\BulkExportsService;
use App\Services\Workflows\ConferencePhasesService;
use App\Services\Workflows\DecisionManagementService;
use App\Services\Workflows\DoubleBlindViewsService;
use App\Services\Workflows\FrontendApiService;
use App\Services\Workflows\ManuscriptAccessService;
use App\Services\Workflows\PaperSubmissionService;
use App\Services\Workflows\RebuttalService;
use App\Services\Workflows\ReviewerAssignmentService;
use App\Services\Workflows\ReviewingService;
use App\Services\Workflows\SubmissionDiscoveryService;
use App\Views\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PageController
{
    public function __construct(
        private readonly ViewRenderer $renderer,
        private readonly PhaseService $phases,
        private readonly PaperSubmissionService $submissions,
        private readonly SubmissionDiscoveryService $discovery,
        private readonly ManuscriptAccessService $manuscriptAccess,
        private readonly ReviewerAssignmentService $assignments,
        private readonly ReviewingService $reviewing,
        private readonly RebuttalService $rebuttals,
        private readonly DecisionManagementService $decisions,
        private readonly ConferencePhasesService $conferencePhases,
        private readonly BulkExportsService $exports,
        private readonly AccountAccessRecoveryService $accountAccess,
        private readonly DoubleBlindViewsService $blindViews,
        private readonly FrontendApiService $frontendApi,
        private readonly AuditService $audit,
        private readonly \PDO $db,
        private readonly array $config
    ) {
    }

    public function home(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $target = $user !== null ? '/dashboard' : '/login';
        return $response->withStatus(302)->withHeader('Location', $target);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($request->getAttribute('user') !== null) {
            return $response->withStatus(302)->withHeader('Location', '/dashboard');
        }
        return $this->authPage($response, 'pages/login', 'Sign in');
    }

    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($request->getAttribute('user') !== null) {
            return $response->withStatus(302)->withHeader('Location', '/dashboard');
        }
        return $this->authPage($response, 'pages/register', 'Create account');
    }

    public function resetPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->authPage($response, 'pages/reset_password', 'Password reset');
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'phases' => $this->phases->all(),
            'summary' => $this->summaryFor($user),
            'recent' => $this->recentFor($user),
        ];
        return $this->page($response, 'pages/dashboard', 'Dashboard', $data, $user);
    }

    public function papers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'submissions' => $this->submissions->visibleSubmissions($user, [])['submissions'] ?? [],
        ];
        return $this->page($response, 'pages/paper_submission', 'Paper submission', $data, $user);
    }

    public function paperDetail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $id = (int) $args['id'];
        $data = [
            'user' => $user,
            'submission' => $this->submissions->show($user, $id),
            'blind_view' => $this->blindViews->viewForSubmission($user, $id)['view'] ?? null,
        ];
        return $this->page($response, 'pages/paper_detail', 'Submission detail', $data, $user);
    }

    public function discovery(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'result' => $this->discovery->listFor($user, $request->getQueryParams()),
        ];
        return $this->page($response, 'pages/submission_discovery', 'Submission discovery', $data, $user);
    }

    public function reviewer(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'assignments' => $this->assignments->listFor($user, [])['assignments'] ?? [],
            'reviews' => $this->reviewing->listFor($user, [])['reviews'] ?? [],
        ];
        return $this->page($response, 'pages/reviewing', 'Reviewer workspace', $data, $user);
    }

    public function chair(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'phases' => $this->conferencePhases->listFor($user, [])['phases'] ?? [],
            'assignments' => $this->assignments->listFor($user, [])['assignments'] ?? [],
            'decisions' => $this->decisions->listFor($user, [])['decisions'] ?? [],
            'exports' => $this->exports->listFor($user, [])['exports'] ?? [],
            'audit' => $this->audit->recent(20),
            'all_submissions' => $this->submissions->visibleSubmissions($user, []),
            'reviewers' => $this->reviewerUsers($user),
        ];
        return $this->page($response, 'pages/chair', 'Chair workspace', $data, $user);
    }

    public function rebuttal(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'rebuttals' => $this->rebuttals->listFor($user, [])['rebuttals'] ?? [],
            'rebuttable' => $this->rebuttableSubmissions($user),
        ];
        return $this->page($response, 'pages/rebuttal', 'Rebuttal', $data, $user);
    }

    public function account(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'records' => $this->accountAccess->listFor($user, [])['records'] ?? [],
            'errors' => $this->frontendApi->listFor($user, [])['reports'] ?? [],
        ];
        return $this->page($response, 'pages/account_access', 'Account access and recovery', $data, $user);
    }

    public function errors(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'result' => $this->frontendApi->listFor($user, []),
        ];
        return $this->page($response, 'pages/frontend_api', 'API integration and errors', $data, $user);
    }

    public function exports(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'exports' => $this->exports->listFor($user, [])['exports'] ?? [],
        ];
        return $this->page($response, 'pages/bulk_exports', 'Bulk exports', $data, $user);
    }

    public function phases(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'phases' => $this->conferencePhases->listFor($user, [])['phases'] ?? [],
        ];
        return $this->page($response, 'pages/conference_phases', 'Conference phases', $data, $user);
    }

    public function assignments(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'assignments' => $this->assignments->listFor($user, [])['assignments'] ?? [],
            'all_submissions' => $this->submissions->visibleSubmissions($user, []),
            'reviewers' => $this->reviewerUsers($user),
        ];
        return $this->page($response, 'pages/reviewer_assignment', 'Reviewer assignment', $data, $user);
    }

    public function decisions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'decisions' => $this->decisions->listFor($user, [])['decisions'] ?? [],
            'all_submissions' => $this->submissions->visibleSubmissions($user, []),
        ];
        return $this->page($response, 'pages/decision_management', 'Decision management', $data, $user);
    }

    public function manuscript(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'records' => $this->manuscriptAccess->listFor($user, [])['records'] ?? [],
        ];
        return $this->page($response, 'pages/manuscript_access', 'Manuscript access', $data, $user);
    }

    public function blindViews(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute('user');
        $data = [
            'user' => $user,
            'views' => $this->blindViews->listFor($user, [])['views'] ?? [],
        ];
        return $this->page($response, 'pages/double_blind_views', 'Double-blind views', $data, $user);
    }

    public function accountApi(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->account($request, $response);
    }

    private function page(ResponseInterface $response, string $view, string $title, array $data, ?array $user): ResponseInterface
    {
        $content = $this->renderer->renderString('pages/' . basename($view), $data);
        return $this->renderer->render($response, 'layout/main', array_merge($data, [
            'content' => $content,
            'title' => $title,
            'active' => $view,
            'user' => $user,
            'ws_url' => $this->config['ws_url'],
            'app_url' => $this->config['app_url'],
            'app_name' => $this->config['app_name'],
        ]));
    }

    private function authPage(ResponseInterface $response, string $view, string $title): ResponseInterface
    {
        return $this->renderer->render($response, 'layout/auth', [
            'content' => $this->renderer->renderString('pages/' . basename($view), []),
            'title' => $title,
            'app_name' => $this->config['app_name'],
            'ws_url' => $this->config['ws_url'],
        ]);
    }

    private function summaryFor(array $user): array
    {
        $all = $this->submissions->visibleSubmissions($user, []);
        $submissionCount = count($all);
        $byStatus = [];
        foreach ($all as $submission) {
            $byStatus[$submission['status']] = ($byStatus[$submission['status']] ?? 0) + 1;
        }
        return [
            'submissions' => $submissionCount,
            'by_status' => $byStatus,
            'pending_assignments' => count($this->assignments->listFor($user, [])['assignments'] ?? []),
            'reviews' => count($this->reviewing->listFor($user, [])['reviews'] ?? []),
            'exports' => count($this->exports->listFor($user, [])['exports'] ?? []),
            'decisions' => count($this->decisions->listFor($user, [])['decisions'] ?? []),
        ];
    }

    private function recentFor(array $user): array
    {
        $subs = $this->submissions->visibleSubmissions($user, []);
        usort($subs, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
        return array_slice($subs, 0, 5);
    }

    private function rebuttableSubmissions(array $user): array
    {
        if ($user['role'] !== 'author') {
            return [];
        }
        $out = [];
        foreach ($this->submissions->visibleSubmissions($user, []) as $submission) {
            if ($submission['status'] === 'in_rebuttal') {
                $out[] = $submission;
            }
        }
        return $out;
    }

    private function reviewerUsers(array $user): array
    {
        $st = $this->db->query("SELECT id, name, email FROM users WHERE role = 'reviewer' ORDER BY name");
        return $st !== false ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
    }
}
