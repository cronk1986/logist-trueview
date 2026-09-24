<?php

declare(strict_types=1);

namespace Logis\SharedContext\Infrastructure\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Logis\SharedContext\Domain\Event\DomainEventPublisher;
use Logis\SharedContext\Infrastructure\Bus\CommandBus;
use Logis\SharedContext\Infrastructure\Bus\CommandBusInterface;
use Logis\SharedContext\Infrastructure\Bus\HandlerResolver;
use Logis\SharedContext\Infrastructure\Bus\QueryBus;
use Logis\SharedContext\Infrastructure\Bus\QueryBusInterface;
use Logis\SharedContext\Infrastructure\BusMiddleware\DomainEventMiddleware;
use Logis\SharedContext\Infrastructure\BusMiddleware\TransactionMiddleware;
use Logis\SharedContext\Infrastructure\Event\DomainEventDispatcher;

final class SharedServiceProvider extends ServiceProvider
{
    private const API_REQUESTS_PER_MINUTE = 60;

    public function register(): void
    {
        $this->app->singleton(abstract: CommandBusInterface::class, concrete: fn (Container $app) => new CommandBus(
            container: $app,
            handlers: $app->make(abstract: HandlerResolver::class),
            middleware: [TransactionMiddleware::class, DomainEventMiddleware::class],
        ));

        $this->app->singleton(abstract: QueryBusInterface::class, concrete: QueryBus::class);
    }

    public function boot(): void
    {
        $this->bootDomainEventPublisher();
        $this->bootApiRateLimiter();

        Queue::before(callback: fn () => $this->bootDomainEventPublisher());
    }

    private function bootApiRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user(guard: 'sanctum');
            $key = $user instanceof Authenticatable ? 'user:' . $user->getAuthIdentifier() : 'ip:' . $request->ip();

            return Limit::perMinute(maxAttempts: self::API_REQUESTS_PER_MINUTE)->by(key: $key);
        });
    }

    private function bootDomainEventPublisher(): void
    {
        DomainEventPublisher::reset();
        DomainEventPublisher::instance()->subscribe(subscriber: $this->app->make(abstract: DomainEventDispatcher::class));
    }
}
