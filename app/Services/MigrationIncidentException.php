<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

/**
 * Raised when a migration execution fails and recording the failed migration state
 * to the migrations tracking table ALSO fails, leaving the database schema
 * in an unverified/corrupted partial DDL state.
 *
 * Subsequent migration executions are blocked until manual reconciliation is performed.
 */
class MigrationIncidentException extends RuntimeException
{
    private ?array $incidentDetails;

    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        ?array $incidentDetails = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->incidentDetails = $incidentDetails;
    }

    public function getIncidentDetails(): ?array
    {
        return $this->incidentDetails;
    }
}
