<?php

declare(strict_types=1);

use App\Services\PdfService;

/**
 * Deterministic seed fixtures for the P02 Conference Review System.
 * Run via: php bin/reset_db.php   (schema + seed)   or   php database/seed.php
 *
 * @param string $rootDir Project root directory.
 */
function seed_database(string $rootDir): void
{
    $dbPath = $rootDir . '/database/conference.sqlite';
    $storage = $rootDir . '/storage';
    $manuscriptDir = $storage . '/manuscripts';
    $exportDir = $storage . '/exports';

    if (!is_dir($manuscriptDir)) {
        mkdir($manuscriptDir, 0777, true);
    }
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0777, true);
    }

    $pdo = App\Repositories\Database::connect($dbPath);
    $pdo->exec('DELETE FROM realtime_events');
    $pdo->exec('DELETE FROM frontend_api_integration_and_errors');
    $pdo->exec('DELETE FROM manuscript_access');
    $pdo->exec('DELETE FROM submission_discovery');
    $pdo->exec('DELETE FROM account_access_and_recovery');
    $pdo->exec('DELETE FROM bulk_exports');
    $pdo->exec('DELETE FROM double_blind_views');
    $pdo->exec('DELETE FROM audit_events');
    $pdo->exec('DELETE FROM decisions');
    $pdo->exec('DELETE FROM rebuttals');
    $pdo->exec('DELETE FROM reviews');
    $pdo->exec('DELETE FROM reviewer_assignments');
    $pdo->exec('DELETE FROM stored_files');
    $pdo->exec('DELETE FROM paper_submissions');
    $pdo->exec('DELETE FROM conference_phases');
    $pdo->exec('DELETE FROM sessions');
    $pdo->exec('DELETE FROM users');
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('users','sessions','conference_phases','paper_submissions','stored_files','reviewer_assignments','reviews','rebuttals','decisions','audit_events','double_blind_views','bulk_exports','account_access_and_recovery','submission_discovery','manuscript_access','frontend_api_integration_and_errors','realtime_events')");

    $insert = static function (string $table, array $data) use ($pdo): int {
        $cols = array_keys($data);
        $st = $pdo->prepare(
            'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES ('
            . implode(', ', array_map(static fn (string $c): string => ':' . $c, $cols)) . ')'
        );
        $st->execute($data);
        return (int) $pdo->lastInsertId();
    };

    // --- Users ---------------------------------------------------------------
    $users = [
        ['name' => 'Ada Admin', 'email' => 'admin@example.com', 'password' => 'Admin@123', 'role' => 'admin'],
        ['name' => 'Chris Chair', 'email' => 'chair@example.com', 'password' => 'Chair@123', 'role' => 'chair'],
        ['name' => 'Alice Author', 'email' => 'author@example.com', 'password' => 'Author@123', 'role' => 'author'],
        ['name' => 'Bob Author', 'email' => 'author2@example.com', 'password' => 'Author2@123', 'role' => 'author'],
        ['name' => 'Rita Reviewer', 'email' => 'reviewer@example.com', 'password' => 'Reviewer@123', 'role' => 'reviewer'],
        ['name' => 'Ravi Reviewer', 'email' => 'reviewer2@example.com', 'password' => 'Reviewer2@123', 'role' => 'reviewer'],
    ];
    $userIds = [];
    foreach ($users as $u) {
        $id = $insert('users', [
            'name' => $u['name'],
            'email' => $u['email'],
            'password_hash' => password_hash($u['password'], PASSWORD_DEFAULT),
            'role' => $u['role'],
        ]);
        $userIds[$u['email']] = $id;
    }

    $adminId = $userIds['admin@example.com'];
    $chairId = $userIds['chair@example.com'];
    $author1 = $userIds['author@example.com'];
    $author2 = $userIds['author2@example.com'];
    $reviewer1 = $userIds['reviewer@example.com'];
    $reviewer2 = $userIds['reviewer2@example.com'];

    // --- Conference phases ----------------------------------------------------
    $insert('conference_phases', ['name' => 'submission', 'status' => 'open', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
    $insert('conference_phases', ['name' => 'review', 'status' => 'open', 'start_date' => '2026-02-01', 'end_date' => '2026-12-31']);
    $insert('conference_phases', ['name' => 'rebuttal', 'status' => 'open', 'start_date' => '2026-03-01', 'end_date' => '2026-12-31']);
    $insert('conference_phases', ['name' => 'decision', 'status' => 'open', 'start_date' => '2026-04-01', 'end_date' => '2026-12-31']);

    // --- Submissions -----------------------------------------------------------
    $submissions = [
        [
            'author_id' => $author1, 'title' => 'Scalable Fault-Tolerant Distributed Consensus',
            'abstract' => 'We present a consensus protocol that tolerates partial network partitions while preserving throughput.',
            'keywords' => 'distributed systems, consensus, fault tolerance', 'status' => 'under_review',
        ],
        [
            'author_id' => $author1, 'title' => 'Energy-Aware Scheduling for Edge Computing',
            'abstract' => 'An energy-aware scheduler that reduces power consumption of edge clusters by up to 30 percent.',
            'keywords' => 'edge computing, scheduling, energy', 'status' => 'in_rebuttal',
        ],
        [
            'author_id' => $author2, 'title' => 'Privacy-Preserving Federated Learning',
            'abstract' => 'A federated learning framework with differential privacy guarantees for heterogeneous clients.',
            'keywords' => 'federated learning, privacy, differential privacy', 'status' => 'under_review',
        ],
        [
            'author_id' => $author1, 'title' => 'Quantum-Inspired Optimization Heuristics',
            'abstract' => 'Quantum-inspired heuristics applied to combinatorial optimization benchmarks with competitive results.',
            'keywords' => 'optimization, heuristics, quantum', 'status' => 'rejected',
        ],
        [
            'author_id' => $author2, 'title' => 'Semantic Code Search with Transformer Embeddings',
            'abstract' => 'We train transformer embeddings on source code to enable natural-language code search at repository scale.',
            'keywords' => 'code search, transformers, embeddings', 'status' => 'accepted',
        ],
    ];
    $submissionIds = [];
    $pdf = new PdfService();
    foreach ($submissions as $s) {
        $id = $insert('paper_submissions', [
            'author_id' => $s['author_id'],
            'title' => $s['title'],
            'abstract' => $s['abstract'],
            'keywords' => $s['keywords'],
            'manuscript_path' => null,
            'status' => $s['status'],
        ]);
        $submissionIds[$s['title']] = $id;
        $path = $manuscriptDir . '/submission_' . $id . '.pdf';
        file_put_contents($path, $pdf->render($s['title'], [
            'Abstract', $s['abstract'], '', 'Keywords: ' . $s['keywords'], '',
            'This is a deterministic seed manuscript for the Conference Review System.',
        ]));
        $size = filesize($path) ?: 0;
        $insert('stored_files', [
            'name' => 'submission_' . $id . '.pdf',
            'path' => $path,
            'mime' => 'application/pdf',
            'size' => $size,
            'kind' => 'manuscript',
            'submission_id' => $id,
        ]);
        $pdo->prepare('UPDATE paper_submissions SET manuscript_path = :p WHERE id = :id')
            ->execute(['p' => $path, 'id' => $id]);
    }

    // --- Reviewer assignments --------------------------------------------------
    $sub1 = $submissionIds['Scalable Fault-Tolerant Distributed Consensus'];
    $sub2 = $submissionIds['Energy-Aware Scheduling for Edge Computing'];
    $sub3 = $submissionIds['Privacy-Preserving Federated Learning'];
    $sub4 = $submissionIds['Quantum-Inspired Optimization Heuristics'];
    $sub5 = $submissionIds['Semantic Code Search with Transformer Embeddings'];

    $assignments = [
        ['submission_id' => $sub1, 'reviewer_id' => $reviewer1, 'assigned_by' => $chairId, 'status' => 'pending', 'conflict' => 0, 'note' => ''],
        ['submission_id' => $sub1, 'reviewer_id' => $reviewer2, 'assigned_by' => $chairId, 'status' => 'accepted', 'conflict' => 0, 'note' => ''],
        ['submission_id' => $sub2, 'reviewer_id' => $reviewer1, 'assigned_by' => $chairId, 'status' => 'accepted', 'conflict' => 0, 'note' => ''],
        ['submission_id' => $sub3, 'reviewer_id' => $reviewer2, 'assigned_by' => $chairId, 'status' => 'pending', 'conflict' => 0, 'note' => ''],
        ['submission_id' => $sub4, 'reviewer_id' => $reviewer1, 'assigned_by' => $chairId, 'status' => 'declined', 'conflict' => 1, 'note' => 'Former collaborator'],
        ['submission_id' => $sub5, 'reviewer_id' => $reviewer1, 'assigned_by' => $chairId, 'status' => 'accepted', 'conflict' => 0, 'note' => ''],
    ];
    $assignmentIds = [];
    foreach ($assignments as $a) {
        $id = $insert('reviewer_assignments', [
            'submission_id' => $a['submission_id'],
            'reviewer_id' => $a['reviewer_id'],
            'assigned_by' => $a['assigned_by'],
            'status' => $a['status'],
            'conflict_of_interest' => $a['conflict'],
            'conflict_note' => $a['note'],
        ]);
        $assignmentIds[$a['submission_id'] . ':' . $a['reviewer_id']] = $id;
    }

    // --- Reviews ----------------------------------------------------------------
    $insert('reviews', [
        'submission_id' => $sub1, 'reviewer_id' => $reviewer2,
        'assignment_id' => $assignmentIds[$sub1 . ':' . $reviewer2],
        'score' => 8, 'confidence' => 4, 'comments' => 'Strong contribution with minor experimental gaps.',
        'private_notes' => 'Ask for ablation studies.', 'status' => 'submitted',
    ]);
    $insert('reviews', [
        'submission_id' => $sub1, 'reviewer_id' => $reviewer1,
        'assignment_id' => $assignmentIds[$sub1 . ':' . $reviewer1],
        'score' => null, 'confidence' => null, 'comments' => '',
        'private_notes' => '', 'status' => 'draft',
    ]);
    $insert('reviews', [
        'submission_id' => $sub2, 'reviewer_id' => $reviewer1,
        'assignment_id' => $assignmentIds[$sub2 . ':' . $reviewer1],
        'score' => 7, 'confidence' => 3, 'comments' => 'Useful work; reproducibility section needs expansion.',
        'private_notes' => '', 'status' => 'submitted',
    ]);
    $insert('reviews', [
        'submission_id' => $sub5, 'reviewer_id' => $reviewer1,
        'assignment_id' => $assignmentIds[$sub5 . ':' . $reviewer1],
        'score' => 9, 'confidence' => 5, 'comments' => 'Excellent results and clear presentation.',
        'private_notes' => 'Recommend acceptance.', 'status' => 'submitted',
    ]);

    // --- Rebuttals ---------------------------------------------------------------
    $insert('rebuttals', [
        'submission_id' => $sub2, 'author_id' => $author1,
        'text' => 'We have expanded the reproducibility section and added a public benchmark harness.',
        'status' => 'submitted',
    ]);
    $insert('rebuttals', [
        'submission_id' => $sub4, 'author_id' => $author1,
        'text' => 'We will provide the optimization benchmark suite and configuration files upon request.',
        'status' => 'pending',
    ]);

    // --- Decisions ----------------------------------------------------------------
    $insert('decisions', [
        'submission_id' => $sub4, 'decision' => 'reject',
        'notification_text' => 'Thank you for your contribution; the committee decided the work is out of scope.',
        'decided_by' => $chairId,
    ]);
    $insert('decisions', [
        'submission_id' => $sub5, 'decision' => 'accept',
        'notification_text' => 'Congratulations, your paper has been accepted for presentation.',
        'decided_by' => $chairId,
    ]);

    // --- Audit events ---------------------------------------------------------------
    $insert('audit_events', ['user_id' => $chairId, 'action' => 'reviewer_assigned', 'entity' => 'reviewer_assignment', 'entity_id' => 1, 'detail' => 'assigned reviewer to submission 1']);
    $insert('audit_events', ['user_id' => $chairId, 'action' => 'decision_recorded', 'entity' => 'decision', 'entity_id' => 1, 'detail' => 'reject for submission 4']);
    $insert('audit_events', ['user_id' => $chairId, 'action' => 'decision_recorded', 'entity' => 'decision', 'entity_id' => 2, 'detail' => 'accept for submission 5']);

    // --- Double-blind views ------------------------------------------------------------
    $insert('double_blind_views', ['submission_id' => $sub1, 'viewer_id' => $reviewer2, 'viewer_role' => 'reviewer', 'blind' => 1]);
    $insert('double_blind_views', ['submission_id' => $sub2, 'viewer_id' => $author1, 'viewer_role' => 'author', 'blind' => 0]);
    $insert('double_blind_views', ['submission_id' => $sub3, 'viewer_id' => $chairId, 'viewer_role' => 'chair', 'blind' => 0]);

    // --- Bulk exports -------------------------------------------------------------------
    $exportName = 'seed_submissions.csv';
    $exportPath = $exportDir . '/' . $exportName;
    $handle = fopen($exportPath, 'wb');
    fputcsv($handle, ['ID', 'Title', 'Abstract', 'Keywords', 'Status', 'Created']);
    foreach ($submissions as $i => $s) {
        fputcsv($handle, [$i + 1, $s['title'], $s['abstract'], $s['keywords'], $s['status'], '2026-08-01']);
    }
    fclose($handle);
    $insert('stored_files', [
        'name' => $exportName, 'path' => $exportPath, 'mime' => 'text/csv',
        'size' => filesize($exportPath) ?: 0, 'kind' => 'export', 'submission_id' => null,
    ]);
    $insert('bulk_exports', [
        'export_type' => 'submissions', 'format' => 'csv', 'file_path' => $exportPath,
        'requested_by' => $chairId, 'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'),
    ]);

    // --- Account access and recovery -------------------------------------------------------
    $insert('account_access_and_recovery', [
        'user_id' => $author1, 'kind' => 'reset_request', 'token' => hash('sha256', 'seed-reset-token-0001'),
        'token_expires_at' => date('Y-m-d H:i:s', time() + 3600), 'payload' => 'seed reset request', 'status' => 'used',
    ]);
    $insert('account_access_and_recovery', [
        'user_id' => $chairId, 'kind' => 'login', 'token' => null, 'token_expires_at' => null,
        'payload' => 'seed sign-in', 'status' => 'completed',
    ]);
    $insert('account_access_and_recovery', [
        'user_id' => $adminId, 'kind' => 'recovery', 'token' => null, 'token_expires_at' => null,
        'payload' => 'seed account created', 'status' => 'completed',
    ]);

    // --- Discovery log -----------------------------------------------------------------------
    $insert('submission_discovery', [
        'user_id' => $author1, 'search_query' => 'learning', 'status_filter' => '', 'result_count' => 1,
    ]);

    // --- Frontend API error reports --------------------------------------------------------------
    $insert('frontend_api_integration_and_errors', [
        'user_id' => $author1, 'endpoint' => '/api/conf/paper_submission', 'error_code' => 'validation_error',
        'error_message' => 'title is required', 'detail' => 'submitted an empty title', 'status' => 'acknowledged',
    ]);

    // --- Real-time events -------------------------------------------------------------------------
    $insert('realtime_events', ['type' => 'paper.submitted', 'payload' => json_encode(['submission_id' => $sub1])]);
    $insert('realtime_events', ['type' => 'review.submitted', 'payload' => json_encode(['review_id' => 1, 'submission_id' => $sub1])]);
    $insert('realtime_events', ['type' => 'decision.recorded', 'payload' => json_encode(['decision_id' => 2, 'submission_id' => $sub5, 'decision' => 'accept'])]);

    echo "Seed complete: 6 users, 4 phases, 5 submissions, 6 assignments, 4 reviews, 2 rebuttals, 2 decisions, 1 export.\n";
}
