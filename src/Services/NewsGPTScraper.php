<?php

declare(strict_types=1);

namespace NewsApiPlugin\Services;

use andreskrey\Readability\Readability;
use GuzzleHttp\Client;
use andreskrey\Readability\Configuration;
use GuzzleHttp\Exception\GuzzleException;
use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

class NewsGPTScraper
{
    private Client $httpClient;
    private string $openAiApiKey;
    private bool $debugMode;
    /**
     * @var false|mixed|null
     */
    private string $apiBaseUrl;

    public function __construct(bool $debugMode = false)
    {
        $this->debugMode = $debugMode;
        $this->apiBaseUrl = (string)get_option('newsapi_ai_url', 'https://openai.com');
        $this->httpClient = HTTPClientFactory::getClient();
        $this->openAiApiKey = get_option('newsapi_ai_openai_api_key', 'dummy key');
        if ($this->debugMode) {
            error_log("gpt api key: " . $this->openAiApiKey);
            error_log("gpt api url: " . $this->apiBaseUrl);
        }
    }

    public function scrapeNewsFromUrl(string $url, int $amount): array
    {
        $articles = [];

        try {
            // Step 1: Fetch the HTML content
            $html = $this->fetchHtml($url);
            if ($this->debugMode) {
                error_log("Scraped HTML content length: " . strlen($html));
            }

            // Step 2: Extract only the <body> content and remove <script> tags
            $bodyContent = $this->extractBodyContent($html);
            if ($this->debugMode) {
                error_log("Body content length after extraction: " . strlen($bodyContent));
            }

            // Step 3: Extract only <a> tags from the body content
            $linksContent = $this->extractLinks($bodyContent);
            if ($this->debugMode) {
                error_log("Links content length: " . strlen($linksContent));
            }

            // Step 2: Send HTML to GPT and ask for URLs of published posts
            $newsUrls = $this->getNewsUrlsFromGpt($linksContent);

            // Step 3: Scrape each news page
            foreach ($newsUrls as $newsUrl) {
                $articles[] = $this->scrape($newsUrl);
                if (count($articles) >= $amount) {
                    break;
                }
            }
        } catch (\Exception $e) {
            error_log('[NewsGPTScraper::scrapeNewsFromUrl]Error scraping news from URL: ' . $e->getMessage());
        } catch (GuzzleException $e) {
            error_log('[NewsGPTScraper::scrapeNewsFromUrl] GuzzleException: ' . $e->getMessage());

        }

        return $articles;
    }

    private function fetchHtml(string $url): string
    {
        $response = $this->httpClient->get($url);
        return (string) $response->getBody();
    }

    /**
     * @throws \Exception
     * @throws GuzzleException
     */
    private function getNewsUrlsFromGpt(string $html): array
    {
        // Step 1: Retrieve AI settings from WordPress options
        $aiModel = get_option('newsapi_ai_model', 'deepseek-chat');
        $aiMaxTokens = (int)get_option('newsapi_ai_max_tokens', 500);
        $aiTemperature = (float)get_option('newsapi_ai_temperature', 0.7);
        $aiPrompt = get_option('newsapi_ai_prompt', "Analyze the following HTML content and return a JSON array of URLs that represent published posts. Only include URLs that are definitely links to published posts. The response must be a valid JSON array. Here is the HTML:\n\n");

        // Step 2: Prepare the prompt for GPT
        $prompt = $aiPrompt . ' >>> ' . $html;

        // Step 3: Send the prompt to GPT
        $response = $this->sendToGpt($prompt, $aiModel, $aiMaxTokens, $aiTemperature);

        if ($this->debugMode) {
            error_log("Response from GPT : " . $response);
        }

        // Step 4: Parse and validate the response
        return $this->parseGptResponse($response);
    }

    /**
     * @throws GuzzleException
     */
    private function sendToGpt(
        string $prompt,
        string $aiModel,
        int $aiMaxTokens,
        float $aiTemperature
    ): string {

        $gptClient = new Client([
            'base_uri' => $this->apiBaseUrl, // Replace with the correct DeepSeek API endpoint
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openAiApiKey,
                'Content-Type' => 'application/json',
            ],
        ]);

        $isValidJson = true;
        $counter = 1;
        $systemContent = 'You extract only news headers urls from the given html';
        $errorContent = 'You extract only news headers urls from the given html. '
            . 'Your previous response is not a valid JSON. Reutrn only valid json string without ˙˙˙ ';

        try {

            do {

                $response = $gptClient->post('/chat/completions', [
                    'json' => [ // Use 'json' key to send the payload as JSON
                        'model' => $aiModel,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $isValidJson ? $systemContent : $errorContent
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'max_tokens' => $aiMaxTokens,
                        'temperature' => $aiTemperature,
                    ],
                ]);

                $responseBody = json_decode($response->getBody()->getContents(), true);
                $translatedText = $responseBody['choices'][0]['message']['content'];
                if ($this->debugMode) {
                    error_log("Translated Text: " . print_r($translatedText, true));
                }

                $isValidJson = $this->isValidJson($translatedText);
                if (!$isValidJson) {
                    $translatedText = JsonFixer::fixIncompleteJson($translatedText);
                }
                $counter++;
            } while (!$isValidJson && $counter < 5);
        } catch (\Exception $e) {
            echo "[NewsGPTScraper::sendToGpt] Error: " . $e->getMessage();
        }

        return $translatedText ?? '';
    }

    private function parseGptResponse(string $response): array
    {
        // Step 1: Try to decode the response as JSON
        $decodedResponse = json_decode($response, true);

        // Step 2: Validate the response
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedResponse)) {
            throw new \Exception("Invalid JSON response from GPT.");
        }

        // Step 3: Ensure all items in the array are valid URLs
        $validUrls = [];
        foreach ($decodedResponse as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $validUrls[] = $url;
            }
        }

        if (empty($validUrls)) {
            throw new \Exception("No valid URLs found in GPT's response.");
        }

        return $validUrls;
    }

    private function scrape(string $url): array
    {
        // Fetch the HTML content
        $response = $this->httpClient->get($url);
        $html = (string) $response->getBody();

        // Use Readability to extract the article content and title
        $readability = new Readability(new Configuration());
        $readability->parse($html);

        // Extract the title and content
        $title = $readability->getTitle(); // Get the title
        $content = $readability->getContent(); // Get the content

        return [
            'title' => $title,
            'content' => $content,
            'url' => $url, // Include the URL as the source link
            'link' => $url,
        ];
    }

    private function extractBodyContent(string $html): string
    {
        // Load the HTML into a DOMDocument object
        $dom = new \DOMDocument();
        @$dom->loadHTML($html); // Suppress warnings for invalid HTML

        // Remove <script> tags
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $script->parentNode->removeChild($script);
        }

        // Extract the <body> content
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            return $dom->saveHTML($body);
        }

        return ''; // Return an empty string if no <body> is found
    }

    private function extractLinks(string $html): string
    {
        // Load the HTML into a DOMDocument object
        $dom = new \DOMDocument();
        @$dom->loadHTML($html); // Suppress warnings for invalid HTML

        // Extract all <a> tags
        $links = $dom->getElementsByTagName('a');
        $linksContent = '';

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $text = $link->textContent;
            $linksContent .= "<a href=\"$href\">$text</a>\n";
        }

        return $linksContent;
    }

    private function isValidJson(string $jsonString): bool
    {
        $schema = '{
            "type": "array",
            "items": {
                "type": "string"
            }
        }';

        $data = json_decode($jsonString);
        $schema = json_decode($schema);

        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_TYPE_CAST);
        return $validator->isValid();
    }
}