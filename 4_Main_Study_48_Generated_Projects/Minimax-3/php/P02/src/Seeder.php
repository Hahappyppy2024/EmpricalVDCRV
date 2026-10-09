<?php
declare(strict_types=1);

namespace App;

use PDO;

final class Seeder
{
    public static function seed(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');
        $password = password_hash('Password123!', PASSWORD_DEFAULT);

        $users = [
            ['admin', 'admin@conference.local', 'Admin User', 'admin', 'Conference Organizers'],
            ['chair1', 'chair@conference.local', 'Dr. Chair', 'chair,reviewer', 'Program Committee'],
            ['reviewer1', 'reviewer1@conference.local', 'Dr. Reviewer One', 'reviewer', 'University A'],
            ['reviewer2', 'reviewer2@conference.local', 'Dr. Reviewer Two', 'reviewer', 'University B'],
            ['reviewer3', 'reviewer3@conference.local', 'Dr. Reviewer Three', 'reviewer', 'University C'],
            ['author1', 'author1@conference.local', 'Author One', 'author', 'Research Lab 1'],
            ['author2', 'author2@conference.local', 'Author Two', 'author', 'Research Lab 2'],
            ['author3', 'author3@conference.local', 'Author Three', 'author', 'Research Lab 3'],
        ];

        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, roles, affiliation, bio, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $userIds = [];
        foreach ($users as $u) {
            $stmt->execute([$u[0], $u[1], $password, $u[2], $u[3], $u[4] ?? '', '', $now]);
            $userIds[$u[0]] = (int)$pdo->lastInsertId();
        }

        $phases = [
            ['submission', 'Submission Phase', '-30 days', '+30 days', 'open', 'Authors can submit papers.'],
            ['review', 'Review Phase', '+31 days', '+90 days', 'scheduled', 'Reviewers submit reviews.'],
            ['rebuttal', 'Rebuttal Phase', '+91 days', '+105 days', 'scheduled', 'Authors respond to reviewers.'],
            ['decision', 'Decision Phase', '+106 days', '+120 days', 'scheduled', 'Chair records decisions.'],
        ];
        $stmt = $pdo->prepare('INSERT INTO conference_phases (phase_key, label, start_date, end_date, status, description, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($phases as $p) {
            $stmt->execute([...$p, $now]);
        }

        $uploadsDir = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }

        $submissions = [
            [
                'author' => 'author1',
                'title' => 'Scalable Distributed Systems for Edge Computing',
                'abstract' => 'This paper presents a scalable architecture for distributed systems targeting edge computing environments.',
                'keywords' => 'distributed systems, edge computing, scalability',
                'topic' => 'Systems',
            ],
            [
                'author' => 'author2',
                'title' => 'Neural Approaches to Code Generation',
                'abstract' => 'We propose a neural approach to code generation using transformer-based models.',
                'keywords' => 'neural networks, code generation, transformers',
                'topic' => 'AI/ML',
            ],
            [
                'author' => 'author3',
                'title' => 'Privacy-Preserving Data Sharing Protocols',
                'abstract' => 'Novel protocols enabling privacy-preserving data sharing across organizations.',
                'keywords' => 'privacy, cryptography, data sharing',
                'topic' => 'Security',
            ],
        ];

