<?php
// tests/Commands/NewsApiRunnerCommandTest.php

declare(strict_types=1);

namespace NewsApiPlugin\Tests\Commands;

use Brain\Monkey;
use Brain\Monkey\WP\Actions;
use Brain\Monkey\WP\Filters;
use NewsApiPlugin\Commands\NewsApiRunnerCommand;
use NewsApiPlugin\Services\NewsApiFetcherService;
use NewsApiPlugin\Services\WPNewsPublisherService;
use PHPUnit\Framework\TestCase;
use WP_CLI;

class NewsApiRunnerCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test that the command outputs an error when the API key is missing.
     */
    public function testInvokeWithoutApiKey()
    {
        // Mock get_option to return false (no API key)
        Monkey\Functions\expect('get_option')
            ->once()
            ->with('newsapi_api_key')
            ->andReturn(false);

        // Expect WP_CLI::error to be called with specific message
        Monkey\Functions\expect('WP_CLI::error')
            ->once()
            ->with('API Key is not configured.')
            ->andReturnUsing(function ($message) {
                // To simulate WP_CLI::error behavior, throw an exception
                throw new \Exception($message);
            });

        $command = new NewsApiRunnerCommand();

        // Expect an exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API Key is not configured.');

        // Invoke the command
        $command->__invoke([], []);
    }

    /**
     * Test that the command successfully fetches and publishes news.
     */
    public function testInvokeWithApiKey()
    {
        // Mock get_option to return a valid API key
        Monkey\Functions\expect('get_option')
            ->once()
            ->with('newsapi_api_key')
            ->andReturn('valid_api_key');

        // Mock WP_CLI::success to be called with specific message
        Monkey\Functions\expect('WP_CLI::success')
            ->once()
            ->with('News fetched and stored as draft posts.');

        // Prepare mock data
        $articles = [
            [
                'title' => 'Test Article 1',
                'content' => 'Content for test article 1.',
            ],
            [
                'title' => 'Test Article 2',
                'content' => 'Content for test article 2.',
            ],
        ];

        // Mock NewsFetcherService
        $newsFetcherMock = $this->createMock(NewsApiFetcherService::class);
        $newsFetcherMock->expects($this->once())
            ->method('fetchNews')
            ->with(
                [
                    'q' => 'technology',
                    'language' => 'en',
                ],
                true
            )
            ->willReturn($articles);

        // Mock WPNewsPublisherService
        $publisherMock = $this->createMock(WPNewsPublisherService::class);
        $publisherMock->expects($this->once())
            ->method('publishNews')
            ->with($articles, 1);

        // Replace the service instantiation with mocks
        // Assuming NewsFetcherService and WPNewsPublisherService are injected or replaceable
        // If not, you might need to refactor the command to allow dependency injection
        // For this example, we'll assume the command can accept service instances

        // Alternatively, use dependency injection via a factory or service container

        // To proceed, we'll need to mock the instantiation inside the command
        // Since the original command creates new instances directly, we have to adjust our approach
        // One way is to use Monkey to mock the constructor or methods

        // However, Brain Monkey does not mock object instantiation
        // Therefore, it's better to refactor the command to accept service instances

        // **Refactoring Suggestion:**
        // Modify the NewsApiRunnerCommand to accept NewsFetcherService and WPNewsPublisherService as dependencies

        // For the purpose of this test, let's assume the command has been refactored as follows:

        // Adjusted NewsApiRunnerCommand::__invoke to accept services as optional parameters (for testing)

        // Here's how to proceed:

        // Create a partial mock of NewsApiRunnerCommand to inject mocks
        $commandMock = $this->getMockBuilder(NewsApiRunnerCommand::class)
            ->onlyMethods(['createNewsFetcherService', 'createPublisherService'])
            ->getMock();

        $commandMock->expects($this->once())
            ->method('createNewsFetcherService')
            ->with('valid_api_key')
            ->willReturn($newsFetcherMock);

        $commandMock->expects($this->once())
            ->method('createPublisherService')
            ->willReturn($publisherMock);

        // To implement this, we need to adjust the NewsApiRunnerCommand class accordingly
        // **Adjusted NewsApiRunnerCommand:**

        // Here's the adjusted class with factory methods (this is just for illustration)
        /*
        class NewsApiRunnerCommand
        {
            public function __invoke(array $args, array $assocArgs): void
            {
                $apiKey = get_option('newsapi_api_key');
                if (!$apiKey) {
                    WP_CLI::error('API Key is not configured.');
                    return;
                }

                $categoryId = (int) ($assocArgs['category'] ?? 1);
                $parameters = [
                    'q' => $assocArgs['query'] ?? 'technology',
                    'language' => $assocArgs['language'] ?? 'en',
                ];

                $fetcher = $this->createNewsFetcherService($apiKey);
                $publisher = $this->createPublisherService();

                try {
                    $articles = $fetcher->fetchNews($parameters, true);
                    $publisher->publishNews($articles, $categoryId);
                    WP_CLI::success('News fetched and stored as draft posts.');
                } catch (\Exception $e) {
                    WP_CLI::error('Error fetching news: ' . $e->getMessage());
                }
            }

            protected function createNewsFetcherService(string $apiKey): NewsFetcherService
            {
                return new NewsFetcherService($apiKey);
            }

            protected function createPublisherService(): WPNewsPublisherService
            {
                return new WPNewsPublisherService();
            }
        }
        */

        // **Assuming the command has been refactored accordingly, proceed with the test:**

        // Invoke the command
        $commandMock->__invoke([], []);
    }

    /**
     * Test that the command handles exceptions from NewsFetcherService.
     */
    public function testInvokeNewsFetcherThrowsException()
    {
        // Mock get_option to return a valid API key
        Monkey\Functions\expect('get_option')
            ->once()
            ->with('newsapi_api_key')
            ->andReturn('valid_api_key');

        // Expect WP_CLI::error to be called with the exception message
        Monkey\Functions\expect('WP_CLI::error')
            ->once()
            ->with('Error fetching news: Fetching failed.')
            ->andReturnUsing(function ($message) {
                throw new \Exception($message);
            });

        // Mock NewsFetcherService to throw an exception
        $newsFetcherMock = $this->createMock(NewsApiFetcherService::class);
        $newsFetcherMock->expects($this->once())
            ->method('fetchNews')
            ->with(
                [
                    'q' => 'technology',
                    'language' => 'en',
                ],
                true
            )
            ->will($this->throwException(new \Exception('Fetching failed.')));

        // Mock WPNewsPublisherService should not be called
        $publisherMock = $this->createMock(WPNewsPublisherService::class);
        $publisherMock->expects($this->never())
            ->method('publishNews');

        // Create a partial mock of NewsApiRunnerCommand to inject mocks
        $commandMock = $this->getMockBuilder(NewsApiRunnerCommand::class)
            ->onlyMethods(['createNewsFetcherService', 'createPublisherService'])
            ->getMock();

        $commandMock->expects($this->once())
            ->method('createNewsFetcherService')
            ->with('valid_api_key')
            ->willReturn($newsFetcherMock);

        $commandMock->expects($this->never())
            ->method('createPublisherService');

        // Expect an exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error fetching news: Fetching failed.');

        // Invoke the command
        $commandMock->__invoke([], []);
    }

    /**
     * Test that the command handles exceptions from WPNewsPublisherService.
     */
    public function testInvokePublisherThrowsException()
    {
        // Mock get_option to return a valid API key
        Monkey\Functions\expect('get_option')
            ->once()
            ->with('newsapi_api_key')
            ->andReturn('valid_api_key');

        // Expect WP_CLI::error to be called with the exception message
        Monkey\Functions\expect('WP_CLI::error')
            ->once()
            ->with('Error fetching news: Publishing failed.')
            ->andReturnUsing(function ($message) {
                throw new \Exception($message);
            });

        // Prepare mock data
        $articles = [
            [
                'title' => 'Test Article 1',
                'content' => 'Content for test article 1.',
            ],
        ];

        // Mock NewsFetcherService
        $newsFetcherMock = $this->createMock(NewsApiFetcherService::class);
        $newsFetcherMock->expects($this->once())
            ->method('fetchNews')
            ->with(
                [
                    'q' => 'technology',
                    'language' => 'en',
                ],
                true
            )
            ->willReturn($articles);

        // Mock WPNewsPublisherService to throw an exception
        $publisherMock = $this->createMock(WPNewsPublisherService::class);
        $publisherMock->expects($this->once())
            ->method('publishNews')
            ->with($articles, 1)
            ->will($this->throwException(new \Exception('Publishing failed.')));

        // Create a partial mock of NewsApiRunnerCommand to inject mocks
        $commandMock = $this->getMockBuilder(NewsApiRunnerCommand::class)
            ->onlyMethods(['createNewsFetcherService', 'createPublisherService'])
            ->getMock();

        $commandMock->expects($this->once())
            ->method('createNewsFetcherService')
            ->with('valid_api_key')
            ->willReturn($newsFetcherMock);

        $commandMock->expects($this->once())
            ->method('createPublisherService')
            ->willReturn($publisherMock);

        // Expect an exception to be thrown
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error fetching news: Publishing failed.');

        // Invoke the command
        $commandMock->__invoke([], []);
    }
}
