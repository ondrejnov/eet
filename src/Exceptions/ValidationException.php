<?php

declare(strict_types=1);

namespace Ondrejnov\EET\Exceptions;

/**
 * Receipt does not match the data masks of the EET interface and was not sent.
 */
class ValidationException extends ClientException
{
    /** @param list<string> $errors */
    public function __construct(private array $errors)
    {
        parent::__construct(implode('; ', $errors));
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
