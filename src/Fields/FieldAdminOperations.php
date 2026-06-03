<?php

declare(strict_types=1);

namespace BsFabric\Fields;

interface FieldAdminOperations
{
    public function delete(int $id): bool;
}
