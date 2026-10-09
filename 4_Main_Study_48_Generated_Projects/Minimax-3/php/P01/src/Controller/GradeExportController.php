<?php
declare(strict_types=1);

namespace LMS\Controller;

use LMS\Auth\AuthService;
use LMS\Http\Csrf;
use LMS\Http\Session;
use LMS\Http\View;
use LMS\Repository\AuditRepository;
use LMS\Repository\CourseRepository;
use LMS\Repository\ExportRepository;
use LMS\Service\ExportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Stream;

final class GradeExportController
{
    public function __construct(
        private View $view,
        private CourseRepository $courses,
        private ExportRepository $exports,
        private ExportService $exporter,
        private AuditRepository $audit,
        private AuthService $auth,
        private Session $session,
        private Csrf $csrf
    ) {}

    public function export(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $course = $this->courses->find((int)$args['id']);
        if (!$course || !in_array($user['role'], ['instructor', 'admin'], true)) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        if (strtoupper($request->getMethod()) === 'POST') {
            $body = (array)$request->getParsedBody();
            $format = (string)($body['format'] ?? 'csv');
            if (!in_array($format, ['csv', 'pdf'], true)) {
                $format = 'csv';
            }
            $rows = $this->exporter->gradeRows((int)$course['id']);
            $headers = ['student','username','item_label','item_type','score','max_score','pct','feedback','graded_at'];
            $fname = sprintf('grade-export-%s-%s.%s', $course['code'], date('Ymd-His'), $format);
            $path = $format === 'pdf'
                ? $this->exporter->writePdfReport($fname, 'Grade Export — ' . $course['title'], $rows, $headers)
                : $this->exporter->writeCsv($fname, $rows, $headers);
            $exportId = $this->exports->record([
                'course_id' => (int)$course['id'],
                'requested_by' => (int)$user['id'],
                'format' => $format,
                'status' => 'ready',
                'file_path' => basename($path),
                'summary' => count($rows) . ' grade records',
            ]);
            $this->audit->record((int)$user['id'], 'grade.export', 'course', (int)$course['id'], ['format' => $format, 'rows' => count($rows)]);
            return $response->withHeader('Location', '/exports/' . $exportId . '/download')->withStatus(302);
        }
        return $this->view->render($response, 'grade_export.php', [
            'course' => $course,
            'csrf' => $this->csrf->token(),
            'user' => $user,
        ]);
    }

    public function download(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $row = $this->exports->find((int)$args['id']);
        if (!$row) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'Export not found.', 'status' => 404]);
        }
        if (!in_array($user['role'], ['instructor', 'admin'], true)) {
            return $this->view->render($response->withStatus(403), 'error.php', ['error' => 'Forbidden.', 'status' => 403]);
        }
        $dir = $this->exporter->exportDir();
        $path = $dir . DIRECTORY_SEPARATOR . $row['file_path'];
        if (!is_file($path)) {
            return $this->view->render($response->withStatus(404), 'error.php', ['error' => 'File missing.', 'status' => 404]);
        }
        $mime = $row['format'] === 'csv' ? 'text/csv' : 'text/html';
        return $response
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"')
            ->withHeader('Content-Length', (string)filesize($path))
            ->withBody(new Stream(fopen($path, 'rb')));
    }

    public function history(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->auth->currentUser();
        $rows = $this->exports->listRecent(50);
        return $this->view->render($response, 'exports_history.php', [
            'rows' => $rows,
            'user' => $user,
        ]);
    }
}
