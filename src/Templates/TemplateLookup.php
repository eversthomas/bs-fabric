<?php

declare(strict_types=1);

namespace BsFabric\Templates;

interface TemplateLookup
{
    public function getByName(string $name): ?Template;
}
