<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\AuditRepository;
use LMS\Repository\ReportRepository;
use LMS\Service\ExportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;

final class BulkCourseReportController
{
    public function __construct(
        private View $view,
        private ExportService $exporter,
        private ReportRepository $reports,
        private AuditRepository $audit,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->currentUser();
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $format = (string)($body['format'] ?? 'csv');
            if (!in_array($format, ['csv', 'pdf'], true)) {
                $format = 'csv';
            }
            $filters = [
                'category' => trim((string)($body['category'] ?? '')),
                'semester' => trim((string)($body['semester'] ?? '')),
                'visibility' => trim((string)($body['visibility'] ?? '')),
            ];
            $rows = $this->exporter->courseSummaryRows();
            if ($filters['category'] !== '') {
                $rows = array_values(array_filter($rows, fn($r) => $r['category'] === $filters['category']));
            }
            if ($filters['semester'] !== '') {
                $rows = array_values(array_filter($rows, fn($r) => $r['semester'] === $filters['semester']));
            }
            if ($filters['visibility'] !== '') {
                $rows = array_values(array_filter($rows, fn($r) => $r['visibility'] === $filters['visibility']));
            }
            $headers = ['code','title','category','semester','visibility','instructor','enrolled_count','material_count','assignment_count','quiz_count'];
            $fname = sprintf('bulk-report-%s.%s', date('Ymd-His'), $format);
            $path = $format === 'pdf'
                ? $this->exporter->writePdfReport($fname, 'Bulk Course Report', $rows, $headers)
                : $this->exporter->writeCsv($fname, $rows, $headers);
            $reportId = $this->reports->record([
                'requested_by' => (int)$user['id'],
                'scope' => 'bulk_course',
                'filters' => $filters,
                'file_path' => basename($path),
                'summary' => count($rows) . ' courses',
            ]);
            $this->audit->record((int)$user['id'], 'report.bulk', 'system', null, ['format' => $format, 'rows' => count($rows), 'filters' => $filters]);
            return $response->withHeader('Location', '/reports/' . $reportId . '/download')->withStatus(302);
        }
        return $this->view->render($response, 'bulk_report.php', [
            'user' => $user,
            'recent' => $this->reports->listRecent(20),
            'csrf' => $this->csrf->token(),
        ]);
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $row = $this->reports->find((int)$args['id']);
        if (!$row) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Report not found.', 'status' => 404]);
        }
        $dir = $this->exporter->exportDir();
        $path = $dir . DIRECTORY_SEPARATOR . $row['file_path'];
        if (!is_file($path)) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'File missing.', 'status' => 404]);
        }
        $mime = str_ends_with($path, '.pdf') ? 'text/html' : 'text/csv';
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"')
            ->withHeader('Content-Length', (string)filesize($path))
            ->withBody(new Stream(fopen($path, 'rb')));
    }
}
