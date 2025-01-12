<?php
declare(strict_types=1);

namespace NewsApiPlugin\Tests\Services;

use PHPUnit\Framework\TestCase;
use NewsApiPlugin\Services\NewsApiFetcherService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

class NewsFetcherServiceTest extends TestCase
{
    public function testFetchNewsReturnsArticles(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'articles' => [
                    ['title' => 'Article 1', 'content' => 'Content 1'],
                    ['title' => 'Article 2', 'content' => 'Content 2']
                ]
            ])),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $service = new NewsApiFetcherService('dummy-api-key');
        $reflection = new \ReflectionProperty(NewsApiFetcherService::class, 'client');
        $reflection->setAccessible(true);
        $reflection->setValue($service, $client);

        $articles = $service->fetchNews(['q' => 'test']);
        $this->assertCount(2, $articles);
        $this->assertEquals('Article 1', $articles[0]['title']);
    }

    public function testFetchNewsHandlesError(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);
        $service = new NewsApiFetcherService('dummy-api-key');
        $reflection = new \ReflectionProperty(NewsApiFetcherService::class, 'client');
        $reflection->setAccessible(true);
        $reflection->setValue($service, $client);

        $this->expectException(GuzzleException::class);
        $service->fetchNews(['q' => 'test']);
    }
}
