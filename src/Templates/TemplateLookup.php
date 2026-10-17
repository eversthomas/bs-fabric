<?php

declare(strict_types=1);

namespace BsFabric\Templates;

interface TemplateLookup
{
    public function getByName(string $name): ?Template;

    public function getById(int $id): ?Template;

    /**
     * @return list<Template>
     */
    public function getAll(): array;
}
