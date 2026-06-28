<?php

declare(strict_types=1);

namespace BsFabric\Rest;

use BsFabric\Core\Capabilities;
use BsFabric\Entries\Entry;
use WP_REST_Request;

final class RestPermissionChecker
{
    public function canReadEntry(WP_REST_Request $request): bool
    {
        return $this->canReadEntries($request);
    }

    public function canReadEntries(WP_REST_Request $request): bool
    {
        if ($this->requestsUnpublished($request)) {
            return current_user_can(Capabilities::MANAGE_ENTRIES);
        }

        return true;
    }

    public function canViewEntry(Entry $entry): bool
    {
        if ($entry->status === 'published') {
            return true;
        }

        return current_user_can(Capabilities::MANAGE_ENTRIES);
    }

    private function requestsUnpublished(WP_REST_Request $request): bool
    {
        $status = sanitize_key((string) ($request->get_param('status') ?? 'published'));

        return $status !== 'published';
    }
}
