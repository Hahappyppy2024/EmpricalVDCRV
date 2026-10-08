<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Minimal, deterministic, dependency-free PDF document generator used as the
 * local adapter for "PDF export". Produces a single-page text report.
 */
final class PdfService
{
    public function render(string $title, array $rows): string
    {
        $content = "BT /F1 16 Tf 40 750 Td (" . $this->escape($title) . ") Tj ET\n";
        $y = 720;
        foreach ($rows as $row) {
            if ($y < 40) {
                break;
            }
            $text = is_array($row) ? implode(' | ', array_map('strval', $row)) : (string) $row;
            $content .= "BT /F1 10 Tf 40 " . $y . " Td (" . $this->escape($text) . ") Tj ET\n";
            $y -= 16;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R'
                . ' /Resources << /Font << /F1 5 0 R >> >> >>',
            4 => '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        return $this->build($objects);
    }

    private function build(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF\n";
        return $pdf;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
