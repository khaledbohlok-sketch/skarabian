<?php
declare(strict_types=1);

namespace App\Core;

final class ValidationException extends \RuntimeException
{
    public function __construct(public array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
    }

    public static function one(string $field, string $messageKey, array $params = []): self
    {
        return new self([$field => __($messageKey, $params)]);
    }
}
