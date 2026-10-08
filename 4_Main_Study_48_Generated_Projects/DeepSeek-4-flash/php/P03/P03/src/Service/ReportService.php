<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\OrderRepository;
use Shop\Repository\StockRepository;
use Shop\Repository\UserRepository;

/**
 * SHOP-12 Reports: sales, inventory and customer reports with CSV export.
 */
final class ReportService
{
    public const TYPES = ['sales', 'inventory', 'customers'];

    public function __construct(
        private OrderRepository $orders,
        private StockRepository $stock,
        private UserRepository $users
    ) {
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function generate(array $user, string $type, array $filters = []): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new DomainException('Unknown report type.', 400);
        }
        $data = match ($type) {
            'sales' => $this->sales($filters),
            'inventory' => $this->inventory($user),
            'customers' => $this->customers(),
        };
        $data['type'] = $type;
        return $data;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function sales(array $filters = []): array
    {
        return [
            'orders' => $this->orders->all($filters),
            'totals' => $this->orders->totals(),
            'status_counts' => $this->orders->statusCounts(),
            'top_products' => $this->orders->topProducts(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function inventory(array $user): array
    {
        return [
            'stock_value' => $this->stock->stockValue(),
            'low_stock' => $this->stock->lowStock(3),
            'movements' => $this->stock->recent(100),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function customers(): array
    {
        $rows = [];
        foreach ($this->users->customers() as $customer) {
            $orders = $this->orders->forUser((int) $customer['id']);
            $paid = array_filter($orders, fn (array $o) => in_array($o['status'], ['paid', 'shipped', 'delivered'], true));
            $spend = array_sum(array_map(fn (array $o) => (float) $o['total'], $paid));
            $rows[] = [
                'id' => (int) $customer['id'],
                'name' => $customer['name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'orders' => count($orders),
                'spend' => round($spend, 2),
                'active' => (int) $customer['active'],
            ];
        }
        return ['customers' => $rows];
    }

    /**
     * Build a CSV export for a report type.
     *
     * @return array{filename:string,csv:string,headers:list<string>,rows:list<list<mixed>>}
     */
    public function export(string $type): array
    {
        switch ($type) {
            case 'sales':
                $headers = ['Number', 'Customer', 'Status', 'Subtotal', 'Shipping', 'Tax', 'Total', 'Created'];
                $rows = [];
                foreach ($this->orders->all() as $o) {
                    $rows[] = [$o['number'], $o['customer_name'], $o['status'], $o['subtotal'], $o['shipping'], $o['tax'], $o['total'], $o['created_at']];
                }
                $filename = 'sales-report.csv';
                break;

            case 'inventory':
                $headers = ['Product', 'Stock', 'Price', 'Value'];
                $rows = [];
                foreach ($this->stock->stockValue() as $p) {
                    $rows[] = [$p['name'], $p['stock'], $p['price'], $p['value']];
                }
                $filename = 'inventory-report.csv';
                break;

            case 'customers':
                $headers = ['Name', 'Email', 'Orders', 'Spend', 'Active'];
                $rows = [];
                foreach ($this->customers()['customers'] as $c) {
                    $rows[] = [$c['name'], $c['email'], $c['orders'], $c['spend'], $c['active'] ? 'yes' : 'no'];
                }
                $filename = 'customers-report.csv';
                break;

            default:
                throw new DomainException('Unknown report type.', 400);
        }

        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_map($this->csvSafe(...), $headers));
        foreach ($rows as $row) {
            fputcsv($out, array_map($this->csvSafe(...), $row));
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return ['filename' => $filename, 'csv' => $csv, 'headers' => $headers, 'rows' => $rows];
    }

    /**
     * Neutralize spreadsheet formula injection in CSV cells: cells beginning
     * with =, +, - or @ are prefixed with a single quote.
     */
    private function csvSafe(mixed $value): string
    {
        $cell = (string) $value;
        if ($cell !== '' && in_array($cell[0], ['=', '+', '-', '@'], true)) {
            return "'" . $cell;
        }
        return $cell;
    }
}
