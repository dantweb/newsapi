<?php

namespace NewsApiPlugin\Services;

use GuzzleHttp\Client;

class HTTPClientFactory
{
    public static function getClient(?Client $client = null): Client
    {
        return $client ?? new Client([
            'timeout' => 15,
            'headers' => [
                'User-Agent' => self::getUserAgent(),
                'Connection' => 'keep-alive',
            ],
            'allow_redirects' => true,
            'verify' => true, // Ensure SSL certificates are verified
            'cookies' => true, // Handle cookies
            ]
        );
    }

    private static function getUserAgent(): string
    {
        return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        .'(KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
    }
}