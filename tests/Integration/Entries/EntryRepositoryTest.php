<?php

declare(strict_types=1);

namespace BsFabric\Tests\Integration\Entries;

use BsFabric\Entries\EntryCollection;
use BsFabric\Entries\EntryNotFoundException;
use BsFabric\Entries\EntryRepository;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Relations\RelationRepository;
use BsFabric\Templates\TemplateRepository;
use BsFabric\Tests\Integration\TestCase;

final class EntryRepositoryTest extends TestCase
{
    private FieldRepository $fieldRepository;

    private TemplateRepository $templateRepository;

    private EntryRepository $repository;

    private RelationRepository $relationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $fieldRegistry = new FieldRegistry();
        $this->fieldRepository = new FieldRepository($fieldRegistry);
        $this->templateRepository = new TemplateRepository($this->fieldRepository);
        $this->relationRepository = new RelationRepository();
        $this->repository = new EntryRepository(
            $this->templateRepository,
            $this->fieldRepository,
            $fieldRegistry,
            $this->relationRepository
        );
    }

    public function testCreateEntryStoresFieldValuesAndTitle(): void
    {
        $titleFieldId = $this->fieldRepository->create([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);
        $bodyFieldId = $this->fieldRepository->create([
            'name' => 'body',
            'label' => 'Body',
            'type' => 'textarea',
        ]);

        $templateId = $this->templateRepository->create([
            'name' => 'article',
            'label' => 'Article',
        ]);

        $this->templateRepository->assignField($templateId, $titleFieldId);
        $this->templateRepository->assignField($templateId, $bodyFieldId);
        $this->templateRepository->update($templateId, ['title_field' => 'title']);

        $entryId = $this->repository->create([
            'template_id' => $templateId,
            'status' => 'published',
            'fields' => [
                'title' => 'My Article Title',
                'body' => 'Article body text',
            ],
        ]);

        $entry = $this->repository->getById($entryId);

        $this->assertNotNull($entry);
        $this->assertSame('My Article Title', $entry->title);
        $this->assertSame('published', $entry->status);
        $this->assertSame('Article body text', $entry->body);
    }

    public function testUpdateEntryUpdatesFieldValuesAndTitle(): void
    {
        $titleFieldId = $this->fieldRepository->create([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        $templateId = $this->templateRepository->create([
            'name' => 'person',
            'label' => 'Person',
        ]);

        $this->templateRepository->assignField($templateId, $titleFieldId);
        $this->templateRepository->update($templateId, ['title_field' => 'name']);

        $entryId = $this->repository->create([
            'template_id' => $templateId,
            'fields' => ['name' => 'Alice'],
        ]);

        $this->repository->update($entryId, [
            'status' => 'archived',
            'fields' => ['name' => 'Bob'],
        ]);

        $entry = $this->repository->getById($entryId);

        $this->assertNotNull($entry);
        $this->assertSame('Bob', $entry->title);
        $this->assertSame('Bob', $entry->name);
        $this->assertSame('archived', $entry->status);
    }

    public function testDeleteEntry(): void
    {
        $templateId = $this->templateRepository->create([
            'name' => 'temp',
            'label' => 'Temp',
        ]);

        $entryId = $this->repository->create([
            'template_id' => $templateId,
        ]);

        $this->assertTrue($this->repository->delete($entryId));
        $this->assertNull($this->repository->getById($entryId));
    }

    public function testDeleteThrowsWhenEntryNotFound(): void
    {
        $this->expectException(EntryNotFoundException::class);

        $this->repository->delete(99999);
    }

    public function testGetExplorerRowsReturnsEmptyArrayWhenNoEntries(): void
    {
        $rows = $this->repository->getExplorerRows();

        $this->assertSame([], $rows);
    }

    public function testDeleteAllRemovesEntriesValuesAndRelations(): void
    {
        global $wpdb;

        $personTemplateId = $this->templateRepository->create([
            'name' => 'person',
            'label' => 'Person',
        ]);
        $orgTemplateId = $this->templateRepository->create([
            'name' => 'organisation',
            'label' => 'Organisation',
        ]);

        $personNameFieldId = $this->fieldRepository->create([
            'name' => 'person_name',
            'label' => 'Name',
            'type' => 'text',
        ]);
        $orgNameFieldId = $this->fieldRepository->create([
            'name' => 'org_name',
            'label' => 'Name',
            'type' => 'text',
        ]);
        $contactsFieldId = $this->fieldRepository->create([
            'name' => 'contacts',
            'label' => 'Contacts',
            'type' => 'relation',
            'settings' => ['template' => 'person', 'multiple' => true],
        ]);

        $this->templateRepository->assignField($personTemplateId, $personNameFieldId);
        $this->templateRepository->assignField($orgTemplateId, $orgNameFieldId);
        $this->templateRepository->assignField($orgTemplateId, $contactsFieldId);

        $personId = $this->repository->create([
            'template_id' => $personTemplateId,
            'fields' => ['person_name' => 'Alice'],
        ]);

        $this->repository->create([
            'template_id' => $orgTemplateId,
            'fields' => [
                'org_name' => 'Acme Corp',
                'contacts' => [$personId],
            ],
        ]);

        $this->assertGreaterThan(0, $this->repository->countAll());

        $this->repository->deleteAll();

        $entriesTable = $wpdb->prefix . 'bs_fabric_entries';
        $valuesTable = $wpdb->prefix . 'bs_fabric_entry_values';
        $relationsTable = $wpdb->prefix . 'bs_fabric_relations';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->assertSame(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$entriesTable}"));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->assertSame(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$valuesTable}"));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->assertSame(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$relationsTable}"));
    }

    public function testGetByTemplateReturnsMatchingEntries(): void
    {
        $templateA = $this->templateRepository->create(['name' => 'a', 'label' => 'A']);
        $templateB = $this->templateRepository->create(['name' => 'b', 'label' => 'B']);

        $this->repository->create(['template_id' => $templateA]);
        $this->repository->create(['template_id' => $templateA]);
        $this->repository->create(['template_id' => $templateB]);

        $collection = $this->repository->getByTemplate($templateA);

        $this->assertInstanceOf(EntryCollection::class, $collection);
        $this->assertCount(2, $collection);
    }

    public function testRelationsAreStoredAndLoaded(): void
    {
        $personTemplateId = $this->templateRepository->create([
            'name' => 'person',
            'label' => 'Person',
        ]);
        $orgTemplateId = $this->templateRepository->create([
            'name' => 'organisation',
            'label' => 'Organisation',
        ]);

        $personNameFieldId = $this->fieldRepository->create([
            'name' => 'person_name',
            'label' => 'Name',
            'type' => 'text',
        ]);
        $orgNameFieldId = $this->fieldRepository->create([
            'name' => 'org_name',
            'label' => 'Name',
            'type' => 'text',
        ]);
        $contactsFieldId = $this->fieldRepository->create([
            'name' => 'contacts',
            'label' => 'Contacts',
            'type' => 'relation',
            'settings' => ['template' => 'person', 'multiple' => true],
        ]);

        $this->templateRepository->assignField($personTemplateId, $personNameFieldId);
        $this->templateRepository->assignField($orgTemplateId, $orgNameFieldId);
        $this->templateRepository->assignField($orgTemplateId, $contactsFieldId);

        $personOneId = $this->repository->create([
            'template_id' => $personTemplateId,
            'fields' => ['person_name' => 'Alice'],
        ]);
        $personTwoId = $this->repository->create([
            'template_id' => $personTemplateId,
            'fields' => ['person_name' => 'Bob'],
        ]);

        $orgId = $this->repository->create([
            'template_id' => $orgTemplateId,
            'fields' => [
                'org_name' => 'Acme Corp',
                'contacts' => [$personTwoId, $personOneId],
            ],
        ]);

        $storedIds = $this->relationRepository->get($orgId, $contactsFieldId);
        $this->assertSame([$personTwoId, $personOneId], $storedIds);

        $org = $this->repository->getById($orgId);
        $this->assertNotNull($org);

        $contacts = $org->contacts;
        $this->assertInstanceOf(EntryCollection::class, $contacts);
        $this->assertCount(2, $contacts);
        $this->assertSame('Bob', $contacts->first()?->person_name);
    }

    public function testRelationRepositoryReorder(): void
    {
        $templateId = $this->templateRepository->create(['name' => 'org', 'label' => 'Org']);
        $relationFieldId = $this->fieldRepository->create([
            'name' => 'members',
            'label' => 'Members',
            'type' => 'relation',
        ]);
        $this->templateRepository->assignField($templateId, $relationFieldId);

        $entryId = $this->repository->create(['template_id' => $templateId]);
        $memberA = $this->repository->create(['template_id' => $templateId]);
        $memberB = $this->repository->create(['template_id' => $templateId]);
        $memberC = $this->repository->create(['template_id' => $templateId]);

        $this->relationRepository->set($entryId, $relationFieldId, [$memberA, $memberB, $memberC]);
        $this->assertTrue($this->relationRepository->reorder($entryId, $relationFieldId, [$memberC, $memberA, $memberB]));
        $this->assertSame([$memberC, $memberA, $memberB], $this->relationRepository->get($entryId, $relationFieldId));

        $this->assertTrue($this->relationRepository->delete($entryId, $relationFieldId));
        $this->assertSame([], $this->relationRepository->get($entryId, $relationFieldId));
    }

    public function testGetExplorerRowsReturnsJoinedTemplateData(): void
    {
        $titleFieldId = $this->fieldRepository->create([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        $articleTemplateId = $this->templateRepository->create([
            'name' => 'article',
            'label' => 'Article',
        ]);
        $eventTemplateId = $this->templateRepository->create([
            'name' => 'event',
            'label' => 'Event',
        ]);

        $this->templateRepository->assignField($articleTemplateId, $titleFieldId);
        $this->templateRepository->assignField($eventTemplateId, $titleFieldId);
        $this->templateRepository->update($articleTemplateId, ['title_field' => 'name']);
        $this->templateRepository->update($eventTemplateId, ['title_field' => 'name']);

        $articleEntryId = $this->repository->create([
            'template_id' => $articleTemplateId,
            'status' => 'published',
            'fields' => ['name' => 'Published Article'],
        ]);
        $this->repository->create([
            'template_id' => $eventTemplateId,
            'status' => 'draft',
            'fields' => ['name' => 'Draft Event'],
        ]);

        $rows = $this->repository->getExplorerRows();

        $this->assertCount(2, $rows);
        $this->assertSame($articleEntryId, $rows[1]['id']);
        $this->assertSame('Published Article', $rows[1]['title']);
        $this->assertSame($articleTemplateId, $rows[1]['template_id']);
        $this->assertSame('Article', $rows[1]['template_label']);
        $this->assertSame('published', $rows[1]['status']);
        $this->assertNotSame('', $rows[1]['created_at']);
    }

    public function testExplorerRowFallsBackToTitleFieldValueWhenStoredTitleMissing(): void
    {
        global $wpdb;

        $nameFieldId = $this->fieldRepository->create([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);

        $templateId = $this->templateRepository->create([
            'name' => 'contact',
            'label' => 'Contact',
        ]);

        $this->templateRepository->assignField($templateId, $nameFieldId);
        $this->templateRepository->update($templateId, ['title_field' => 'name']);

        $entryId = $this->repository->create([
            'template_id' => $templateId,
            'fields' => ['name' => 'Evers'],
        ]);

        $entriesTable = $wpdb->prefix . 'bs_fabric_entries';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$entriesTable} SET title = NULL WHERE id = %d",
                $entryId
            )
        );

        $rows = $this->repository->getExplorerRows();
        $row = array_values(array_filter($rows, static fn (array $item): bool => $item['id'] === $entryId))[0] ?? null;

        $this->assertIsArray($row);
        $this->assertSame('Evers', $row['title']);

        $entry = $this->repository->getById($entryId);
        $this->assertNotNull($entry);
        $this->assertSame('Evers', $entry->title);
    }
}
