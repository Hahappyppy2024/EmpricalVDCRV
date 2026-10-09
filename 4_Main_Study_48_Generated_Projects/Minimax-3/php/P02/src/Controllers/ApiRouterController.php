<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SessionService;
use App\Services\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ApiRouterController
{
    public function dispatch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $useCase = $args['use_case'] ?? '';
        $method = $request->getMethod();
        $id = $args['id'] ?? null;

        $map = [
            'account_access_and_recovery' => 'account_access',
            'conference_phases' => 'phases',
            'paper_submission' => 'submissions',
            'submission_discovery' => 'discovery',
            'manuscript_access' => 'manuscript',
            'reviewer_assignment' => 'reviewer_assignment',
            'reviewing' => 'reviewing',
            'rebuttal' => 'rebuttal',
            'decision_management' => 'decision_management',
            'double_blind_views' => 'double_blind',
            'bulk_exports' => 'exports',
            'frontend_api_integration_and_errors' => 'frontend_errors',
        ];

        if (!isset($map[$useCase])) {
            return View::json(['ok' => false, 'error' => 'unknown_use_case'], 404);
        }
        $resource = $map[$useCase];

        if ($method === 'GET' && !$id) {
            return $this->summary($resource);
        }
        if ($method === 'POST') {
            return View::json(['ok' => true, 'use_case' => $useCase, 'resource' => $resource, 'message' => 'See HTML form for full submission flow.', 'session_user' => SessionService::userId()], 202);
        }
        if ($method === 'PATCH' && $id) {
            return View::json(['ok' => true, 'use_case' => $useCase, 'resource' => $resource, 'id' => $id, 'message' => 'See HTML form for update flow.'], 202);
        }
        return View::json(['ok' => false, 'error' => 'method_not_allowed'], 405);
    }

    private function summary(string $resource): ResponseInterface
    {
        $summaries = [
            'account_access' => ['module' => 'account_access_and_recovery', 'endpoints' => ['POST /login', 'POST /register', 'POST /reset/request', 'POST /reset/perform', 'POST /logout']],
            'phases' => ['module' => 'conference_phases', 'endpoints' => ['GET /conference_phases', 'PATCH /conference_phases/{id}']],
            'submissions' => ['module' => 'paper_submission', 'endpoints' => ['GET /paper_submission', 'POST /paper_submission']],
            'discovery' => ['module' => 'submission_discovery', 'endpoints' => ['GET /submission_discovery?q=&status=']],
            'manuscript' => ['module' => 'manuscript_access', 'endpoints' => ['GET /manuscript_access/{submission_id}', 'GET /manuscript_access/{submission_id}/{file_id}/download']],
            'reviewer_assignment' => ['module' => 'reviewer_assignment', 'endpoints' => ['GET /reviewer_assignment', 'POST /reviewer_assignment/assign', 'POST /reviewer_assignment/conflict']],
            'reviewing' => ['module' => 'reviewing', 'endpoints' => ['GET /reviewing', 'GET /reviewing/{assignment_id}', 'POST /reviewing/{assignment_id}/submit']],
            'rebuttal' => ['module' => 'rebuttal', 'endpoints' => ['GET /rebuttal', 'POST /rebuttal/submit']],
            'decision_management' => ['module' => 'decision_management', 'endpoints' => ['GET /decision_management', 'POST /decision_management/record']],
            'double_blind' => ['module' => 'double_blind_views', 'endpoints' => ['GET /double_blind_views/{submission_id}', 'POST /double_blind_views/{submission_id}']],
            'exports' => ['module' => 'bulk_exports', 'endpoints' => ['GET /bulk_exports', 'POST /bulk_exports/run', 'GET /bulk_exports/{id}/download']],
            'frontend_errors' => ['module' => 'frontend_api_integration_and_errors', 'endpoints' => ['POST /frontend_api_integration_and_errors/report', 'GET /api/errors/{code}']],
        ];
        return View::json($summaries[$resource] ?? ['ok' => false], 200);
    }
}