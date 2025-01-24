<?php
declare(strict_types=1);

namespace NewsApiPlugin\Admin;

use NewsApiPlugin\Services\WebNewsScrapper;

class GoogleScholarMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('save_post_newsapi_gs', [self::class, 'saveMetaBox']);
    }

    public static function addMetaBox(): void
    {
        add_meta_box(
            'newsapi_gs_details',
            'Google Scholar Details',
            [self::class, 'renderMetaBox'],
            'newsapi_gs',
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('newsapi_gs_details_nonce', 'newsapi_gs_details_nonce_field');

        // Retrieve saved meta values
        $categoryId = (int) get_post_meta($post->ID, '_google_scholar_category', true);
        $tags = (array) get_post_meta($post->ID, '_google_scholar_tags', true);
        $keywords = get_post_meta($post->ID, '_google_scholar_keywords', true) ?: '';
        $maxSteps = (int) get_post_meta($post->ID, '_google_scholar_max_steps', true);
        $maxArticlesPerStep = (int) get_post_meta($post->ID, '_google_scholar_max_articles_per_step', true);
        $post_status = get_post_meta($post->ID, '_google_scholar_post_status', true);
        $mindate = get_post_meta($post->ID, '_google_scholar_mindate', true);
        $maxdate = get_post_meta($post->ID, '_google_scholar_maxdate', true);
        $fields = (array) get_post_meta($post->ID, '_google_scholar_fields', true);

        // Category Selector
        $categories = get_categories(['hide_empty' => false]);
        echo '<p><label>Category:</label><br>';
        echo '<select name="google_scholar_category">';
        echo '<option value="">-- Select Category --</option>';
        foreach ($categories as $cat) {
            $selected = $cat->term_id === $categoryId ? 'selected' : '';
            echo '<option value="' . (int)$cat->term_id . '" ' . $selected . '>' . esc_html($cat->name) . '</option>';
        }
        echo '</select></p>';

        // Tags (checkboxes in a scrollable area)
        $all_tags = get_tags(['hide_empty' => false]);
        echo '<p><label>Tags:</label><br>';
        echo '<div style="max-height: 200px; overflow:auto; border:1px solid #ccc; padding:5px;">';
        foreach ($all_tags as $t) {
            $checked = in_array($t->term_id, $tags) ? 'checked' : '';
            echo '<label style="display:block;"><input type="checkbox" name="google_scholar_tags[]" value="' . (int)$t->term_id . '" ' . $checked . '> ' . esc_html($t->name) . '</label>';
        }
        echo '</div></p>';

        // Keywords
        echo '<p><label>Keywords (comma separated):</label><br>';
        echo '<input type="text" name="google_scholar_keywords" value="' . esc_attr($keywords) . '" class="regular-text"></p>';

        // Max Steps Deep
        echo '<p><label>Max Steps Deep:</label><br>';
        echo '<input type="number" name="google_scholar_max_steps" value="' . esc_attr($maxSteps) . '" min="1"></p>';

        // Max Articles per Step
        echo '<p><label>Max Articles per Step:</label><br>';
        echo '<input type="number" name="google_scholar_max_articles_per_step" value="' . esc_attr($maxArticlesPerStep) . '" min="1"></p>';

        // Post Status
        $statuses = ['publish', 'draft', 'pending'];
        echo '<p><label>Post Status:</label><br>';
        echo '<select name="google_scholar_post_status">';
        foreach ($statuses as $st) {
            $selected = $post_status === $st ? 'selected' : '';
            echo '<option value="' . esc_attr($st) . '" ' . $selected . '>' . ucfirst($st) . '</option>';
        }
        echo '</select></p>';

        // Date Filters
        echo '<p><label>Min Date (YYYY/MM/DD):</label><br>';
        echo '<input type="text" name="google_scholar_mindate" value="' . esc_attr($mindate) . '" class="regular-text"></p>';

        echo '<p><label>Max Date (YYYY/MM/DD):</label><br>';
        echo '<input type="text" name="google_scholar_maxdate" value="' . esc_attr($maxdate) . '" class="regular-text"></p>';

        // Fields to Include in Content
        $fieldOptions = [
            'AuthorList' => 'Author List',
            'AbstractText' => 'Abstract',
            'Article.Journal' => 'Journal',
            'PublicationType' => 'Publication Type',
            'PublicationDate' => 'Publication Date',
            'ReferenceList' => 'References',
        ];
        echo '<p><label>Fields to Include in Content:</label><br>';
        echo '<div style="max-height: 200px; overflow:auto; border:1px solid #ccc; padding:5px;">';
        foreach ($fieldOptions as $value => $label) {
            $checked = in_array($value, $fields) ? 'checked' : '';
            echo '<label style="display:block;"><input type="checkbox" name="google_scholar_fields[]" value="' . esc_attr($value) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
        }
        echo '</div></p>';

        // Fetch button
        echo '<p><input type="submit" name="fetch_google_scholar" class="button button-primary" value="Fetch"></p>';

        // Display results if any
        $results = get_transient('newsapi_gs_fetch_results_' . $post->ID);
        if ($results) {
            echo '<div class="notice notice-success"><p>Fetched ' . intval($results['count']) . ' Google Scholar articles.</p>';
            if (!empty($results['post_ids'])) {
                echo '<ul>';
                foreach ($results['post_ids'] as $id) {
                    echo '<li><a href="' . get_edit_post_link($id) . '" target="_blank">' . get_the_title($id) . '</a></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            delete_transient('newsapi_gs_fetch_results_' . $post->ID);
        }

        // Check for fetch errors
        $error = get_transient('newsapi_gs_fetch_error_' . $post->ID);
        if ($error) {
            echo '<div class="notice notice-error"><p><strong>Error fetching Google Scholar articles:</strong> ' . esc_html($error['message']) . '</p>';
            if (!empty($error['trace'])) {
                echo '<pre>' . esc_html($error['trace']) . '</pre>';
            }
            echo '</div>';
            delete_transient('newsapi_gs_fetch_error_' . $post->ID);
        }
    }

    public static function saveMetaBox(int $postId): void
    {
        if (!isset($_POST['newsapi_gs_details_nonce_field']) || !wp_verify_nonce($_POST['newsapi_gs_details_nonce_field'], 'newsapi_gs_details_nonce')) {
            return;
        }

        // Check user capability
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Save meta values
        update_post_meta($postId, '_google_scholar_category', (int)($_POST['google_scholar_category'] ?? 0));
        update_post_meta($postId, '_google_scholar_tags', isset($_POST['google_scholar_tags']) ? array_map('intval', (array)$_POST['google_scholar_tags']) : []);
        update_post_meta($postId, '_google_scholar_keywords', sanitize_text_field($_POST['google_scholar_keywords'] ?? ''));
        update_post_meta($postId, '_google_scholar_max_steps', (int)($_POST['google_scholar_max_steps'] ?? 0));
        update_post_meta($postId, '_google_scholar_max_articles_per_step', (int)($_POST['google_scholar_max_articles_per_step'] ?? 0));
        update_post_meta($postId, '_google_scholar_post_status', sanitize_text_field($_POST['google_scholar_post_status'] ?? 'draft'));
        update_post_meta($postId, '_google_scholar_mindate', sanitize_text_field($_POST['google_scholar_mindate'] ?? ''));
        update_post_meta($postId, '_google_scholar_maxdate', sanitize_text_field($_POST['google_scholar_maxdate'] ?? ''));
        update_post_meta($postId, '_google_scholar_fields', isset($_POST['google_scholar_fields']) ? array_map('sanitize_text_field', (array)$_POST['google_scholar_fields']) : []);

        // Check if Fetch was clicked
        if (isset($_POST['fetch_google_scholar'])) {
            self::fetchAndProcessGoogleScholar($postId);
        }
    }

    public static function fetchAndProcessGoogleScholar(int $postId): void
    {
        $debugMode = (bool) get_option('newsapi_debug', false);

        $categoryId = (int) get_post_meta($postId, '_google_scholar_category', true);
        $tags = (array) get_post_meta($postId, '_google_scholar_tags', true);
        $keywords = get_post_meta($postId, '_google_scholar_keywords', true);
        $maxSteps = (int) get_post_meta($postId, '_google_scholar_max_steps', true);
        $maxArticlesPerStep = (int) get_post_meta($postId, '_google_scholar_max_articles_per_step', true);
        $postStatus = get_post_meta($postId, '_google_scholar_post_status', true);
        $mindate = get_post_meta($postId, '_google_scholar_mindate', true);
        $maxdate = get_post_meta($postId, '_google_scholar_maxdate', true);
        $fields = (array) get_post_meta($postId, '_google_scholar_fields', true);

        try {
            $articles = self::fetchGoogleScholarArticles($keywords, $maxSteps, $maxArticlesPerStep, $mindate, $maxdate);

            if ($debugMode) {
                set_transient('newsapi_gs_raw_response_' . $postId, $articles, 300);
            }

            // Scrape each article's link
            $scrapper = new WebNewsScrapper();
            foreach ($articles as &$article) {
                if (!empty($article['link'])) {
                    $scrapedContent = $scrapper->scrape($article['link']);
                    if (!empty($scrapedContent)) {
                        $article['content'] = $scrapedContent;
                    }
                }
            }

            // Publish the fetched items using WPNewsPublisherService
            $publisher = new \NewsApiPlugin\Services\WPNewsPublisherService();
            $result = $publisher->publishNews($articles, $categoryId, $tags, $postStatus, 'post');

            set_transient('newsapi_gs_fetch_results_' . $postId, $result, 300);
        } catch (\Exception $e) {
            $errorData = [
                'message' => $e->getMessage(),
            ];

            if ($debugMode) {
                $errorData['trace'] = $e->getTraceAsString();
                error_log("Error fetching Google Scholar articles: " . $e->getMessage());
                error_log("Trace: " . $e->getTraceAsString());
            }

            set_transient('newsapi_gs_fetch_error_' . $postId, $errorData, 300);
        }
    }

    /**
     * @throws \Exception
     */
    private static function fetchGoogleScholarArticles(
        string $keywords,
        int $maxSteps,
        int $maxArticlesPerStep,
        string $mindate = '',
        string $maxdate = ''
    ): array {
        $apiKey = get_option('newsapi_gs_api_key');
        if (!$apiKey) {
            throw new \Exception('Google Scholar API Key is not configured.');
        }

        $articles = [];
        $currentStep = 0;
        $currentKeywords = $keywords;

        while ($currentStep < $maxSteps) {
            $params = [
                'engine' => 'google_scholar',
                'q' => $currentKeywords,
                'api_key' => $apiKey,
                'num' => $maxArticlesPerStep,
                'as_ylo' => $mindate,
                'as_yhi' => $maxdate,
            ];

            $response = wp_remote_get('https://serpapi.com/search.json?' . http_build_query($params), [
                'timeout' => 30, // Set a higher timeout if needed
            ]);

            if (is_wp_error($response)) {
                throw new \Exception('Error fetching Google Scholar articles: ' . $response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Debug: Inspect the API response
            error_log('Google Scholar API Response: ' . print_r($data, true));

            // Check for API errors
            if (isset($data['error'])) {
                throw new \Exception('Google Scholar API error: ' . $data['error']);
            }

            // Check if organic_results exist and are not empty
            if (empty($data['organic_results'])) {
                error_log('No results found for query: ' . $currentKeywords);
                break; // No more results
            }

            // Process organic_results
            foreach ($data['organic_results'] as $result) {
                if (isset($result['title'], $result['snippet'], $result['link'])) {
                    $articles[] = [
                        'title' => $result['title'],
                        'content' => $result['snippet'],
                        'link' => $result['link'],
                    ];
                } else {
                    error_log('Invalid result structure: ' . print_r($result, true));
                }
            }

            // Generate keywords for the next step
            $currentStep++;
            if ($currentStep < $maxSteps) {
                // Use the first 3 words of the first result's title as the next query
                $firstResultTitle = $data['organic_results'][0]['title'];
                $currentKeywords = implode(' ', array_slice(explode(' ', $firstResultTitle), 0, 3));
                error_log('Next query keywords: ' . $currentKeywords);
            }
        }

        if (empty($articles)) {
            throw new \Exception('No articles found for the given query.');
        }

        return $articles;
    }
}