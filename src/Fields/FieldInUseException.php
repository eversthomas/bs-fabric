<?php

declare(strict_types=1);

namespace BsFabric\Fields;

use RuntimeException;

final class FieldInUseException extends RuntimeException
{
    /**
     * @param list<array{id: int, name: string, label: string}> $templates
     */
    public function __construct(
        string $message,
        private readonly array $templates = [],
    ) {
        parent::__construct($message);
    }

    /**
     * @return list<array{id: int, name: string, label: string}>
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }
}
