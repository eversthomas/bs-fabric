<?php

declare(strict_types=1);

namespace BsFabric\Tests\Integration\Fields;

use BsFabric\Fields\DuplicateFieldNameException;
use BsFabric\Fields\FieldInUseException;
use BsFabric\Fields\FieldRegistry;
use BsFabric\Fields\FieldRepository;
use BsFabric\Tests\Integration\TestCase;

final class FieldRepositoryTest extends TestCase
{
    private FieldRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new FieldRepository(new FieldRegistry());
    }

    public function testCreateField(): void
    {
        $fieldId = $this->repository->create([
            'name' => 'title',
            'label' => 'Title',
            'type' => 'text',
        ]);

        $this->assertGreaterThan(0, $fieldId);

        $field = $this->repository->getById($fieldId);

        $this->assertNotNull($field);
        $this->assertSame('title', $field['name']);
        $this->assertSame('Title', $field['label']);
        $this->assertSame('text', $field['type']);
    }

    public function testUpdateField(): void
    {
        $fieldId = $this->repository->create([
            'name' => 'summary',
            'label' => 'Summary',
            'type' => 'textarea',
        ]);

        $updated = $this->repository->update($fieldId, [
            'label' => 'Updated Summary',
            'settings' => ['rows' => 8],
        ]);

        $this->assertTrue($updated);

        $field = $this->repository->getById($fieldId);

        $this->assertNotNull($field);
        $this->assertSame('summary', $field['name']);
        $this->assertSame('Updated Summary', $field['label']);
        $this->assertSame(8, $field['settings']['rows']);
    }

    public function testDeleteField(): void
    {
        $fieldId = $this->repository->create([
            'name' => 'delete_me',
            'label' => 'Delete Me',
            'type' => 'text',
        ]);

        $this->assertTrue($this->repository->delete($fieldId));
        $this->assertNull($this->repository->getById($fieldId));
    }

    public function testDeleteFailsWhenFieldIsUsedInTemplate(): void
    {
        global $wpdb;

        $fieldId = $this->repository->create([
            'name' => 'locked_field',
            'label' => 'Locked Field',
            'type' => 'text',
        ]);

        $templatesTable = $wpdb->prefix . 'bs_fabric_templates';
        $templateFieldsTable = $wpdb->prefix . 'bs_fabric_template_fields';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$templatesTable} (name, label) VALUES (%s, %s)",
                'test_template',
                'Test Template'
            )
        );

        $templateId = (int) $wpdb->insert_id;

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$templateFieldsTable} (template_id, field_id, sort_order, is_required) VALUES (%d, %d, %d, %d)",
                $templateId,
                $fieldId,
                0,
                0
            )
        );

        $this->expectException(FieldInUseException::class);

        $this->repository->delete($fieldId);
    }

    public function testUniqueNameConstraint(): void
    {
        $this->repository->create([
            'name' => 'unique_name',
            'label' => 'Unique Name',
            'type' => 'text',
        ]);

        $this->expectException(DuplicateFieldNameException::class);

        $this->repository->create([
            'name' => 'unique_name',
            'label' => 'Duplicate',
            'type' => 'text',
        ]);
    }

    public function testGetAllReturnsFieldsOrderedByName(): void
    {
        $this->repository->create([
            'name' => 'zebra',
            'label' => 'Zebra',
            'type' => 'text',
        ]);
        $this->repository->create([
            'name' => 'alpha',
            'label' => 'Alpha',
            'type' => 'text',
        ]);

        $fields = $this->repository->getAll();

        $this->assertCount(2, $fields);
        $this->assertSame('alpha', $fields[0]['name']);
        $this->assertSame('zebra', $fields[1]['name']);
    }
}
