<?php

namespace AidanCasey\MockClient;

use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\Assert as PHPUnit;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Client implements ClientInterface
{
    private readonly ResponseFactoryInterface $responseFactory;

    private readonly StreamFactoryInterface $streamFactory;

    /** @var array<array-key,RequestInterface> $requests */
    private array $requests = [];

    private RequestInterface $lastRequest;

    /** @var array<string,ResponseInterface|ResponseBag> $fakedResponses */
    private array $fakedResponses = [];

    /** @var array<string,ResponseInterface|ResponseBag> $fakedResponses */
    private array $fakedWildcardResponses = [];

    public function __construct(?ResponseFactoryInterface $responseFactory = null, ?StreamFactoryInterface $streamFactory = null)
    {
        $this->responseFactory = $responseFactory ?: Psr17FactoryDiscovery::findResponseFactory();
        $this->streamFactory = $streamFactory ?: Psr17FactoryDiscovery::findStreamFactory();
    }

    /**
     * @param array<string,ResponseInterface|ResponseBag> $map
     */
    public static function fake(array $map = []): self
    {
        return (new self)->setResponses($map);
    }

    /**
     * @param array<array-key,ResponseInterface> $responses
     */
    public static function sequence(array $responses = []): ResponseBag
    {
        return new ResponseBag($responses);
    }

    /**
     * @param array<array-key,ResponseInterface> $responses
     */
    public static function random(array $responses = []): ResponseBag
    {
        return self::sequence($responses)->randomize();
    }

    /**
     * @param array<string,string> $headers
     */
    public static function response(string|array $body = null, int $code = 200, array $headers = []): ResponseInterface
    {
        $client = (new self);
        $response = $client->responseFactory->createResponse($code);
        $body = is_array($body) ? json_encode($body) : $body;

        if ($body !== null) {
            $stream = is_file($body)
                ? $client->streamFactory->createStreamFromFile($body)
                : $client->streamFactory->createStream($body);

            $response = $response->withBody($stream);
        }

        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        return $response;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;
        $this->requests[] = $request;

        if ($response = $this->resolveFakeResponse($request)) {
            return $response;
        }

        if ($response = $this->resolveWildcardFakeResponse($request)) {
            return $response;
        }

        return $this->responseFactory->createResponse();
    }

    public function assertUri(string $uri): self
    {
        $this->assertRequestsWereMade();

        PHPUnit::assertSame(
            strtolower($uri), strtolower($this->lastRequest->getUri()->__toString())
        );

        return $this;
    }

    public function assertMethod(string $method): self
    {
        $this->assertRequestsWereMade();

        PHPUnit::assertSame(
            strtoupper($method), strtoupper($this->lastRequest->getMethod())
        );

        return $this;
    }

    public function assertHeaderEquals(string $header, mixed $value): self
    {
        $this->assertRequestsWereMade();

        PHPUnit::assertSame(
            $value, $this->lastRequest->getHeaderLine($header)
        );

        return $this;
    }

    public function assertBodyIs(string $content): self
    {
        $this->assertRequestsWereMade();

        PHPUnit::assertSame(
            $content, $this->lastRequest->getBody()->getContents()
        );

        return $this;
    }

    public function assertBodyIsEmpty(): self
    {
        return $this->assertBodyIs('');
    }

    public function assertBodyContains(string $content): self
    {
        $this->assertRequestsWereMade();

        PHPUnit::assertStringContainsString(
            $content, $this->lastRequest->getBody()->getContents()
        );

        return $this;
    }

    public function assertRequestsWereMade(?int $count = null): self
    {
        if ($count) {
            PHPUnit::assertCount($count, $this->requests);
        } else {
            PHPUnit::assertNotEmpty($this->requests);
        }

        return $this;
    }

    public function assertNoRequestsWereMade(): self
    {
        PHPUnit::assertEmpty($this->requests);

        return $this;
    }

    /**
     * @param array<string,ResponseInterface|ResponseBag> $responses
     */
    private function setResponses(array $responses): self
    {
        foreach ($responses as $uri => $response) {
            $this->setResponse($uri, $response);
        }

        return $this;
    }

    private function setResponse(string $uri, ResponseInterface|ResponseBag $response): self
    {
        if (str_contains($uri, '*')) {
            $this->fakedWildcardResponses[$uri] = $response;
        } else {
            $this->fakedResponses[$uri] = $response;
        }

        return $this;
    }

    private function resolveFakeResponse(RequestInterface $request): ?ResponseInterface
    {
        foreach ($this->fakedResponses as $uri => $fakedResponse) {
            if (strtolower($uri) !== strtolower($request->getUri()->__toString())) {
                continue;
            }

            return ($fakedResponse instanceof ResponseBag)
                ? $fakedResponse->getNextResponse()
                : $fakedResponse;
        }

        return null;
    }

    private function resolveWildcardFakeResponse(RequestInterface $request): ?ResponseInterface
    {
        foreach ($this->fakedWildcardResponses as $url => $fakedWildcardResponse) {
            $url = str_replace('\*', '.*', preg_quote($url, '/'));

            if (! preg_match("/$url/i", $request->getUri()->__toString())) {
                continue;
            }

            return ($fakedWildcardResponse instanceof ResponseBag)
                ? $fakedWildcardResponse->getNextResponse()
                : $fakedWildcardResponse;
        }

        return null;
    }
}
