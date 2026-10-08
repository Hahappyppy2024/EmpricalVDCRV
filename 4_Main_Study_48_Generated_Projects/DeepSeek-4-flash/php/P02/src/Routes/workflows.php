<?php

declare(strict_types=1);

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
use App\Services\Workflows\RealtimeEventsService;

return [
    'realtime_events' => [
        'uc' => 'REAL',
        'title' => 'Real-time events',
        'page' => null,
        'service' => RealtimeEventsService::class,
    ],
    'account_access_and_recovery' => [
        'uc' => 'CONF-01',
        'title' => 'Account access and recovery',
        'page' => 'pages/account_access',
        'service' => AccountAccessRecoveryService::class,
    ],
    'conference_phases' => [
        'uc' => 'CONF-02',
        'title' => 'Conference phases',
        'page' => 'pages/conference_phases',
        'service' => ConferencePhasesService::class,
    ],
    'paper_submission' => [
        'uc' => 'CONF-03',
        'title' => 'Paper submission',
        'page' => 'pages/paper_submission',
        'service' => PaperSubmissionService::class,
    ],
    'submission_discovery' => [
        'uc' => 'CONF-04',
        'title' => 'Submission discovery',
        'page' => 'pages/submission_discovery',
        'service' => SubmissionDiscoveryService::class,
    ],
    'manuscript_access' => [
        'uc' => 'CONF-05',
        'title' => 'Manuscript access',
        'page' => 'pages/manuscript_access',
        'service' => ManuscriptAccessService::class,
    ],
    'reviewer_assignment' => [
        'uc' => 'CONF-06',
        'title' => 'Reviewer assignment',
        'page' => 'pages/reviewer_assignment',
        'service' => ReviewerAssignmentService::class,
    ],
    'reviewing' => [
        'uc' => 'CONF-07',
        'title' => 'Reviewing',
        'page' => 'pages/reviewing',
        'service' => ReviewingService::class,
    ],
    'rebuttal' => [
        'uc' => 'CONF-08',
        'title' => 'Rebuttal',
        'page' => 'pages/rebuttal',
        'service' => RebuttalService::class,
    ],
    'decision_management' => [
        'uc' => 'CONF-09',
        'title' => 'Decision management',
        'page' => 'pages/decision_management',
        'service' => DecisionManagementService::class,
    ],
    'double_blind_views' => [
        'uc' => 'CONF-10',
        'title' => 'Double-blind views',
        'page' => 'pages/double_blind_views',
        'service' => DoubleBlindViewsService::class,
    ],
    'bulk_exports' => [
        'uc' => 'CONF-11',
        'title' => 'Bulk exports',
        'page' => 'pages/bulk_exports',
        'service' => BulkExportsService::class,
    ],
    'frontend_api_integration_and_errors' => [
        'uc' => 'CONF-12',
        'title' => 'Frontend API integration and errors',
        'page' => 'pages/frontend_api',
        'service' => FrontendApiService::class,
    ],
];
