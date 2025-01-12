<?php

declare(strict_types=1);

namespace NewsApiPlugin\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mockery\Exception;

class RssReaderService
{
    protected Client $httpClient;
    private WebNewsScrapper $scrapper;

    public function __construct(Client $client = null)
    {
        $this->httpClient = HTTPClientFactory::getClient($client);

        $this->scrapper = new WebNewsScrapper();
    }

    private static function getUrl(?\SimpleXMLElement $item): string
    {
        $url = '';

        if (!empty($item->link)) {
            $url = (string) $item->link;
        } elseif (!empty($item->url)) {
            $url = (string) $item->url;
        } elseif (!empty($item->guid)) {
            if (filter_var((string) $item->guid, FILTER_VALIDATE_URL)) {
                $url = (string) $item->guid;
            }
        } elseif (!empty($item->enclosure) && isset($item->enclosure->attributes()->url)) {
            $url = (string) $item->enclosure->attributes()->url;
        }

        return $url;
    }

    /**
     * Reads an RSS feed from the given URL and returns an array of articles.
     *
     * Each article is an associative array with keys like 'title' and 'content'.
     *
     * @param string $url The RSS feed URL.
     * @param int $amount Maximum number of items to fetch (0 = no limit).
     * @return array<int, array<string, string>> Array of articles with 'title' and 'content'.
     * @throws \Exception If the feed cannot be fetched or is invalid.
     */
    public function readUrl(string $url, int $amount = 10): array
    {
        try {
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();
            $contentType = $response->getHeaderLine('Content-Type');
            $body = (string) $response->getBody();


            // Log response details if in debug mode
            if (get_option('[RssReaderService ] newsapi_debug', false)) {
                error_log("RssReaderService ] Fetched URL: $url");
                error_log("Response code: $statusCode");

                error_log("Content-Type: $contentType");
            }

            // Check for successful response
            if ($statusCode !== 200) {
                throw new \Exception("Failed to fetch RSS feed. HTTP Status Code: $statusCode");
            }

            // Validate Content-Type
            if (
                stripos($contentType, 'application/rss+xml') === false &&
                stripos($contentType, 'application/atom+xml') === false &&
                stripos($contentType, 'text/xml') === false
            ) {
                error_log("Wrong content type: " . $contentType);
            }

            // Parse XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMessage = "Failed to parse RSS feed. Errors: ";
                foreach ($errors as $error) {
                    $errorMessage .= $error->message . "; ";
                }
                throw new \Exception($errorMessage);
            }

            // Handle both RSS 2.0 and Atom feeds
            if (isset($xml->channel->item)) {
                $items = $xml->channel->item;
            } elseif (isset($xml->entry)) {
                $items = $xml->entry;
            } else {
                throw new \Exception("No recognizable items found in the feed.");
            }

            error_log("[RssReaderService] Fetched URL: items "
                . print_r($items, true)
            );



            error_log("[RssReaderService] Fetched URL: items after array_slice "
                . print_r($items, true)
            );

            $articles = [];

            foreach ($items as $item) {
                $title = isset($item->title) ? (string) $item->title : 'No Title';

                if (isset($item->content)) {
                    $content = (string) $item->content;
                } elseif (isset($item->description)) {
                    $content = (string) $item->description;
                } else {
                    $content = 'No Content';
                }

                // Sanitize title and content
                $sanitizedTitle = wp_strip_all_tags($title);
                $sanitizedContent = wp_kses_post($content);
                $scrappedContent = '';

                $url = self::getUrl($item);
                if ($url) {
                    try {
                        $scrappedContent = $this->scrapper->scrape($url);
                    } catch (Exception $exception ) {
                        error_log('Error scrapping url '
                            . $item->link . ' '
                            . $exception->getMessage());
                    }
                }

                $articles[] = [
                    'title' => $sanitizedTitle,
                    'content' => $sanitizedContent,
                    'fullContent' => $scrappedContent,
                    'link' => $item->link,
                    'pubDate' => $item->pubDate,
                ];
            }

            // Log the number of articles fetched
            if (get_option('newsapi_debug', false)) {
                error_log("Number of articles fetched: " . count($articles));
            }

            return $articles;
        } catch (GuzzleException $e) {
            error_log('Error fetching RSS feed: ' . $e->getMessage());
        }

        return [];
    }
}
