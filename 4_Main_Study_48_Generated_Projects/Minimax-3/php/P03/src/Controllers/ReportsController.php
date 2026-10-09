<?php
declare(strict_types=1);

namespace Shop\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Shop\Auth\SessionManager;
use Shop\Models\ReportRepository;
use Shop\Models\AuditRepository;

final class ReportsController extends BaseController
{
    public function page(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $scope = $user['role'] === 'admin' ? null : (int)$user['id'];
        return $this->render($response, 'reports.php', [
            'page_title' => 'Reports',
            'sales' => ReportRepository::sales($scope),
            'inventory_report' => ReportRepository::inventoryReport(),
            'customers' => ReportRepository::customers(),
            'role' => $user['role'],
        ]);
    }

    public function exportCsv(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $type = (string)($request->getQueryParams()['type'] ?? 'sales');
        $scope = $user['role'] === 'admin' ? null : (int)$user['id'];
        switch ($type) {
            case 'inventory':
                $rows = ReportRepository::inventoryReport();
                $headers = ['id', 'sku', 'name', 'quantity', 'restock_threshold', 'needs_restock'];
                break;
            case 'customers':
                $rows = ReportRepository::customers();
                $headers = ['id', 'display_name', 'email', 'created_at', 'order_count', 'lifetime_value_cents'];
                break;
            case 'sales':
            default:
                $rows = ReportRepository::sales($scope);
                $headers = ['id', 'reference', 'status', 'total_cents', 'placed_at', 'customer_name'];
                break;
        }
        AuditRepository::log((int)$user['id'], 'report_export', 'report', $type);
        $csv = ReportRepository::toCsv($rows, $headers);
        $response->getBody()->write($csv);
        return $response
            ->withHeader('Content-Type', 'text/csv')
            ->withHeader('Content-Disposition', 'attachment; filename="report-' . $type . '.csv"');
    }

    public function apiIndex(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $scope = $user['role'] === 'admin' ? null : (int)$user['id'];
        return $this->json($response, [
            'sales' => ReportRepository::sales($scope),
            'inventory' => ReportRepository::inventoryReport(),
            'customers' => ReportRepository::customers(),
        ]);
    }

    public function apiCreate(Request $request, Response $response): Response
    {
        $user = $this->requireRole($request, 'seller');
        $data = $this->jsonBody($request);
        $type = (string)($data['type'] ?? 'sales');
        AuditRepository::log((int)$user['id'], 'report_request_api', 'report', $type);
        return $this->json($response, ['requested' => $type, 'status' => 'queued (synchronous)']);
    }

    public function apiPatch(Request $request, Response $response, array $args): Response
    {
        $user = $this->requireRole($request, 'seller');
        AuditRepository::log((int)$user['id'], 'report_patch', 'report', (string)($args['id'] ?? ''));
        return $this->json($response, ['ok' => true]);
    }
}