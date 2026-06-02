<?php

declare(strict_types=1);

namespace BsFabric\Api;

use BsFabric\Core\Plugin;
use BsFabric\Entries\Entry;
use BsFabric\Query\EntryQuery;

if (! function_exists('bs_fabric')) {
    /**
     * @return EntryQuery|Entry|null
     */
    function bs_fabric(string $selector, ?int $id = null): EntryQuery|Entry|null
    {
        return PublicApi::resolve($selector, $id);
    }
}

final class PublicApi
{
    public const SERVICE_ID = 'public.api';

    public static function resolve(string $selector, ?int $id = null): EntryQuery|Entry|null
    {
        if ($selector === '') {
            return null;
        }

        if (str_contains($selector, '#')) {
            [$templateName, $entryId] = explode('#', $selector, 2);
            $templateName = sanitize_key($templateName);
            $parsedId = absint($entryId);

            if ($templateName === '' || $parsedId <= 0) {
                return null;
            }

            return self::instance()->entry($templateName, $parsedId);
        }

        $templateName = sanitize_key($selector);

        if ($templateName === '') {
            return null;
        }

        if ($id !== null && $id > 0) {
            return self::instance()->entry($templateName, $id);
        }

        return self::instance()->query($templateName);
    }

    public static function instance(): self
    {
        /** @var self $api */
        $api = Plugin::instance()->container()->get(self::SERVICE_ID);

        return $api;
    }

    public function __construct(
        private readonly EntryQueryFactory $entryQueryFactory,
        private readonly EntryResolver $entryResolver,
    ) {
    }

    public function query(string $templateName): EntryQuery
    {
        return $this->entryQueryFactory->create($templateName);
    }

    public function entry(string $templateName, int $id): ?Entry
    {
        return $this->entryResolver->resolve($templateName, $id);
    }
}
