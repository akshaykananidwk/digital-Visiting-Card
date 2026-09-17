<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown when request validation fails; handled by the kernel. */
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string,array<int,string>> $errors
     * @param array<string,mixed> $input
     */
    public function __construct(private readonly array $errors, private readonly array $input = [])
    {
        parent::__construct('The given data was invalid.', 422);
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> */
    public function input(): array
    {
        return $this->input;
    }

    public function firstMessage(): string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return (string) $messages[0];
            }
        }

        return $this->getMessage();
    }
}