        $submissionIds = [];
        foreach ($submissions as $s) {
            $authorId = $userIds[$s['author']];
            $pdfName = 'manuscript_' . $s['author'] . '.pdf';
            $pdfPath = $uploadsDir . '/' . $pdfName;
            if (!file_exists($pdfPath)) {
                file_put_contents($pdfPath, "%PDF-1.4\n% Synthetic manuscript for {$s['title']}\n%EOF\n");
            }
            $relPath = './storage/uploads/' . $pdfName;
            $stmt = $pdo->prepare('INSERT INTO paper_submissions (author_id, title, abstract, keywords, topic, pdf_path, status, submission_phase, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$authorId, $s['title'], $s['abstract'], $s['keywords'], $s['topic'], $relPath, 'under_review', 'review', $now, $now]);
            $subId = (int)$pdo->lastInsertId();
            $submissionIds[$s['author']] = $subId;

            $fstmt = $pdo->prepare('INSERT INTO stored_files (submission_id, owner_id, original_name, stored_path, mime_type, size_bytes, kind, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $fstmt->execute([$subId, $authorId, $pdfName, $relPath, 'application/pdf', filesize($pdfPath), 'manuscript', $now]);
        }

        $assignStmt = $pdo->prepare('INSERT INTO reviewer_assignments (submission_id, reviewer_id, assigned_by, status, due_date, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $fileStmt = $pdo->prepare('SELECT id FROM stored_files WHERE submission_id = ? AND kind = ?');
        $reviewStmt = $pdo->prepare('INSERT INTO reviews (assignment_id, reviewer_id, submission_id, score, confidence, comments_to_author, comments_to_chair, recommendation, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        foreach ($submissionIds as $author => $subId) {
            $reviewers = array_filter(['reviewer1', 'reviewer2', 'reviewer3'], fn($r) => true);
            $i = 0;
            foreach ($reviewers as $rev) {
                $revId = $userIds[$rev];
                $dueDate = date('Y-m-d H:i:s', strtotime('+30 days'));
                $assignStmt->execute([$subId, $revId, $userIds['chair1'], 'completed', $dueDate, $now]);
                $assignId = (int)$pdo->lastInsertId();
                $score = 5 + ($i % 2);
                $confidence = 4 + ($i % 2);
                $rec = $i === 0 ? 'accept' : ($i === 1 ? 'weak_accept' : 'weak_reject');
                $commentsToAuthor = "Overall the paper is solid. Section 3 could use more detail about the methodology. Authors should clarify the experimental setup in Section 4.";
                $commentsToChair = "Reviewer notes confidence. Suggest acceptance after minor revisions.";
                $reviewStmt->execute([$assignId, $revId, $subId, $score, $confidence, $commentsToAuthor, $commentsToChair, $rec, 'submitted', $now, $now]);
                $i++;
            }
        }

        $rebuttalStmt = $pdo->prepare('INSERT INTO rebuttals (submission_id, author_id, body, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
        $rebuttalStmt->execute([$submissionIds['author1'], $userIds['author1'], 'We thank the reviewers for their constructive feedback. We have addressed the methodology concerns in Section 3 and clarified the experimental setup in Section 4. The updated version is available.', $now, $now]);

        $decisionStmt = $pdo->prepare('INSERT INTO decisions (submission_id, chair_id, decision, summary, notification, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $decisionStmt->execute([$submissionIds['author2'], $userIds['chair1'], 'accept', 'Strong reviews, recommend acceptance after minor revisions.', 'Congratulations! Your paper has been accepted to Synthetic Conference 2026. Detailed review comments will follow.', $now]);

        $blindStmt = $pdo->prepare('INSERT OR IGNORE INTO double_blind_preferences (submission_id, user_id, view_mode) VALUES (?, ?, ?)');
        foreach ($submissionIds as $subId) {
            foreach (['reviewer1', 'reviewer2', 'reviewer3'] as $rev) {
                $blindStmt->execute([$subId, $userIds[$rev], 'blind']);
            }
            $blindStmt->execute([$subId, $userIds['chair1'], 'unblinded']);
        }

        $auditStmt = $pdo->prepare('INSERT INTO audit_events (actor_id, action, target_type, target_id, details, created_at) VALUES (?, ?, ?, ?, ?, ?)');
        $auditStmt->execute([$userIds['chair1'], 'phase.update', 'phase', 'submission', 'Submission phase opened.', $now]);
        $auditStmt->execute([$userIds['chair1'], 'assign.reviewer', 'submission', (string)$submissionIds['author1'], 'Assigned 3 reviewers.', $now]);
        $auditStmt->execute([$userIds['chair1'], 'decision.record', 'submission', (string)$submissionIds['author2'], 'Accept recorded.', $now]);

        $errorStmt = $pdo->prepare('INSERT INTO frontend_error_logs (user_id, code, severity, source, message, details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $errorStmt->execute([null, 'VAL-001', 'warning', 'submission_form', 'Required field missing during initial seed.', 'Triggered during initial load.', $now]);
    }
}