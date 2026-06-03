<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Admin\Ajax;

use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use RuntimeException;

abstract class AjaxTestCase extends TestCase
{
    /** @var array<string, mixed>|null */
    protected ?array $lastJsonSuccess = null;

    /** @var array{data: array<string, mixed>, code: int}|null */
    protected ?array $lastJsonError = null;

    protected function setUpAjaxStubs(): void
    {
        Functions\when('__')->returnArg(1);
        Functions\when('wp_unslash')->returnArg(1);
        Functions\when('sanitize_key')->alias(
            static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '')
        );
        Functions\when('sanitize_text_field')->returnArg(1);

        Functions\when('wp_send_json_success')->alias(function (array $data): void {
            throw new JsonSuccessException($data);
        });
        Functions\when('wp_send_json_error')->alias(function (array $data, int $code = 400): void {
            throw new JsonErrorException($data, $code);
        });
    }

    protected function expectJsonSuccess(callable $callback): array
    {
        try {
            $callback();
        } catch (JsonSuccessException $exception) {
            return $exception->data;
        }

        self::fail('Expected wp_send_json_success() to be called.');
    }

    protected function expectJsonError(callable $callback, int $expectedCode): array
    {
        try {
            $callback();
        } catch (JsonErrorException $exception) {
            self::assertSame($expectedCode, $exception->statusCode);

            return $exception->data;
        }

        self::fail('Expected wp_send_json_error() to be called.');
    }
}

final class JsonSuccessException extends RuntimeException
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public readonly array $data)
    {
        parent::__construct('wp_send_json_success');
    }
}

final class JsonErrorException extends RuntimeException
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public readonly array $data, public readonly int $statusCode)
    {
        parent::__construct('wp_send_json_error');
    }
}
