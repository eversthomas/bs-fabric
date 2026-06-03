<?php

declare(strict_types=1);

namespace BsFabric\Tests\Integration\Templates;

use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Templates\DuplicateTemplateNameException;
use BsFabric\Templates\TemplateInUseException;
use BsFabric\Templates\TemplateRepository;
use BsFabric\Tests\Integration\TestCase;

final class TemplateRepositoryTest extends TestCase
{
    private FieldRepository $fieldRepository;

    private TemplateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fieldRepository = new FieldRepository(new FieldRegistry());
        $this->repository = new TemplateRepository($this->fieldRepository);
    }

    public function testCreateTemplate(): void
    {
        $templateId = $this->repository->create([
            'name' => 'einrichtung',
            'label' => 'Einrichtung',
        ]);

        $this->assertGreaterThan(0, $templateId);

        $template = $this->repository->getById($templateId);

        $this->assertNotNull($template);
        $this->assertSame('einrichtung', $template->name);
        $this->assertSame('Einrichtung', $template->label);
    }

    public function testUpdateTemplate(): void
    {
        $templateId = $this->repository->create([
            'name' => 'event',
            'label' => 'Event',
        ]);

        $fieldId = $this->fieldRepository->create([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);

        $this->repository->assignField($templateId, $fieldId);

        $updated = $this->repository->update($templateId, [
            'label' => 'Updated Event',
            'title_field' => 'title',
        ]);

        $this->assertTrue($updated);

        $template = $this->repository->getById($templateId);

        $this->assertNotNull($template);
        $this->assertSame('event', $template->name);
        $this->assertSame('Updated Event', $template->label);
        $this->assertSame('title', $template->title_field);
    }

    public function testDeleteTemplate(): void
    {
        $templateId = $this->repository->create([
            'name' => 'delete_me',
            'label' => 'Delete Me',
        ]);

        $this->assertTrue($this->repository->delete($templateId));
        $this->assertNull($this->repository->getById($templateId));
    }

    public function testDeleteFailsWhenTemplateHasEntries(): void
    {
        global $wpdb;

        $templateId = $this->repository->create([
            'name' => 'locked_template',
            'label' => 'Locked Template',
        ]);

        $entriesTable = $wpdb->prefix . 'bs_fabric_entries';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$entriesTable} (template_id, title, status) VALUES (%d, %s, %s)",
                $templateId,
                'Test Entry',
                'draft'
            )
        );

        $this->expectException(TemplateInUseException::class);

        $this->repository->delete($templateId);
    }

    public function testAssignFieldAndGetFields(): void
    {
        $templateId = $this->repository->create([
            'name' => 'contact',
            'label' => 'Contact',
        ]);

        $fieldA = $this->fieldRepository->create([
            'name' => 'name',
            'label' => 'Name',
            'type' => 'text',
        ]);
        $fieldB = $this->fieldRepository->create([
            'name' => 'email',
            'label' => 'Email',
            'type' => 'text',
        ]);

        $this->assertTrue($this->repository->assignField($templateId, $fieldA, true));
        $this->assertTrue($this->repository->assignField($templateId, $fieldB));

        $fields = $this->repository->getFields($templateId);

        $this->assertCount(2, $fields);
        $this->assertSame('name', $fields[0]['name']);
        $this->assertTrue($fields[0]['is_required']);
        $this->assertSame('email', $fields[1]['name']);
        $this->assertFalse($fields[1]['is_required']);
    }

    public function testUpdateFieldOrder(): void
    {
        $templateId = $this->repository->create([
            'name' => 'ordered',
            'label' => 'Ordered',
        ]);

        $fieldA = $this->fieldRepository->create([
            'name' => 'first',
            'label' => 'First',
            'type' => 'text',
        ]);
        $fieldB = $this->fieldRepository->create([
            'name' => 'second',
            'label' => 'Second',
            'type' => 'text',
        ]);
        $fieldC = $this->fieldRepository->create([
            'name' => 'third',
            'label' => 'Third',
            'type' => 'text',
        ]);

        $this->repository->assignField($templateId, $fieldA);
        $this->repository->assignField($templateId, $fieldB);
        $this->repository->assignField($templateId, $fieldC);

        $this->assertTrue($this->repository->updateFieldOrder($templateId, [$fieldC, $fieldA, $fieldB]));

        $fields = $this->repository->getFields($templateId);

        $this->assertSame('third', $fields[0]['name']);
        $this->assertSame('first', $fields[1]['name']);
        $this->assertSame('second', $fields[2]['name']);
    }

    public function testUniqueNameConstraint(): void
    {
        $this->repository->create([
            'name' => 'name_collision_template',
            'label' => 'Name Collision Template',
        ]);

        $this->expectException(DuplicateTemplateNameException::class);

        $this->repository->create([
            'name' => 'name_collision_template',
            'label' => 'Duplicate',
        ]);
    }
}
