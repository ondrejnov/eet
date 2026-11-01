<?php

declare(strict_types=1);

namespace Ondrejnov\EET;

use Ondrejnov\EET\Utils\ReadOnlyProperties;

/**
 * Parsed Odpoved element: a confirmation with POK or an error, optionally with warnings.
 *
 * @property-read string|null $uuid
 * @property-read string|null $receivedAt
 * @property-read string|null $rejectedAt
 * @property-read string|null $pok
 * @property-read int|null $errorCode
 * @property-read string|null $errorMessage
 * @property-read bool $test
 * @property-read array<int, string> $warnings
 * @property-read string|null $transactionId
 */
final class Response
{
    use ReadOnlyProperties;

    private $uuid;

    private $receivedAt;

    private $rejectedAt;

    private $pok;

    private $errorCode;

    private $errorMessage;

    private $test;

    private $warnings;

    private $transactionId;

    /**
     * @param string|null $uuid
     * @param string|null $receivedAt
     * @param string|null $rejectedAt
     * @param string|null $pok
     * @param int|null $errorCode
     * @param string|null $errorMessage
     * @param array<int, string> $warnings [warning code => message]
     * @param string|null $transactionId
     */
    public function __construct(
        $uuid,
        $receivedAt,
        $rejectedAt,
        $pok,
        $errorCode,
        $errorMessage,
        bool $test,
        array $warnings,
        $transactionId
    ) {
        $this->uuid = $uuid;
        $this->receivedAt = $receivedAt;
        $this->rejectedAt = $rejectedAt;
        $this->pok = $pok;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->test = $test;
        $this->warnings = $warnings;
        $this->transactionId = $transactionId;
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
