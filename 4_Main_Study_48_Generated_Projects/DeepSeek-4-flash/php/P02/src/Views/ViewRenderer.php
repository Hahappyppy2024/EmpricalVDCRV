<?php

declare(strict_types=1);

namespace App\Views;

use Psr\Http\Message\ResponseInterface;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function status_label(string $status): string
{
    $labels = [
        'open' => 'Open',
        'closed' => 'Closed',
        'submitted' => 'Submitted',
        'under_review' => 'Under review',
        'in_rebuttal' => 'In rebuttal',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'decided' => 'Decided',
        'pending' => 'Pending',
        'accepted2' => 'Accepted',
        'draft' => 'Draft',
        'completed' => 'Completed',
        'used' => 'Used',
        'expired' => 'Expired',
        'reported' => 'Reported',
        'acknowledged' => 'Acknowledged',
        'resolved' => 'Resolved',
        'declined' => 'Declined',
        'failed' => 'Failed',
        'view' => 'View',
        'download' => 'Download',
    ];
    $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="badge badge--' . e($status) . '">' . e($label) . '</span>';
}

function date_fmt(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('Y-m-d H:i', $ts);
}

final class ViewRenderer
{
    public function __construct(private readonly string $viewsDir)
    {
    }

    public function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $response->getBody()->write($this->renderString($template, $data));
        return $response;
    }

    public function renderString(string $template, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->viewsDir . '/' . $template . '.php';
        return (string) ob_get_clean();
    }
}
