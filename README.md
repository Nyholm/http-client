# Nyholm's HTTP client

[![Latest Version](https://img.shields.io/github/release/nyholm/http-client.svg?style=flat-square)](https://github.com/nyholm/http-client/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Total Downloads](https://img.shields.io/packagist/dt/nyholm/http-client.svg?style=flat-square)](https://packagist.org/packages/nyholm/http-client)

**A super light HTTP client that sends PSR-7 HTTP messages.**


## Install

Via Composer

``` bash
$ composer require nyholm/http-client
```

## Usage

```php
// Create a PSR-7 request
$request = MessageFactoryDiscovery::find()->createRequest('GET', 'http://example.com');

// You will get back a PSR-7 response
$response = (new Client)->sendRequest($request);
```

## Documentation

There is no configuration of any kind that could be done to this client. If you want more functionality you should
use Plugins to HTTPlug. See their [documentation](http://docs.php-http.org/en/latest/httplug/introduction.html).

The MIT License (MIT). Please see [License File](LICENSE) for more information.
