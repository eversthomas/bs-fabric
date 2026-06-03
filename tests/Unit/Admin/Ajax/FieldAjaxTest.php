<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Admin\Ajax;

use BsFabric\Admin\Ajax\FieldAjax;
use BsFabric\Fields\FieldAdminOperations;
use BsFabric\Fields\FieldInUseException;
use BsFabric\Fields\FieldNotFoundException;
use Brain\Monkey\Functions;
use Mockery;

final class FieldAjaxTest extends AjaxTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAjaxStubs();
    }

    public function testDeleteFieldRequiresCapability(): void
    {
        Functions\when('current_user_can')->justReturn(false);

        $_POST = ['nonce' => 'valid'];

        $ajax = new FieldAjax(Mockery::mock(FieldAdminOperations::class));

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteField(), 403);

        self::assertSame('Keine Berechtigung.', $payload['message']);
    }

    public function testDeleteFieldReturnsInUseError(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(FieldAjax::NONCE_ACTION, 'nonce');

        $_POST = ['nonce' => 'valid', 'field_id' => '3'];

        $fieldRepository = Mockery::mock(FieldAdminOperations::class);
        $fieldRepository->shouldReceive('delete')->once()->with(3)->andThrow(FieldInUseException::class);

        $ajax = new FieldAjax($fieldRepository);

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteField(), 409);

        self::assertStringContainsString('Template', $payload['message']);
    }

    public function testDeleteFieldReturnsNotFound(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(FieldAjax::NONCE_ACTION, 'nonce');

        $_POST = ['nonce' => 'valid', 'field_id' => '99'];

        $fieldRepository = Mockery::mock(FieldAdminOperations::class);
        $fieldRepository->shouldReceive('delete')->once()->with(99)->andThrow(FieldNotFoundException::class);

        $ajax = new FieldAjax($fieldRepository);

        $payload = $this->expectJsonError(static fn (): mixed => $ajax->deleteField(), 404);

        self::assertSame('Feld nicht gefunden.', $payload['message']);
    }

    public function testDeleteFieldSucceeds(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        Functions\expect('check_ajax_referer')->once()->with(FieldAjax::NONCE_ACTION, 'nonce');

        $_POST = ['nonce' => 'valid', 'field_id' => '5'];

        $fieldRepository = Mockery::mock(FieldAdminOperations::class);
        $fieldRepository->shouldReceive('delete')->once()->with(5);

        $ajax = new FieldAjax($fieldRepository);

        $payload = $this->expectJsonSuccess(static fn (): mixed => $ajax->deleteField());

        self::assertSame('Feld wurde gelöscht.', $payload['message']);
    }
}
