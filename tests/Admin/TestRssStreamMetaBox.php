<?php
declare(strict_types=1);

namespace NewsApiPlugin\Tests;

use NewsApiPlugin\Admin\RSSStreamMetaBox;
use NewsApiPlugin\Services\RssReaderService;
use NewsApiPlugin\Services\WPNewsPublisherService;
use WP_UnitTestCase;
use WP_Post;

class TestRSSStreamMetaBox extends WP_UnitTestCase
{
    protected static $post_id;

    public static function wpSetUpBeforeClass($factory): void
    {
        // Create a test post of type 'newsapi_rss_stream'
        self::$post_id = $factory->post->create(['post_type' => 'newsapi_rss_stream']);
    }

    public function setUp(): void
    {
        parent::setUp();
        // Ensure the meta box actions are hooked
        RSSStreamMetaBox::register();
    }

    public function test_actions_are_added(): void
    {
        $this->assertNotFalse(has_action('add_meta_boxes', [RSSStreamMetaBox::class, 'addMetaBox']));
        $this->assertNotFalse(has_action('save_post_newsapi_rss_stream', [RSSStreamMetaBox::class, 'saveMetaBox']));
    }

    public function test_render_meta_box(): void
    {
        // Start output buffering to capture the rendered HTML
        ob_start();
        $post = get_post(self::$post_id);
        RSSStreamMetaBox::renderMetaBox($post);
        $content = ob_get_clean();

        // Check for expected fields
        $this->assertStringContainsString('name="rss_stream_category"', $content);
        $this->assertStringContainsString('name="rss_stream_tags[]"', $content);
        $this->assertStringContainsString('name="rss_stream_feeds"', $content);
        $this->assertStringContainsString('name="rss_stream_amount"', $content);
        $this->assertStringContainsString('name="rss_stream_max_size"', $content);
        $this->assertStringContainsString('name="fetch_rss"', $content);
    }

    public function test_save_meta_box_no_nonce(): void
    {
        // Simulate $_POST without a nonce
        $_POST = [
            'rss_stream_category' => '2',
        ];

        // Call saveMetaBox
        RSSStreamMetaBox::saveMetaBox(self::$post_id);

        // Since no nonce, no update should happen
        $this->assertEmpty(get_post_meta(self::$post_id, '_rss_stream_category', true));
    }

    public function test_save_meta_box_invalid_nonce(): void
    {
        // Create a fake nonce that won't verify
        $_POST = [
            'rss_stream_category' => '2',
            'newsapi_rss_stream_details_nonce_field' => 'fake_nonce'
        ];

        RSSStreamMetaBox::saveMetaBox(self::$post_id);
        $this->assertEmpty(get_post_meta(self::$post_id, '_rss_stream_category', true));
    }

    public function test_save_meta_box_valid_nonce_no_cap(): void
    {
        // Simulate a user with no permissions
        $this->set_current_user(0);

        $_POST = [
            'newsapi_rss_stream_details_nonce_field' => wp_create_nonce('newsapi_rss_stream_details_nonce'),
            'rss_stream_category' => '3'
        ];

        RSSStreamMetaBox::saveMetaBox(self::$post_id);

        // Current user can't edit post -> no update
        $this->assertEmpty(get_post_meta(self::$post_id, '_rss_stream_category', true));
    }

