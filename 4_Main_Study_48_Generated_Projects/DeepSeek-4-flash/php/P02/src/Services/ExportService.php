<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BulkExportRepository;
use App\Repositories\DecisionRepository;
use App\Repositories\PaperSubmissionRepository;
use App\Repositories\ReviewRepository;

final class ExportService
{
    public function __construct(
        private readonly PaperSubmissionRepository $submissions,
        private readonly ReviewRepository $reviews,
        private readonly DecisionRepository $decisions,
        private readonly BulkExportRepository $exports,
        private readonly FileService $files,
        private readonly PdfService $pdf
    ) {
    }

    /**
     * @return array{path: string, name: string, mime: string}
     */
    public function generate(string $exportType, string $format, int $requestedBy): array
    {
        if (!in_array($exportType, ['submissions', 'reviews', 'decisions'], true)) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['export_type' => 'invalid type']]);
        }
        if (!in_array($format, ['csv', 'pdf'], true)) {
            throw new WorkflowException('validation_error', 422, ['fields' => ['format' => 'invalid format']]);
        }

        $rows = match ($exportType) {
            'submissions' => $this->submissionRows(),
            'reviews' => $this->reviewRows(),
            'decisions' => $this->decisionRows(),
        };
        $headers = array_shift($rows) ?? [];

        $dir = $this->files->exportDirectory();
        $stamp = date('Ymd_His');
        $base = 'export_' . $exportType . '_' . $stamp;

        if ($format === 'csv') {
            $name = $base . '.csv';
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            $handle = fopen($path, 'wb');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
            $mime = 'text/csv';
        } else {
            $name = $base . '.pdf';
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            $pdfRows = array_map(
                static fn (array $r): string => implode(' | ', array_map('strval', $r)),
                $rows
            );
            $content = $this->pdf->render('Conference ' . ucfirst($exportType) . ' export', $pdfRows);
            file_put_contents($path, $content);
            $mime = 'application/pdf';
        }

        $size = filesize($path) ?: 0;
        $this->files->registerFile($name, $path, $mime, $size, 'export');
        $id = $this->exports->insert([
            'export_type' => $exportType,
            'format' => $format,
            'file_path' => $path,
            'requested_by' => $requestedBy,
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        return ['export_id' => $id, 'path' => $path, 'name' => $name, 'mime' => $mime];
    }

    private function submissionRows(): array
    {
        $rows = [['ID', 'Title', 'Abstract', 'Keywords', 'Status', 'Author', 'Created']];
        foreach ($this->submissions->search([], 1000) as $s) {
            $rows[] = [$s['id'], $s['title'], $s['abstract'], $s['keywords'], $s['status'], $s['author_name'], $s['created_at']];
        }
        return $rows;
    }

    private function reviewRows(): array
    {
        $rows = [['ID', 'Submission', 'Reviewer', 'Score', 'Confidence', 'Status', 'Created']];
        foreach ($this->submissions->search([], 1000) as $s) {
            foreach ($this->reviews->forSubmission((int) $s['id']) as $r) {
                $rows[] = [$r['id'], $s['title'], $r['reviewer_name'], $r['score'], $r['confidence'], $r['status'], $r['created_at']];
            }
        }
        return $rows;
    }

    private function decisionRows(): array
    {
        $rows = [['ID', 'Submission', 'Decision', 'Decided by', 'Decided at', 'Notification']];
        foreach ($this->decisions->withDetails() as $d) {
            $rows[] = [$d['id'], $d['submission_title'], $d['decision'], $d['decided_by_name'], $d['decided_at'], $d['notification_text']];
        }
        return $rows;
    }
}
