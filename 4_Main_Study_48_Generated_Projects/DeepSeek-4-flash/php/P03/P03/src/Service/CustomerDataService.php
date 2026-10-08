<?php

declare(strict_types=1);

namespace Shop\Service;

use Shop\DomainException;
use Shop\Repository\AddressRepository;
use Shop\Repository\UserRepository;
use Shop\Validation;
use Shop\ValidationException;

/**
 * SHOP-11 Customer data: addresses, saved preferences and profile data.
 */
final class CustomerDataService
{
    public function __construct(
        private UserRepository $users,
        private AddressRepository $addresses
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function profile(array $user): array
    {
        $row = $this->users->byId((int) $user['id']) ?? $user;
        $row['preferences'] = json_decode((string) ($row['preferences'] ?? '{}'), true) ?: [];
        $row['addresses'] = $this->addresses->forUser((int) $user['id']);
        return $row;
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     */
    public function updateProfile(array $user, array $data): array
    {
        $errors = Validation::required($data, 'name');
        Validation::throw($errors);
        $this->users->updateProfile((int) $user['id'], $data);
        return $this->profile($user);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function addresses(array $user): array
    {
        return $this->addresses->forUser((int) $user['id']);
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $data
     */
    public function createAddress(array $user, array $data): array
    {
        $errors = Validation::required($data, 'line1', 'city', 'postal_code');
        Validation::throw($errors);
        $id = $this->addresses->create((int) $user['id'], $data);
        return $this->addresses->byId($id) ?? [];
    }

    public function deleteAddress(array $user, int $id): void
    {
        $address = $this->addresses->byId($id);
        if ($address === null || (int) $address['user_id'] !== (int) $user['id']) {
            throw new DomainException('Address not found.', 404);
        }
        $this->addresses->delete($id);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function adminList(): array
    {
        return $this->users->customers();
    }

    /**
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $data
     */
    public function adminUpdate(array $admin, int $id, array $data): array
    {
        if ($admin['role'] !== 'admin') {
            throw new DomainException('Only administrators can edit customer records.', 403);
        }
        $customer = $this->users->byId($id);
        if ($customer === null || $customer['role'] !== 'customer') {
            throw new DomainException('Customer not found.', 404);
        }
        $this->users->updateProfile($id, $data);
        return $this->users->byId($id) ?? [];
    }
}
