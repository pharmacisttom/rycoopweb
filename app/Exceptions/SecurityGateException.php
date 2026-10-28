<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Structured exception for RBAC, Step-Up MFA, and Dual-Control security gate violations.
 */
class SecurityGateException extends RuntimeException
{
    private int $httpStatus;
    private string $errorCode;
    private array $context;

    public function __construct(
        string $message,
        int $httpStatus = 403,
        string $errorCode = 'SECURITY_GATE_REJECTED',
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

