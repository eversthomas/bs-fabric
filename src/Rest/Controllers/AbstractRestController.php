<?php

declare(strict_types=1);

namespace BsFabric\Rest\Controllers;

use WP_REST_Controller;

abstract class AbstractRestController extends WP_REST_Controller
{
    /** @var string */
    protected $namespace = 'bs-fabric/v1';

    protected function entrySelfLink(string $templateName, int $entryId): string
    {
        return rest_url($this->namespace . '/templates/' . sanitize_key($templateName) . '/entries/' . $entryId);
    }

    protected function entryCollectionLink(string $templateName): string
    {
        return rest_url($this->namespace . '/templates/' . sanitize_key($templateName) . '/entries');
    }
}
