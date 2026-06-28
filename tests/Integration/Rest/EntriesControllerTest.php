<?php

declare(strict_types=1);

namespace BsFabric\Tests\Integration\Rest;

use BsFabric\Core\Plugin;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\TemplateRepository;
use BsFabric\Tests\Integration\TestCase;
use WP_REST_Request;

final class EntriesControllerTest extends TestCase
{
    private TemplateRepository $templateRepository;

    private FieldRepository $fieldRepository;

    private EntryRepository $entryRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $fieldRegistry = new FieldRegistry();
        $this->fieldRepository = new FieldRepository($fieldRegistry);
        $this->templateRepository = new TemplateRepository($this->fieldRepository);
        $this->entryRepository = new EntryRepository(
            $this->templateRepository,
            $this->fieldRepository,
            $fieldRegistry,
            new RelationRepository(),
            new \BsFabric\Entries\EntryAuditRepository()
        );

        Plugin::instance()->init();
        do_action('rest_api_init');
    }

    public function test_get_collection_returns_array(): void
    {
        $this->seedPublishedEntries();

        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries');
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertIsArray($response->get_data());
        $this->assertCount(2, $response->get_data());
        $this->assertSame('2', $response->get_headers()['X-WP-Total'][0] ?? '');
        $this->assertSame('published', $response->get_data()[0]['status'] ?? null);
    }

    public function test_count(): void
    {
        $this->seedPublishedEntries();

        $request = new WP_REST_Request('GET', '/bs-fabric/v1/templates/einrichtung/entries/count');
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['count' => 2], $response->get_data());
    }

    public function test_get_item_with_relation_embed_returns_objects(): void
    {
        $entryId = $this->seedEntryWithRelation();

        $request = new WP_REST_Request(
            'GET',
            '/bs-fabric/v1/templates/einrichtung/entries/' . $entryId
        );
        $request->set_param('with', 'ansprechpartner');
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data['ansprechpartner'] ?? null);
        $this->assertIsArray($data['ansprechpartner'][0] ?? null);
        $this->assertSame('person', $data['ansprechpartner'][0]['template'] ?? null);
        $this->assertSame('Anna Müller', $data['ansprechpartner'][0]['name'] ?? null);
    }

    private function seedPublishedEntries(): void
    {
        $nameFieldId = $this->fieldRepository->create([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        $templateId = $this->templateRepository->create([
            'name' => 'einrichtung',
            'label' => 'Einrichtung',
        ]);

        $this->templateRepository->assignField($templateId, $nameFieldId);
        $this->templateRepository->update($templateId, ['title_field' => 'name']);

        $this->entryRepository->create([
            'template_id' => $templateId,
            'status' => 'published',
            'fields' => ['name' => 'Alpha'],
        ]);
        $this->entryRepository->create([
            'template_id' => $templateId,
            'status' => 'published',
            'fields' => ['name' => 'Beta'],
        ]);
        $this->entryRepository->create([
            'template_id' => $templateId,
            'status' => 'draft',
            'fields' => ['name' => 'Draft Entry'],
        ]);
    }

    private function seedEntryWithRelation(): int
    {
        $personNameFieldId = $this->fieldRepository->create([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        $personTemplateId = $this->templateRepository->create([
            'name' => 'person',
            'label' => 'Person',
        ]);

        $this->templateRepository->assignField($personTemplateId, $personNameFieldId);
        $this->templateRepository->update($personTemplateId, ['title_field' => 'name']);

        $personId = $this->entryRepository->create([
            'template_id' => $personTemplateId,
            'status' => 'published',
            'fields' => ['name' => 'Anna Müller'],
        ]);

        $nameFieldId = $this->fieldRepository->create([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        $relationFieldId = $this->fieldRepository->create([
            'name' => 'ansprechpartner',
            'label' => 'Ansprechpartner',
            'type' => 'relation',
            'settings' => [
                'template' => 'person',
                'multiple' => true,
            ],
        ]);

        $templateId = $this->templateRepository->create([
            'name' => 'einrichtung',
            'label' => 'Einrichtung',
        ]);

        $this->templateRepository->assignField($templateId, $nameFieldId);
        $this->templateRepository->assignField($templateId, $relationFieldId);
        $this->templateRepository->update($templateId, ['title_field' => 'name']);

        return $this->entryRepository->create([
            'template_id' => $templateId,
            'status' => 'published',
            'fields' => [
                'name' => 'Stadtwerke Wesel',
                'ansprechpartner' => [$personId],
            ],
        ]);
    }
}
