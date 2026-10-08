<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ConferencePhasesRepository;

final class PhaseService
{
    private const PHASES = ['submission', 'review', 'rebuttal', 'decision'];

    public function __construct(private readonly ConferencePhasesRepository $phases)
    {
    }

    public function all(): array
    {
        return $this->phases->findAll([], 'id ASC');
    }

    public function get(string $name): ?array
    {
        return $this->phases->findByName($name);
    }

    public function isOpen(string $name): bool
    {
        $phase = $this->get($name);
        return $phase !== null && $phase['status'] === 'open';
    }

    public function requireOpen(string $name): array
    {
        $phase = $this->get($name);
        if ($phase === null) {
            throw new WorkflowException('phase_error', 409, ['phase' => $name], "The {$name} phase has not been configured.");
        }
        if ($phase['status'] !== 'open') {
            throw new WorkflowException(
                'phase_error',
                409,
                ['phase' => $name, 'status' => $phase['status']],
                "The {$name} phase is currently closed."
            );
        }
        return $phase;
    }

    /**
     * @return array<string, string>
     */
    public static function names(): array
    {
        return self::PHASES;
    }
}
