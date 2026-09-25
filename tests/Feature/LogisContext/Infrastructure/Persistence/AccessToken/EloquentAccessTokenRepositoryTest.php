<?php

declare(strict_types=1);

namespace Tests\Feature\LogisContext\Infrastructure\Persistence\AccessToken;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\LogisContext\Infrastructure\Persistence\AccessToken\EloquentAccessTokenRepository;
use Logis\LogisContext\Infrastructure\Persistence\User\UserModel;
use Tests\TestCase;
use Tests\Unit\LogisContext\Domain\Model\AccessToken\AccessTokenMother;

final class EloquentAccessTokenRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentAccessTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(abstract: EloquentAccessTokenRepository::class);
    }

    public function test_successors_are_found_by_their_predecessor(): void
    {
        $user = UserModel::factory()->create();
        $this->repository->save(accessToken: AccessTokenMother::create(plainTextToken: 'successor', userId: $user->id, rotatedFrom: 'token'));
        $this->repository->save(accessToken: AccessTokenMother::create(plainTextToken: 'unrelated', userId: $user->id));

        $successors = $this->repository->findRotatedFrom(hash: PlainTextToken::fromString(value: 'token')->hash());

        $this->assertCount(1, $successors);
        $this->assertTrue($successors[0]->hash()->equals(other: PlainTextToken::fromString(value: 'successor')->hash()));
    }

    public function test_a_saved_token_is_found_by_its_hash(): void
    {
        $user = UserModel::factory()->create();
        $this->repository->save(accessToken: AccessTokenMother::create(plainTextToken: 'token', userId: $user->id, rotatedFrom: 'predecessor'));

        $accessToken = $this->repository->findByHash(hash: PlainTextToken::fromString(value: 'token')->hash());

        $this->assertNotNull($accessToken);
        $this->assertSame($user->id, $accessToken->userId()->value());
        $this->assertTrue($accessToken->rotatedFrom()?->equals(other: PlainTextToken::fromString(value: 'predecessor')->hash()));
    }

    public function test_a_claim_based_on_a_stale_read_is_rejected(): void
    {
        $user = UserModel::factory()->create();
        $this->repository->save(accessToken: AccessTokenMother::create(plainTextToken: 'token', userId: $user->id));
        $hash = PlainTextToken::fromString(value: 'token')->hash();
        $firstRead = $this->repository->findByHash(hash: $hash);
        $secondRead = $this->repository->findByHash(hash: $hash);
        $this->assertNotNull($firstRead);
        $this->assertNotNull($secondRead);
        $firstRead->rotate(successorHash: PlainTextToken::fromString(value: 'first')->hash(), ipAddress: '127.0.0.1', userAgent: 'PHPUnit', ttlMinutes: 60, graceSeconds: 10);
        $secondRead->rotate(successorHash: PlainTextToken::fromString(value: 'second')->hash(), ipAddress: '127.0.0.1', userAgent: 'PHPUnit', ttlMinutes: 60, graceSeconds: 10);

        $this->assertTrue($this->repository->claimRotation(rotatedAccessToken: $firstRead));
        $this->assertFalse($this->repository->claimRotation(rotatedAccessToken: $secondRead));
    }
}
