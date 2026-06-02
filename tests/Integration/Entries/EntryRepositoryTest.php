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
}
