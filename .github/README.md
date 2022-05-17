# Mock Client

This testing client is [PSR-18](https://www.php-fig.org/psr/psr-18/) compliant, allowing you to use it in any scenario that you may swap out the client. Using this client you can ensure that your application makes the expected requests and mock responses.

Both HTTPlug's [Mock Client](https://github.com/php-http/mock-client) package and Laravel's [HTTP client](https://laravel.com/docs/http-client#testing) inspired this package. This package is an attempt to bridge them into a helpful class.

# Installation
To install this package, use Composer:

```bash
composer require aidan-casey/mock-client:dev-master
```

# Testing Requests
To begin testing with this client, create a new instance and pass it to any PSR-18 compatible service. After a request should have been made, use the assertion methods on the class directly to test that the client sent everything correctly.

For example:

```php
use AidanCasey\MockClient\Client;
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase
{
    public function test_it_makes_requests()
    {
        $client = new Client;
        $service = new MyCoolService($client);
        
        $service->makeRequest();
        
        $client->assertRequestsWereMade();   
    }
}
```

The following assertion methods are currently available:

- `assertUri`
- `assertMethod`
- `assertHeaderEquals`
- `assertBodyIs`
- `assertBodyIsEmpty`
- `assertBodyContains`
- `assertRequestsWereMade`
- `assertNoRequestsWereMade`

# Mocking Responses
Mocking responses allows you to ensure that your classes are parsing them correctly. Several helpers bundled with this client will make that process easier.

## Response Method
The static `response` method assists in building a PSR-7 response to be returned when making specific requests. This helper accepts three parameters: the body, the status code, and the headers.

For example:

```php
use AidanCasey\MockClient\Client;

// This will return a body with the string "Hello, world"
Client::response('Hello, world', 200, [
    'test-header' => 'test-value'
]);

// This will return the contents of the file path.
Client::response(__DIR__ . '/stubs/response.json');

// This will return the array in JSON form.
Client::response(['key' => 'value']);
```

## Fake Method
The static `fake` method lets you map certain URLs to specific responses. Pass in an array with your URLs as keys and your PSR-7 responses as values.

For example:

```php
use AidanCasey\MockClient\Client;

Client::fake([
    'https://github.com' => Client::response('Hello!'),
]);

// You may also use wildcards at any point in the URL.
Client::fake([
    'https://github.com/aidan-casey/*' => Client::response('Hello Aidan'),
    'https://github.com/*/mock-client' => Client::response('Hello, Stranger'),
]);
```

## Multiple Responses

The static `sequence` and `random` methods allow you to pass a series of responses in sequential or random order. These methods can be helpful if you want to put the resiliency of your service to the test!

For example:

```php
use AidanCasey\MockClient\Client;

Client::fake([
    'https://github.com/*' => Client::sequence([
        Client::response(null, 301),
        Client::response(null, 201),
        Client::response(null, 404),
    ]),
    
    'https://bing.com' => Client::random([
        Client::response(null, 401),
        Client::response(null, 403),
        Client::response(null, 404),
    ])
]);
```