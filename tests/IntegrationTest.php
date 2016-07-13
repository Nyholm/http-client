<?php

namespace tests\Nyholm\HttpClient;

use GuzzleHttp\Psr7\Request;
use Http\Adapter\Guzzle6\Tests\HttpAdapterTest;
use Http\Client\Tests\HttpClientTest;
use Nyholm\HttpClient\Client;

class IntegrationTest extends HttpClientTest
{
    protected function createHttpAdapter()
    {
        return new Client();
    }
}