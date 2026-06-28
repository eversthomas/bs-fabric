<?php

declare(strict_types=1);

namespace BsFabric\Rest;

use BsFabric\Rest\Controllers\EntriesController;
use WP_REST_Server;

final class RestBootstrap
{
    private EntriesController $entriesController;

    private RestPermissionChecker $permissions;

    public function __construct(
        EntriesController $entriesController,
        RestPermissionChecker $permissions
    ) {
        $this->entriesController = $entriesController;
        $this->permissions = $permissions;
    }

    public function registerRoutes(): void
    {
        $namespace = EntrySerializer::REST_NAMESPACE;

        register_rest_route(
            $namespace,
            '/templates/(?P<template>[a-z0-9_-]+)/entries/count',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this->entriesController, 'getCount'],
                    'permission_callback' => [$this->permissions, 'canReadEntries'],
                    'args' => $this->entriesController->getCollectionArgs(),
                ],
            ]
        );

        register_rest_route(
            $namespace,
            '/templates/(?P<template>[a-z0-9_-]+)/entries/(?P<id>\d+)',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this->entriesController, 'getItem'],
                    'permission_callback' => [$this->permissions, 'canReadEntry'],
                    'args' => $this->entriesController->getItemArgs(),
                ],
            ]
        );

        register_rest_route(
            $namespace,
            '/templates/(?P<template>[a-z0-9_-]+)/entries',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this->entriesController, 'getItems'],
                    'permission_callback' => [$this->permissions, 'canReadEntries'],
                    'args' => $this->entriesController->getCollectionArgs(),
                ],
            ]
        );
    }
}
