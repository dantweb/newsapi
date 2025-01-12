<?php
declare(strict_types=1);

namespace NewsApiPlugin\Tests\Services;

use NewsApiPlugin\Services\WPNewsPublisherService;
use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * @group publisher
 */
class WPNewsPublisherServiceTest extends TestCase
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

    public function testPublishNewsCreatesPosts(): void
    {
        // Mock WordPress functions that are used by WPNewsPublisherService
        Functions\when('wp_insert_post')->justReturn(101);
        Functions\when('wp_set_object_terms')->justReturn(true);

        // Prepare test data
        $articles = [
            ['title' => 'Test Article 1', 'content' => 'Content 1'],
            ['title' => 'Test Article 2', 'content' => 'Content 2']
        ];
        $categoryId = 1;
        $tags = [2, 3];
        $postStatus = 'draft';
        $postType = 'post';

        $publisher = new WPNewsPublisherService();
        $result = $publisher->publishNews($articles, $categoryId, $tags, $postStatus, $postType);

        // Assertions
        $this->assertEquals(2, $result['count']);
        $this->assertEquals([101, 101], $result['post_ids']); // Adjust based on your mock
    }
}
