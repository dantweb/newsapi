<?php
declare(strict_types=1);

namespace NewsApiPlugin;

use B2S_Api_Post;
use NewsApiPlugin\NewsApi;

/**
 * Plugin Name: NewsAPI Plugin
 * Plugin URI: https://dantweb.dev/newsapi-plugin
 * Description: Fetches news from NewsAPI and stores them in WordPress as draft posts.
 * Version: 1.0.0
 * Author: [dantweb.dev]
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

require_once __DIR__ . '/vendor/autoload.php';

NewsApi::init();

if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('newsapi:run-stream', function ($args, $assocArgs) {
        $id = $assocArgs['id'] ?? null;
        $type = $assocArgs['type'] ?? 'news'; // Default to 'stream'

        if (!$id) {
            \WP_CLI::error('You must provide an ID for the stream.');
            return;
        }

        switch ($type) {
            case 'rss':
                \NewsApiPlugin\Admin\RSSStreamMetaBox::fetchAndProcessRSS((int)$id);
                \WP_CLI::success("RSS Stream processed for ID: {$id}");
                break;

            case 'news':
                \NewsApiPlugin\Admin\StreamMetaBox::fetchAndProcessNews((int)$id);
                \WP_CLI::success("NewsAPI Stream processed for ID: {$id}");
                break;

            default:
                \WP_CLI::error('Invalid type specified. Use "rss" or "news".');
        }
    });


    \WP_CLI::add_command('newsapi:run-blog-scraper', function ($args, $assocArgs) {
        $id = $assocArgs['id'] ?? null;

        if (!$id) {
            \WP_CLI::error('You must provide an ID for the blog scraper.');
            return;
        }

        \NewsApiPlugin\Admin\BlogScraperMetaBox::fetchAndProcessBlog((int)$id);
        \WP_CLI::success("Blog Scraper processed for ID: {$id}");
    });

}