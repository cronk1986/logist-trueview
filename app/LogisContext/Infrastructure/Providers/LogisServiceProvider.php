<?php

declare(strict_types=1);

namespace Logis\LogisContext\Infrastructure\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Logis\LogisContext\Application\Command\LogIn\LogInHandler;
use Logis\LogisContext\Application\Command\RotateAccessToken\RotateAccessTokenHandler;
use Logis\LogisContext\Domain\Model\AccessToken\AccessTokenRepository;
use Logis\LogisContext\Domain\Model\Contract\ContractRepository;
use Logis\LogisContext\Domain\Model\Customer\CustomerRepository;
use Logis\LogisContext\Domain\Model\Location\LocationRepository;
use Logis\LogisContext\Domain\Model\TicketStatus\TicketStatusRepository;
use Logis\LogisContext\Domain\Model\User\PasswordHasher;
use Logis\LogisContext\Domain\Model\User\UserRepository;
use Logis\LogisContext\Infrastructure\Http\PlainTextTokenGenerator;
use Logis\LogisContext\Infrastructure\Persistence\AccessToken\AccessTokenModel;
use Logis\LogisContext\Infrastructure\Persistence\AccessToken\EloquentAccessTokenRepository;
use Logis\LogisContext\Infrastructure\Persistence\Contract\EloquentContractRepository;
use Logis\LogisContext\Infrastructure\Persistence\Customer\EloquentCustomerRepository;
use Logis\LogisContext\Infrastructure\Persistence\Location\EloquentLocationRepository;
use Logis\LogisContext\Infrastructure\Persistence\TicketStatus\EloquentTicketStatusRepository;
use Logis\LogisContext\Infrastructure\Persistence\User\BcryptPasswordHasher;
use Logis\LogisContext\Infrastructure\Persistence\User\EloquentUserRepository;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;

final class LogisServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepository::class => EloquentUserRepository::class,
        AccessTokenRepository::class => EloquentAccessTokenRepository::class,
        PasswordHasher::class => BcryptPasswordHasher::class,
        LocationRepository::class => EloquentLocationRepository::class,
        ContractRepository::class => EloquentContractRepository::class,
        TicketStatusRepository::class => EloquentTicketStatusRepository::class,
        CustomerRepository::class => EloquentCustomerRepository::class,
    ];

    public function register(): void
    {
        $this->app->when(concrete: [LogInHandler::class, RotateAccessTokenHandler::class])
            ->needs(abstract: '$ttlMinutes')
            ->giveConfig(key: 'access_tokens.ttl_minutes');

        $this->app->when(concrete: RotateAccessTokenHandler::class)
            ->needs(abstract: '$graceSeconds')
            ->giveConfig(key: 'access_tokens.grace_seconds');

        $this->app->bind(abstract: PlainTextTokenGenerator::class, concrete: fn (Application $app) => new PlainTextTokenGenerator(
            prefix: (string) $app->make(abstract: 'config')->get(key: 'sanctum.token_prefix', default: ''),
        ));
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(map: ['user' => UserModel::class]);
        Sanctum::usePersonalAccessTokenModel(model: AccessTokenModel::class);
    }
}
