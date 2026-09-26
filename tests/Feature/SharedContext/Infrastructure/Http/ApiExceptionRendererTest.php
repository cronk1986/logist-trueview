<?php

declare(strict_types=1);

namespace Tests\Feature\SharedContext\Infrastructure\Http;

use Carbon\CarbonImmutable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Logis\SharedContext\Infrastructure\Http\ApiExceptionRenderer;
use Logis\SharedContext\Domain\Exception\ConflictException;
use Logis\SharedContext\Domain\Exception\ForbiddenException;
use Logis\SharedContext\Domain\Exception\InvalidValueException;
use Logis\SharedContext\Domain\Exception\NotFoundException;
use Logis\SharedContext\Domain\Exception\TimeoutException;
use Logis\SharedContext\Domain\Exception\UnauthorizedException;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class ApiExceptionRendererTest extends TestCase
{
    public function test_an_unknown_route_returns_404_in_the_standard_format(): void
    {
        $this->freezeTime();

        $this->getJson(uri: '/api/v1/unknown')
            ->assertNotFound()
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonPath(path: 'data', expect: [])
            ->assertJsonPath(path: 'message', expect: 'Not Found')
            ->assertJsonPath(path: 'meta.timestamp', expect: CarbonImmutable::now()->utc()->toIso8601ZuluString())
            ->assertJsonPath(path: 'meta.version', expect: '1.0')
            ->assertJsonStructure(structure: ['meta' => ['execution_time_ms']]);
    }

    public function test_a_wrong_method_returns_405(): void
    {
        Route::middleware('api')->post('/api/v1/test/resource', fn () => []);

        $this->getJson(uri: '/api/v1/test/resource')
            ->assertMethodNotAllowed()
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonPath(path: 'message', expect: 'Method Not Allowed');
    }

    public function test_a_validation_error_returns_422_with_the_errors_in_data(): void
    {
        Route::middleware('api')->post(
            '/api/v1/test/validation',
            fn () => Validator::make(['email' => 'nope'], ['email' => ['email']])->validate(),
        );

        $this->postJson(uri: '/api/v1/test/validation')
            ->assertUnprocessable()
            ->assertJsonPath(path: 'success', expect: false)
            ->assertJsonPath(path: 'message', expect: 'Validation failed')
            ->assertJsonPath(path: 'data.email.0', expect: 'The email field must be a valid email address.');
    }

    public function test_an_authentication_error_returns_401(): void
    {
        $this->routeThrowing(exception: new AuthenticationException());

        $this->getJson(uri: '/api/v1/test/throw')
            ->assertUnauthorized()
            ->assertJsonPath(path: 'message', expect: 'Unauthenticated');
    }

    public function test_domain_exceptions_map_to_their_status_with_their_message(): void
    {
        $cases = [
            401 => new class (message: 'Invalid credentials') extends UnauthorizedException {},
            403 => new class (message: 'Not your view') extends ForbiddenException {},
            404 => new class (message: 'Resource not found') extends NotFoundException {},
            409 => new class (message: 'Already rotated') extends ConflictException {},
            422 => new class (message: 'Invalid email') extends InvalidValueException {},
            504 => new class (message: 'Too slow') extends TimeoutException {},
        ];

        foreach ($cases as $status => $exception) {
            $this->routeThrowing(exception: $exception);

            $this->getJson(uri: '/api/v1/test/throw')
                ->assertStatus(status: $status)
                ->assertJsonPath(path: 'success', expect: false)
                ->assertJsonPath(path: 'message', expect: $exception->getMessage());
        }
    }

    public function test_timeouts_are_reported_and_other_domain_exceptions_are_not(): void
    {
        $expected = ApiExceptionRenderer::expectedExceptions();

        $this->assertContains(ForbiddenException::class, $expected);
        $this->assertContains(NotFoundException::class, $expected);
        $this->assertNotContains(TimeoutException::class, $expected);
    }

    public function test_an_unexpected_error_returns_500_without_details(): void
    {
        $this->routeThrowing(exception: new RuntimeException(message: 'Database password is secret'));

        $this->getJson(uri: '/api/v1/test/throw')
            ->assertInternalServerError()
            ->assertJsonPath(path: 'message', expect: 'Server error')
            ->assertDontSee(value: 'secret');
    }

    public function test_the_response_meta_has_timestamp_version_and_execution_time(): void
    {
        $this->routeThrowing(exception: new AuthenticationException());

        $this->getJson(uri: '/api/v1/test/throw')
            ->assertJsonPath(path: 'meta.version', expect: '1.0')
            ->assertJsonStructure(structure: ['meta' => ['timestamp', 'version', 'execution_time_ms']]);
    }

    private function routeThrowing(Throwable $exception): void
    {
        Route::middleware('api')->get('/api/v1/test/throw', fn () => throw $exception);
    }
}
