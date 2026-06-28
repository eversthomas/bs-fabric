<?php

declare(strict_types=1);

namespace BsFabric\Rest\Controllers;

use BsFabric\Api\EntryResolver;
use BsFabric\Rest\EntrySerializer;
use BsFabric\Rest\RestPermissionChecker;
use BsFabric\Rest\RestQueryParser;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class EntriesController extends AbstractRestController
{
    /** @var string */
    protected $rest_base = 'templates/(?P<template>[a-z0-9_-]+)/entries';

    private EntryResolver $entryResolver;

    private RestPermissionChecker $permissions;

    private RestQueryParser $queryParser;

    public function __construct(
        EntryResolver $entryResolver,
        RestPermissionChecker $permissions,
        RestQueryParser $queryParser
    ) {
        $this->entryResolver = $entryResolver;
        $this->permissions = $permissions;
        $this->queryParser = $queryParser;
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getItems(WP_REST_Request $request)
    {
        $templateName = sanitize_key((string) $request->get_param('template'));

        if ($templateName === '') {
            return new WP_Error(
                'bs_fabric_rest_invalid_params',
                __('Invalid template.', 'bs-fabric'),
                ['status' => 400]
            );
        }

        $parsed = $this->queryParser->fromRequest($request);
        $query = $parsed->getQuery();
        $serializer = $this->createSerializer($parsed->getEmbedRelations());
        $total = $query->count();
        $collection = $query->get();

        $items = [];

        foreach ($collection as $entry) {
            if (! $this->permissions->canViewEntry($entry)) {
                continue;
            }

            $items[] = $serializer->serialize($entry, $templateName);
        }

        $response = new WP_REST_Response($items, 200);
        $response->header('X-WP-Total', (string) $total);

        return $response;
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getCount(WP_REST_Request $request)
    {
        $templateName = sanitize_key((string) $request->get_param('template'));

        if ($templateName === '') {
            return new WP_Error(
                'bs_fabric_rest_invalid_params',
                __('Invalid template.', 'bs-fabric'),
                ['status' => 400]
            );
        }

        $query = $this->queryParser->fromRequest($request)->getQuery();

        return new WP_REST_Response(
            ['count' => $query->count()],
            200
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function getItem(WP_REST_Request $request)
    {
        $templateName = sanitize_key((string) $request->get_param('template'));
        $entryId = absint($request->get_param('id'));

        if ($templateName === '' || $entryId <= 0) {
            return new WP_Error(
                'bs_fabric_rest_invalid_params',
                __('Invalid template or entry ID.', 'bs-fabric'),
                ['status' => 400]
            );
        }

        $entry = $this->entryResolver->resolve($templateName, $entryId);

        if ($entry === null) {
            return new WP_Error(
                'bs_fabric_entry_not_found',
                __('Entry not found.', 'bs-fabric'),
                ['status' => 404]
            );
        }

        if (! $this->permissions->canViewEntry($entry)) {
            return new WP_Error(
                'bs_fabric_rest_forbidden',
                __('You do not have permission to view this entry.', 'bs-fabric'),
                ['status' => 403]
            );
        }

        $embedRelations = $this->queryParser->parseEmbedRelations($request);
        $serializer = $this->createSerializer($embedRelations);

        return new WP_REST_Response(
            $serializer->serialize($entry, $templateName),
            200
        );
    }

    /**
     * @param list<string> $embedRelations
     */
    private function createSerializer(array $embedRelations): EntrySerializer
    {
        return new EntrySerializer($embedRelations);
    }

    /**
     * @return array<string, mixed>
     */
    public function getCollectionArgs(): array
    {
        return [
            'template' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_key',
            ],
            'where' => [
                'type' => 'object',
                'default' => [],
            ],
            'orderby' => [
                'type' => 'string',
                'default' => 'created_at',
                'sanitize_callback' => 'sanitize_key',
            ],
            'order' => [
                'type' => 'string',
                'default' => 'desc',
                'enum' => ['asc', 'desc', 'ASC', 'DESC'],
            ],
            'per_page' => [
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
            ],
            'status' => [
                'type' => 'string',
                'default' => 'published',
                'enum' => ['published', 'draft', 'archived', 'any'],
            ],
            'with' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getItemArgs(): array
    {
        return [
            'template' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_key',
            ],
            'id' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
            'with' => [
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
}
