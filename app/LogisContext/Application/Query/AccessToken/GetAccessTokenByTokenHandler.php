<?php

declare(strict_types=1);

namespace Logis\LogisContext\Application\Query\AccessToken;

use Logis\LogisContext\Application\ModelView\AccessToken\AccessTokenView;
use Logis\LogisContext\Domain\Exception\AccessToken\AccessTokenNotFoundException;
use Logis\LogisContext\Domain\Model\AccessToken\AccessTokenRepository;
use Logis\LogisContext\Domain\ValueObject\AccessToken\PlainTextToken;
use Logis\SharedContext\Domain\QueryResponse\QueryResponse;

final class GetAccessTokenByTokenHandler
{
    public function __construct(private readonly AccessTokenRepository $accessTokens)
    {
    }

    public function handle(GetAccessTokenByToken $query): QueryResponse
    {
        $plainTextToken = PlainTextToken::fromString(value: $query->token);
        $accessToken = $this->accessTokens->findByHash(hash: $plainTextToken->hash());

        if ($accessToken === null) {
            throw AccessTokenNotFoundException::create();
        }

        return QueryResponse::from(data: AccessTokenView::from(plainTextToken: $plainTextToken, accessToken: $accessToken)->toArray());
    }
}
