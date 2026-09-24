<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param array<mixed> $parameters
     * @param array<mixed> $cookies
     * @param array<mixed> $files
     * @param array<mixed> $server
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->app->make(abstract: 'auth')->forgetGuards();

        return parent::call(method: $method, uri: $uri, parameters: $parameters, cookies: $cookies, files: $files, server: $server, content: $content);
    }
}
