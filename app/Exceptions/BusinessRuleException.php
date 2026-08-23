<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 業務ルール違反。コントローラで捕捉してフォームエラーとして表示する。
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(string $message, private readonly string $errorKey = 'error')
    {
        parent::__construct($message);
    }

    public function errorKey(): string
    {
        return $this->errorKey;
    }

    /**
     * @return array<string, string>
     */
    public function toErrorBag(): array
    {
        return [$this->errorKey => $this->getMessage()];
    }
}
