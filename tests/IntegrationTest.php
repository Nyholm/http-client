<?php

namespace tests\Nyholm\HttpClient;

use Http\Client\Tests\HttpClientTest;
use Nyholm\HttpClient\Client;

class IntegrationTest extends HttpClientTest
{
    protected function createHttpAdapter()
    {
        return new Client();
    }
}
