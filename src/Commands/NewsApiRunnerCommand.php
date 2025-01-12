<?php
declare(strict_types=1);

namespace NewsApiPlugin\Commands;

use NewsApiPlugin\Services\NewsApiFetcherService;
use NewsApiPlugin\Services\WPNewsPublisherService;
use WP_CLI;

class NewsApiRunnerCommand
{
    /**
     * Runs the news fetcher and publisher.
     *
     * @param array<int, mixed> $args
     * @param array<string, mixed> $assocArgs
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $apiKey = get_option('newsapi_api_key');
        if (!$apiKey) {
            \WP_CLI::error('API Key is not configured.');
            return;
        }

        $categoryId = (int) ($assocArgs['category'] ?? 1);
        $parameters = [
            'q' => $assocArgs['query'] ?? 'technology',
            'language' => $assocArgs['language'] ?? 'en',
        ];

        $fetcher = new NewsApiFetcherService($apiKey);
        $publisher = new WPNewsPublisherService();

        try {
            $articles = $fetcher->fetchNews($parameters, true);
            $publisher->publishNews($articles, $categoryId);
            \WP_CLI::success('News fetched and stored as draft posts.');
        } catch (\Exception $e) {
            \WP_CLI::error('Error fetching news: ' . $e->getMessage());
        }
    }
}
