<?php
declare(strict_types=1);

namespace LMS\Service;

use PDO;

/**
 * Deterministic CSV / PDF-lite / JSON exporter used by Grade export and
 * Bulk course report. PDFs are produced locally as a tiny one-page HTML
 * report wrapped in a deterministic header — no external libraries.
 */
final class ExportService
{
    public function __construct(private PDO $pdo, private array $config) {}

    public function exportDir(): string
    {
        $dir = $this->config['storage']['exports'];
        if (!str_starts_with($dir, DIRECTORY_SEPARATOR) && !preg_match('#^[A-Za-z]:[\\\\/]#', $dir)) {
            $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $dir;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        return $dir;
    }

    public function writeCsv(string $filename, array $rows, array $headers): string
    {
        $path = $this->exportDir() . DIRECTORY_SEPARATOR . $filename;
        $fp = fopen($path, 'w');
        if (!$fp) {
            throw new \RuntimeException('Unable to open export file');
        }
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($fp, $line);
        }
        fclose($fp);
        return $path;
    }

    public function writeJson(string $filename, array $payload): string
    {
        $path = $this->exportDir() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $path;
    }

    /**
     * Writes a self-contained HTML report that can be saved as PDF by the
     * browser via the print dialog. This keeps the implementation
     * deterministic and offline (no wkhtmltopdf / puppeteer dependency).
     */
    public function writePdfReport(string $filename, string $title, array $rows, array $headers): string
    {
        $path = $this->exportDir() . DIRECTORY_SEPARATOR . $filename;
        $html = "<!doctype html><html><head><meta charset=\"utf-8\"><title>"
            . htmlspecialchars($title) . "</title>";
        $html .= '<style>body{font-family:Arial,Helvetica,sans-serif;margin:24px;color:#111}'
            . 'h1{font-size:20px;margin-bottom:4px}'
            . '.meta{color:#555;font-size:12px;margin-bottom:18px}'
            . 'table{border-collapse:collapse;width:100%;font-size:12px}'
            . 'th,td{border:1px solid #999;padding:6px 8px;text-align:left}'
            . 'th{background:#eee}</style></head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<div class="meta">Generated at ' . date('Y-m-d H:i:s') . ' — P01 LMS (offline)</div>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars((string)$h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $h) {
                $html .= '<td>' . htmlspecialchars((string)($row[$h] ?? '')) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        file_put_contents($path, $html);
        return $path;
    }

    public function gradeRows(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.full_name AS student, u.username,
                    g.item_label, g.item_type, g.score, g.max_score,
                    ROUND((g.score * 100.0) / NULLIF(g.max_score, 0), 1) AS pct,
                    g.feedback, g.graded_at
             FROM grades g
             JOIN users u ON u.id = g.student_id
             WHERE g.course_id = ?
             ORDER BY u.full_name, g.graded_at DESC'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function rosterRows(int $courseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.full_name AS student, u.username, u.email,
                    e.role, e.status, e.created_at AS enrolled_at
             FROM enrollments e JOIN users u ON u.id = e.user_id
             WHERE e.course_id = ? ORDER BY u.full_name'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public function courseSummaryRows(): array
    {
        return $this->pdo->query(
            'SELECT c.code, c.title, c.category, c.semester, c.visibility,
                    u.full_name AS instructor,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id AND e.status = \'active\') AS enrolled_count,
                    (SELECT COUNT(*) FROM materials m WHERE m.course_id = c.id) AS material_count,
                    (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) AS assignment_count,
                    (SELECT COUNT(*) FROM quizzes q WHERE q.course_id = c.id) AS quiz_count
             FROM courses c JOIN users u ON u.id = c.instructor_id
             ORDER BY c.code'
        )->fetchAll();
    }
}
