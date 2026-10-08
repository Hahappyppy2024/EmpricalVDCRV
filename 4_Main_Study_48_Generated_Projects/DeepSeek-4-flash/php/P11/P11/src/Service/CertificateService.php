<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AuditRepository;
use App\Repository\CertificateRepository;
use App\Repository\DomainRepository;

final class CertificateService
{
    private CertificateRepository $certificates;

    private DomainRepository $domains;

    private AuditRepository $audit;

    public function __construct()
    {
        $this->certificates = new CertificateRepository();
        $this->domains = new DomainRepository();
        $this->audit = new AuditRepository();
    }

    public function listFor(array $user): array
    {
        return $this->certificates->allForUser((int) $user['id']);
    }

    public function getForUser(int $id, array $user): ?array
    {
        return $this->certificates->findForUser($id, (int) $user['id']);
    }

    public function domainsFor(array $user): array
    {
        return $this->domains->allForUser((int) $user['id']);
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, id]
     */
    public function request(array $user, int $domainId, string $provider): array
    {
        $domain = $this->domains->findForUser($domainId, (int) $user['id']);
        if ($domain === null) {
            return ['Selected domain does not exist for your account.', null];
        }
        if (!in_array($provider, ['letsencrypt', 'zerossl', 'selfsigned'], true)) {
            return ['Unsupported certificate provider.', null];
        }
        if ($this->certificates->existsForDomain((int) $user['id'], $domainId)) {
            return ['A certificate for this domain already exists.', null];
        }

        $id = $this->certificates->create((int) $user['id'], $domainId, $provider, 'requested', '', '');
        $this->audit->record((int) $user['id'], $user['username'], 'request', 'ssl_certificate_management', 'certificate', (string) $id, 'Requested certificate for ' . $domain['name']);

        return [null, $id];
    }

    /**
     * @return array{0: ?string, 1: ?int} [error, id]
     */
    public function upload(array $user, int $domainId, string $certText, string $keyText): array
    {
        $domain = $this->domains->findForUser($domainId, (int) $user['id']);
        if ($domain === null) {
            return ['Selected domain does not exist for your account.', null];
        }
        if (trim($certText) === '' || trim($keyText) === '') {
            return ['Certificate and private key are required.', null];
        }

        $id = $this->certificates->create((int) $user['id'], $domainId, 'manual', 'issued', $certText, $keyText);
        $this->audit->record((int) $user['id'], $user['username'], 'upload', 'ssl_certificate_management', 'certificate', (string) $id, 'Uploaded manual certificate for ' . $domain['name']);

        return [null, $id];
    }

    public function renew(array $user, int $id): ?string
    {
        $cert = $this->certificates->findForUser($id, (int) $user['id']);
        if ($cert === null) {
            return 'Unknown or out-of-scope certificate.';
        }
        $this->certificates->renew(
            $id,
            'issued',
            "-----BEGIN CERTIFICATE-----\nRENEWED-" . date('Ymd') . "\n-----END CERTIFICATE-----",
            "-----BEGIN PRIVATE KEY-----\nRENEWED-" . date('Ymd') . "\n-----END PRIVATE KEY-----"
        );
        $this->audit->record((int) $user['id'], $user['username'], 'renew', 'ssl_certificate_management', 'certificate', (string) $id, 'Renewed certificate for ' . $cert['domain_name']);

        return null;
    }
}
