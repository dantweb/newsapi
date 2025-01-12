<?php
declare(strict_types=1);

namespace NewsApiPlugin\Tests\Services;

use NewsApiPlugin\Services\RssReaderService;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * @group rss
 */
class RssReaderServiceTest extends TestCase
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

    public function test_read_url_success_no_limit(): void
    {
        // Create mock feed items
        $items = $this->createMockFeedItems([
            ['<b>Title 1</b>', '<p>Content 1</p>'],
            ['Title 2', '<div>Content 2</div>']
        ]);

        // Create a mock feed
        $feed = $this->createMockFeed(count($items), $items);

        // Define the fetch_feed callable to return the mock feed
        $fetch_feed_callable = function($url) use ($feed) {
            return $feed;
        };

        $rssReader = new RssReaderService($fetch_feed_callable);

        $articles = $rssReader->readUrl('http://example.com/feed');

        $this->assertCount(2, $articles);
        $this->assertEquals('Title 1', $articles[0]['title']);
        $this->assertEquals('<p>Content 1</p>', $articles[0]['content']);

        $this->assertEquals('Title 2', $articles[1]['title']);
        $this->assertEquals('<div>Content 2</div>', $articles[1]['content']);
    }

    public function test_read_url_with_amount_limit(): void
    {
        // Create mock feed items
        $items = $this->createMockFeedItems([
            ['Title 1', 'Content 1'],
            ['Title 2', 'Content 2'],
            ['Title 3', 'Content 3'],
        ]);

        // Create a mock feed
        $feed = $this->createMockFeed(count($items), $items);

        // Define the fetch_feed callable to return the mock feed
        $fetch_feed_callable = function($url) use ($feed) {
            return $feed;
        };

        $rssReader = new RssReaderService($fetch_feed_callable);

        $articles = $rssReader->readUrl('http://example.com/feed', 2);
        $this->assertCount(2, $articles);
        $this->assertEquals('Title 1', $articles[0]['title']);
        $this->assertEquals('Title 2', $articles[1]['title']);
    }

    public function test_read_url_error_fetching(): void
    {
        // Define the fetch_feed callable to return WP_Error
        $fetch_feed_callable = function($url) {
            return new \WP_Error('fetch_failed', 'Failed to fetch feed');
        };

        $rssReader = new RssReaderService($fetch_feed_callable);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error fetching RSS feed: Failed to fetch feed');

        $rssReader->readUrl('http://invalid-feed.com');
    }

    public function test_read_url_empty_feed(): void
    {
        // Define the fetch_feed callable to return a feed with 0 items
        $fetch_feed_callable = function($url) {
            $feed = $this->createMockFeed(0, []);
            return $feed;
        };

        $rssReader = new RssReaderService($fetch_feed_callable);

        $articles = $rssReader->readUrl('http://example.com/emptyfeed');
        $this->assertEmpty($articles);
    }

    /**
     * Helper to create a mock feed object with a set number of items.
     *
     * @param int $count The number of items.
     * @param array $items Each item is [title, content].
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function createMockFeed(int $count, array $items)
    {
        $feed = $this->getMockBuilder('SimplePie')->disableOriginalConstructor()->getMock();
        $feed->method('get_item_quantity')->willReturn($count);
        $feed->method('get_items')->willReturn($items);
        return $feed;
    }

    /**
     * Helper to create an array of mock feed items.
     *
     * @param array $data Each element is [title, content].
     * @return array
     */
    protected function createMockFeedItems(array $data): array
    {
        $items = [];
        foreach ($data as [$title, $content]) {
            $item = $this->getMockBuilder('SimplePie_Item')->disableOriginalConstructor()->getMock();
            $item->method('get_title')->willReturn($title);
            $item->method('get_content')->willReturn($content);
            $items[] = $item;
        }
        return $items;
    }
}
