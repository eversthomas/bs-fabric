<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Admin\Ajax;

use BsFabric\Admin\Ajax\TemplateAjax;
use BsFabric\Templates\Template;
use BsFabric\Templates\TemplateAdminOperations;
use BsFabric\Templates\TemplateInUseException;
use BsFabric\Templates\TemplateNotFoundException;
use Brain\Monkey\Functions;
use Mockery;

final class TemplateAjaxTest extends AjaxTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAjaxStubs();
    }

    public function testDeleteTemplateRequiresCapability(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $_POST = ['nonce' => 'valid'];

        $ajax = new TemplateAjax(Mockery::mock(TemplateAdminOperations::class));

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteTemplate(), 403);

        self::assertSame('Keine Berechtigung.', $payload['message']);
    }

    public function testDeleteTemplateRejectsInvalidId(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(TemplateAjax::NONCE_ACTION, 'nonce');

        $_POST = ['nonce' => 'valid', 'template_id' => '0'];

        $ajax = new TemplateAjax(Mockery::mock(TemplateAdminOperations::class));

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteTemplate(), 400);

        self::assertSame('Ungültige Template-ID.', $payload['message']);
    }

    public function testDeleteTemplateReturnsInUseError(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(TemplateAjax::NONCE_ACTION, 'nonce');

        $_POST = ['nonce' => 'valid', 'template_id' => '2'];

        $templateRepository = Mockery::mock(TemplateAdminOperations::class);
        $templateRepository->shouldReceive('delete')->once()->with(2)->andThrow(TemplateInUseException::class);

        $ajax = new TemplateAjax($templateRepository);

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteTemplate(), 409);

        self::assertStringContainsString('Entries', $payload['message']);
    }

    public function testAssignFieldReturnsFieldsOnSuccess(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(TemplateAjax::NONCE_ACTION, 'nonce');

        $_POST = [
            'nonce' => 'valid',
            'template_id' => '1',
            'field_id' => '4',
            'is_required' => '1',
        ];

        $fields = [
            ['field_id' => 4, 'is_required' => true],
        ];

        $templateRepository = Mockery::mock(TemplateAdminOperations::class);
        $templateRepository->shouldReceive('getById')->once()->with(1)->andReturn(new Template([
            'id' => 1,
            'name' => 'demo',
            'label' => 'Demo',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]));
        $templateRepository->shouldReceive('assignField')->once()->with(1, 4, true);
        $templateRepository->shouldReceive('getFields')->once()->with(1)->andReturn($fields);

        $ajax = new TemplateAjax($templateRepository);

        $payload = $this->expectJsonSuccess(static fn (): mixed => $ajax->assignField());

        self::assertSame($fields, $payload['fields']);
    }

    public function testRemoveFieldReturnsNotFound(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(TemplateAjax::NONCE_ACTION, 'nonce');

        $_POST = [
            'nonce' => 'valid',
            'template_id' => '1',
            'field_id' => '4',
        ];

        $templateRepository = Mockery::mock(TemplateAdminOperations::class);
        $templateRepository->shouldReceive('getById')->once()->with(1)->andReturn(new Template([
            'id' => 1,
            'name' => 'demo',
            'label' => 'Demo',
            'title_field' => null,
            'settings' => [],
            'created_at' => '2026-01-01 00:00:00',
        ]));
        $templateRepository->shouldReceive('removeField')->once()->with(1, 4)->andThrow(TemplateNotFoundException::class, 'missing');

        $ajax = new TemplateAjax($templateRepository);

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->removeField(), 404);

        self::assertSame('missing', $payload['message']);
    }
}
