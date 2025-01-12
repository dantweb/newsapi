<?php

declare(strict_types=1);

namespace NewsApiPlugin\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class NewsApiFetcherService
{
    private Client $newsApiClient;
    private WebNewsScrapper $scrapper;

    public function __construct(string $apiKey)
    {
        $this->newsApiClient = new Client([
            'base_uri' => 'https://newsapi.org/v2/',
            'headers' => [
                'Authorization' => $apiKey,
            ],
        ]);

        // If no scrapper provided, create one
        $this->scrapper = new WebNewsScrapper();
    }

    /**
     * Fetch news articles from the API.
     *
     * @param array<string, mixed> $parameters
     * @return array<int, mixed>
     * @throws GuzzleException
     */
    public function fetchNews(array $parameters, bool $fetchFullContent = false): array
    {
        try {
            $response = $this->newsApiClient->get('everything', [
                'query' => $parameters,
            ]);
        } catch (GuzzleException $e) {
            throw new \Exception('Error fetching news: ' . $e->getMessage());
        }

        $data = json_decode((string)$response->getBody(), true);
        $articles = $data['articles'] ?? [];

        if ($fetchFullContent) {
            foreach ($articles as $key => $article) {
                // Attempt to scrape full content if article has a URL
                if (!empty($article['url'])) {
                    try {
                        $fullText = $this->scrapper->scrape($article['url']);
                        $articles[$key]['fullContent'] = $fullText;
                    } catch (\Exception $ex) {
                        // If scraping fails, you can log or just leave the article with partial content
                        $articles[$key]['fullContent'] = '';
                        // Optionally log the error:
                         error_log('Scrape error: ' . $ex->getMessage());
                    }
                }
            }
        }

        return $articles;
    }
}