    public function test_save_meta_box_with_valid_nonce_and_capability(): void
    {
        // Give current user admin capability
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        $this->set_current_user($user_id);

        $_POST = [
            'newsapi_rss_stream_details_nonce_field' => wp_create_nonce('newsapi_rss_stream_details_nonce'),
            'rss_stream_category' => '5',
            'rss_stream_tags' => ['10', '20'],
            'rss_stream_feeds' => "http://example.com/feed1\nhttp://example.com/feed2",
            'rss_stream_amount' => '3',
            'rss_stream_max_size' => '10'
        ];

        RSSStreamMetaBox::saveMetaBox(self::$post_id);

        $this->assertEquals(5, (int)get_post_meta(self::$post_id, '_rss_stream_category', true));
        $this->assertEquals([10, 20], get_post_meta(self::$post_id, '_rss_stream_tags', true));
        $this->assertEquals("http://example.com/feed1\nhttp://example.com/feed2", get_post_meta(self::$post_id, '_rss_stream_feeds', true));
        $this->assertEquals(3, (int)get_post_meta(self::$post_id, '_rss_stream_amount', true));
        $this->assertEquals(10, (int)get_post_meta(self::$post_id, '_rss_stream_max_size', true));
    }

    public function test_fetch_rss_triggered(): void
    {
        // Mocking the RssReaderService and WPNewsPublisherService would be ideal.
        // For simplicity, assume they work as expected and just check that transients get set.

        $this->setAdminUser();

        // Fake RssReaderService - we can define a static closure to mock readUrl
        $this->mockRssReaderService();

        // Mock WPNewsPublisherService as well
        $this->mockWPNewsPublisherService();

        $_POST = [
            'newsapi_rss_stream_details_nonce_field' => wp_create_nonce('newsapi_rss_stream_details_nonce'),
            'rss_stream_category' => '5',
            'rss_stream_tags' => ['10'],
            'rss_stream_feeds' => "http://example.com/feed1",
            'rss_stream_amount' => '2',
            'rss_stream_max_size' => '5',
            'fetch_rss' => 'Fetch'
        ];

        RSSStreamMetaBox::saveMetaBox(self::$post_id);

        // After fetching, results should be stored in transient
        $results = get_transient('newsapi_rss_fetch_results_' . self::$post_id);
        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('count', $results);
        $this->assertEquals(2, $results['count']);
    }

    public function test_fetch_rss_error(): void
    {
        $this->setAdminUser();
        $this->mockRssReaderService(true); // cause error

        $_POST = [
            'newsapi_rss_stream_details_nonce_field' => wp_create_nonce('newsapi_rss_stream_details_nonce'),
            'rss_stream_category' => '5',
            'rss_stream_feeds' => "http://example.com/invalidfeed",
            'fetch_rss' => 'Fetch'
        ];

        RSSStreamMetaBox::saveMetaBox(self::$post_id);

        // Check error transient
        $error = get_transient('newsapi_rss_fetch_error_' . self::$post_id);
        $this->assertNotEmpty($error);
        $this->assertArrayHasKey('message', $error);
    }

    protected function setAdminUser(): void
    {
        $user_id = $this->factory->user->create(['role' => 'administrator']);
        $this->set_current_user($user_id);
    }

    protected function mockRssReaderService(bool $throwException = false): void
    {
        // Replace RssReaderService::readUrl with a closure for testing
        // This is a simplistic approach; a more robust solution might use something like Brain Monkey for mocking.
        $mock = function($url, $amount) use ($throwException) {
            if ($throwException) {
                throw new \Exception('Failed to fetch RSS');
            }
            // Return a fixed set of articles
            return [
                ['title' => 'Mock Article 1', 'content' => 'Content 1'],
                ['title' => 'Mock Article 2', 'content' => 'Content 2']
            ];
        };

        // Hacky way: override the global namespace function
        // In a real environment, you might refactor RssReaderService to be injectable or use a mock library.
        RssReaderService::class; // just to ensure autoload
        runkit_function_redefine(
            'NewsApiPlugin\\Services\\RssReaderService::readUrl',
            '$url, $amount',
            'return (' . var_export($mock, true) . ')($url,$amount);'
        );
    }

    protected function mockWPNewsPublisherService(): void
    {
        // Similarly mock publishNews
        $mock = function($articles, $categoryId, $tags, $status, $type) {
            return [
                'count' => count($articles),
                'post_ids' => [101, 102],
            ];
        };


    }
}
