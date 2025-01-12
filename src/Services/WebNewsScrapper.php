<?php

declare(strict_types=1);

namespace NewsApiPlugin\Services;

use andreskrey\Readability\ParseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\Readability;

class WebNewsScrapper
{
    private Client $httpClient;
    private Configuration $config;

    public function __construct(?Client $client = null, ?Configuration $config = null)
    {
        $this->httpClient = $client ?? new Client([
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);

        $this->config = $config ?? new Configuration([
            'originalUrl' => '',
        ]);
    }

    /**
     * Scrape the provided URL and return the extracted article text.
     *
     * @param string $url
     * @return string Returns the extracted text content of the article.
     * @throws \Exception If unable to fetch or parse the article.
     */
    public function scrape(string $url): string
    {
        $content = '';
        try {
            $response = $this->httpClient->get($url);
            $html = (string) $response->getBody();

            $this->config->setOriginalUrl($url);
            $readability = new Readability($this->config);
            $readability->parse($html);

            $htmlContent = $readability->getContent();
            if (empty($htmlContent)) {
                error_log('Unable to extract content from the article.');
            }

            // Convert HTML to plain text
            $content = strip_tags($htmlContent);

        } catch (GuzzleException $e) {
            error_log('Error fetching article: ' . $e->getMessage());
        } catch (ParseException $e) {
            error_log('Cannot parse contnent: ' . $e->getMessage());
        }

        return $content;
    }
}
