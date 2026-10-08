<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\ClientRepository;
use Shop\Repository\OrderRepository;
use Shop\Validation;

/**
 * SHOP-13 Frontend API integration: client registration plus the data the
 * browser needs for validation, payment simulation and async order states.
 */
final class FrontendApiService
{
    public function __construct(
        private ClientRepository $clients,
        private OrderRepository $orders
    ) {
    }

    /**
     * @param array<string,mixed> $user
     */
    public function registerClient(array $user, string $name): array
    {
        $errors = Validation::required(['name' => $name], 'name');
        Validation::throw($errors);
        $id = $this->clients->create((int) $user['id'], trim($name));
        return $this->clients->byId($id) ?? [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function clients(array $user): array
    {
        return $this->clients->forUser((int) $user['id']);
    }

    /**
     * @param array<string,mixed> $user
     */
    public function updateClient(array $user, int $id, string $name, array $prefs): array
    {
        $client = $this->clients->byIdForUser($id, (int) $user['id']);
        if ($client === null) {
            throw new DomainException('Client not found.', 404);
        }
        $errors = Validation::required(['name' => $name], 'name');
        Validation::throw($errors);
        $this->clients->update($id, trim($name), $prefs);
        return $this->clients->byId($id) ?? [];
    }

    /**
     * Deterministic simulated payment states for the async UI.
     *
     * The order must belong to the requesting user (administrators may query any order).
     *
     * @return list<array{step:string,status:string}>
     */
    public function paymentStates(array $user, int $orderId): array
    {
        $order = $this->orders->byId($orderId);
        if ($order === null) {
            throw new DomainException('Order not found.', 404);
        }
        if ($user['role'] !== 'admin' && (int) $order['user_id'] !== (int) $user['id']) {
            throw new DomainException('Order not found.', 404);
        }
        $state = (string) $order['status'] === 'paid' ? 'done' : 'pending';
        return [
            ['step' => 'validate', 'status' => 'done'],
            ['step' => 'processing', 'status' => $state === 'done' ? 'done' : 'running'],
            ['step' => 'approved', 'status' => $state],
        ];
    }

    /**
     * Order states for the async order-status UI (WebSocket fallback poll).
     *
     * @return list<array<string,mixed>>
     */
    public function orderStates(array $user, array $filters = []): array
    {
        if ($user['role'] === 'customer') {
            $orders = $this->orders->forUser((int) $user['id'], $filters);
        } elseif ($user['role'] === 'seller') {
            $orders = $this->orders->forSeller((int) $user['id'], $filters);
        } else {
            $orders = $this->orders->all($filters);
        }
        return array_map(
            fn (array $o) => [
                'id' => (int) $o['id'],
                'number' => (string) $o['number'],
                'status' => (string) $o['status'],
                'total' => (float) $o['total'],
            ],
            $orders
        );
    }

    /**
     * The WebSocket endpoint the browser should connect to.
     */
    public function wsUrl(): string
    {
        return (string) (getenv('WS_URL') ?: 'ws://127.0.0.1:8081');
    }
}
