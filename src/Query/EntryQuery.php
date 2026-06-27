<?php

declare(strict_types=1);

namespace BsFabric\Query;

use BsFabric\Entries\Entry;
use BsFabric\Entries\EntryCollection;
use BsFabric\Entries\EntryQueryExecutor;
use BsFabric\Entries\EntryRepository;
use BsFabric\Templates\TemplateLookup;
use BsFabric\Templates\TemplateRepository;

final class EntryQuery
{
    /** @var list<array{field: string, value: mixed}> */
    private array $wheres = [];

    /** @var list<string> */
    private array $with = [];

    private ?string $orderByField = null;

    private string $orderDirection = 'ASC';

    private ?int $limit = null;

    private ?string $status = 'published';

    public function __construct(
        private readonly string $templateName,
        private readonly TemplateLookup $templateRepository,
        private readonly EntryQueryExecutor $entryRepository,
    ) {
    }

    public function where(string $field, mixed $value): self
    {
        $clone = clone $this;
        $clone->wheres[] = [
            'field' => sanitize_key($field),
            'value' => $value,
        ];

        return $clone;
    }

    public function with(string $relationField): self
    {
        $clone = clone $this;
        $clone->with[] = sanitize_key($relationField);

        return $clone;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $clone = clone $this;
        $clone->orderByField = sanitize_key($field);
        $clone->orderDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return $clone;
    }

    public function limit(int $limit): self
    {
        $clone = clone $this;
        $clone->limit = max(1, $limit);

        return $clone;
    }

    public function status(string $status): self
    {
        $clone = clone $this;
        $clone->status = sanitize_key($status);

        return $clone;
    }

    /**
     * Disable the default published-only filter (include draft and archived entries).
     */
    public function withAnyStatus(): self
    {
        $clone = clone $this;
        $clone->status = null;

        return $clone;
    }

    public function get(): EntryCollection
    {
        $templateId = $this->resolveTemplateId();

        if ($templateId === null) {
            return new EntryCollection();
        }

        return $this->entryRepository->findByFrontendQuery($this->buildArgs($templateId));
    }

    public function first(): ?Entry
    {
        return $this->limit(1)->get()->first();
    }

    public function count(): int
    {
        $templateId = $this->resolveTemplateId();

        if ($templateId === null) {
            return 0;
        }

        return $this->entryRepository->countByFrontendQuery($this->buildArgs($templateId, forCount: true));
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    /**
     * @return array{
     *     template_id: int,
     *     wheres: list<array{field: string, value: mixed}>,
     *     with: list<string>,
     *     status: string|null,
     *     order_by: string|null,
     *     order_direction: string,
     *     limit: int|null
     * }
     */
    public function buildArgs(int $templateId, bool $forCount = false): array
    {
        $args = [
            'template_id' => $templateId,
            'wheres' => $this->wheres,
            'with' => $this->with,
            'status' => $this->status,
            'order_by' => $this->orderByField,
            'order_direction' => $this->orderDirection,
            'limit' => $forCount ? null : $this->limit,
        ];

        /**
         * @param array{
         *     template_id: int,
         *     wheres: list<array{field: string, value: mixed}>,
         *     with: list<string>,
         *     status: string|null,
         *     order_by: string|null,
         *     order_direction: string,
         *     limit: int|null
         * } $args
         */
        $filtered = apply_filters('bs_fabric_query_args', $args);

        return is_array($filtered) ? $filtered : $args;
    }

    private function resolveTemplateId(): ?int
    {
        $template = $this->templateRepository->getByName($this->templateName);

        return $template?->getId();
    }
}
