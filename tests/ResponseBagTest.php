<?php

namespace AidanCasey\MockClient\Tests;

use AidanCasey\MockClient\ResponseBag;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;

class ResponseBagTest extends TestCase
{
    public function test_it_will_retrieve_responses_in_sequence()
    {
        $responseFactory = new HttpFactory();

        $sequence = [
            $responseFactory->createResponse(200, 'This is a test.'),
            $responseFactory->createResponse(201, 'This is another test.'),
        ];

        $responseBag = new ResponseBag($sequence);

        $this->assertSame($sequence[0], $responseBag->getNextResponse());
        $this->assertSame($sequence[1], $responseBag->getNextResponse());
        $this->assertSame($sequence[0], $responseBag->getNextResponse());
        $this->assertSame($sequence[1], $responseBag->getNextResponse());
    }

    public function test_it_will_retrieve_responses_randomly()
    {
        $responseFactory = new HttpFactory();

        $sequence = [
            $responseFactory->createResponse(200, 'This is a test.'),
            $responseFactory->createResponse(201, 'This is another test.'),
        ];

        $responseBag = (new ResponseBag($sequence))->randomize();

        $this->assertContains($responseBag->getNextResponse(), $sequence);
        $this->assertContains($responseBag->getNextResponse(), $sequence);
        $this->assertContains($responseBag->getNextResponse(), $sequence);
        $this->assertContains($responseBag->getNextResponse(), $sequence);
    }
}