<?php

declare(strict_types=1);

namespace BsFabric\Api;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Templates\TemplateRepository;

final class EntryResolver
{
    public function __construct(
        private readonly TemplateLookup $templateRepository,
        private readonly EntryQueryExecutor $entryRepository,
    ) {
    }

    public function resolve(string $templateName, int $id): ?Entry
    {
        $template = $this->templateRepository->getByName(sanitize_key($templateName));

        if ($template === null) {
            return null;
        }

        $entry = $this->entryRepository->getById($id);

        if ($entry === null || (int) $entry->template_id !== $template->getId()) {
            return null;
        }

        return $entry;
    }
}
