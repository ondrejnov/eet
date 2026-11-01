<?php

declare(strict_types=1);

namespace Ondrejnov\EET;

/**
 * Parsed Odpoved element: a confirmation with POK or an error, optionally with warnings.
 */
final class Response
{
    /**
     * @param array<int, string> $warnings [warning code => message]
     */
    public function __construct(
        public readonly ?string $uuid,
        public readonly ?string $receivedAt,
        public readonly ?string $rejectedAt,
        public readonly ?string $pok,
        public readonly ?int $errorCode,
        public readonly ?string $errorMessage,
        public readonly bool $test,
        public readonly array $warnings,
        public readonly ?string $transactionId,
    ) {
    }

    /**
     * The sale was accepted and a POK was issued.
     */
    public function isConfirmed(): bool
    {
        return $this->pok !== null;
    }

    /**
     * A message sent in the verification mode was processed without critical errors.
     */
    public function isVerified(): bool
    {
        return $this->errorCode === 0;
    }
}
