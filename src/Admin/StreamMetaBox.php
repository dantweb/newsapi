<?php

declare(strict_types=1);

namespace NewsApiPlugin\Admin;

class StreamMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('save_post_newsapi_stream', [self::class, 'saveMetaBox']);
    }

    public static function addMetaBox(): void
    {
        add_meta_box(
            'newsapi_stream_details',
            'Stream Details',
            [self::class, 'renderMetaBox'],
            'newsapi_stream',
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('newsapi_stream_details_nonce', 'newsapi_stream_details_nonce_field');

        $language = get_post_meta($post->ID, '_stream_language', true);
        $dataType = (array) get_post_meta($post->ID, '_stream_data_type', true); // stored as array
        $keywords = get_post_meta($post->ID, '_stream_keywords', true);
        $category = get_post_meta($post->ID, '_stream_category', true);
        $tags = (array) get_post_meta($post->ID, '_stream_tags', true);
        $amount = get_post_meta($post->ID, '_stream_amount', true);
        $post_status = get_post_meta($post->ID, '_stream_post_status', true);
        $post_type = get_post_meta($post->ID, '_stream_post_type', true);

        // Language
        echo '<p><label>Language:</label><br>';
        echo '<input type="text" name="stream_language" value="' . esc_attr($language) . '"></p>';

        // Data Type (news, blog, article)
        $possibleDataTypes = ['news', 'blog', 'article'];
        echo '<p><label>Data Type:</label><br>';
        foreach ($possibleDataTypes as $type) {
            $checked = in_array($type, $dataType) ? 'checked' : '';
            echo '<label><input type="checkbox" name="stream_data_type[]" value="' . esc_attr($type) . '" ' . $checked . '> ' . ucfirst($type) . '</label> ';
        }
        echo '</p>';

        // Keywords
        echo '<p><label>Keywords (comma separated):</label><br>';
        echo '<input type="text" name="stream_keywords" value="' . esc_attr($keywords) . '"></p>';

        // Category Selector (using WP categories)
        $categories = get_categories(['hide_empty' => false]);
        echo '<p><label>Category:</label><br>';
        echo '<select name="stream_category">';
        echo '<option value="">-- Select Category --</option>';
        foreach ($categories as $cat) {
            $selected = (int)$category === (int)$cat->term_id ? 'selected' : '';
            echo '<option value="' . (int)$cat->term_id . '" ' . $selected . '>' . esc_html($cat->name) . '</option>';
        }
        echo '</select></p>';

        // Tags checkboxes
        $all_tags = get_tags(['hide_empty' => false]);
        echo '<p><label>Tags:</label><br>';
        foreach ($all_tags as $t) {
            $checked = in_array($t->term_id, $tags) ? 'checked' : '';
            echo '<label><input type="checkbox" name="stream_tags[]" value="' . (int)$t->term_id . '" ' . $checked . '> ' . esc_html($t->name) . '</label> ';
        }
        echo '</p>';

        // Amount
        echo '<p><label>Amount:</label><br>';
        echo '<input type="number" name="stream_amount" value="' . esc_attr($amount) . '" min="1"></p>';

        // Post Status
        $statuses = ['publish', 'draft', 'pending'];
        echo '<p><label>Post Status:</label><br>';
        echo '<select name="stream_post_status">';
        foreach ($statuses as $st) {
            $selected = $post_status === $st ? 'selected' : '';
            echo '<option value="' . esc_attr($st) . '" ' . $selected . '>' . ucfirst($st) . '</option>';
        }
        echo '</select></p>';

        // Post Type
        $types = ['post' => 'Post', 'page' => 'Page'];
        echo '<p><label>Post Type:</label><br>';
        echo '<select name="stream_post_type">';
        foreach ($types as $typeKey => $typeLabel) {
            $selected = $post_type === $typeKey ? 'selected' : '';
            echo '<option value="' . esc_attr($typeKey) . '" ' . $selected . '>' . $typeLabel . '</option>';
        }
        echo '</select></p>';

        // Fetch News button
        // This button submits the form with a special action
        echo '<p><input type="submit" name="fetch_news" class="button button-primary" value="Fetch News"></p>';
        $results = get_transient('newsapi_fetch_results_' . $post->ID);
        if ($results) {
            echo '<div class="notice notice-success"><p>Fetched ' . intval($results['count']) . ' news items.</p>';
            if (!empty($results['post_ids'])) {
                echo '<ul>';
                foreach ($results['post_ids'] as $id) {
                    echo '<li><a href="' . get_edit_post_link($id) . '" target="_blank">' . get_the_title($id) . '</a></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            delete_transient('newsapi_fetch_results_' . $post->ID);
        }

        // Check for fetch errors
        $error = get_transient('newsapi_fetch_error_' . $post->ID);
        if ($error) {
            echo '<div class="notice notice-error"><p><strong>Error fetching news:</strong> ' . esc_html($error['message']) . '</p>';
            if (!empty($error['trace'])) {
                echo '<pre>' . esc_html($error['trace']) . '</pre>';
            }
            echo '</div>';
            delete_transient('newsapi_fetch_error_' . $post->ID);
        }
    }


    public static function saveMetaBox(int $postId): void
    {
        if (!isset($_POST['newsapi_stream_details_nonce_field']) || !wp_verify_nonce($_POST['newsapi_stream_details_nonce_field'], 'newsapi_stream_details_nonce')) {
            return;
        }

        // Save fields
        update_post_meta($postId, '_stream_language', sanitize_text_field($_POST['stream_language'] ?? ''));
        $dataType = isset($_POST['stream_data_type']) ? array_map('sanitize_text_field', (array)$_POST['stream_data_type']) : [];
        update_post_meta($postId, '_stream_data_type', $dataType);
        update_post_meta($postId, '_stream_keywords', sanitize_text_field($_POST['stream_keywords'] ?? ''));
        update_post_meta($postId, '_stream_category', (int)($_POST['stream_category'] ?? 0));
        $tags = isset($_POST['stream_tags']) ? array_map('intval', (array)$_POST['stream_tags']) : [];
        update_post_meta($postId, '_stream_tags', $tags);
        update_post_meta($postId, '_stream_amount', (int)($_POST['stream_amount'] ?? 0));
        update_post_meta($postId, '_stream_post_status', sanitize_text_field($_POST['stream_post_status'] ?? 'draft'));
        update_post_meta($postId, '_stream_post_type', sanitize_text_field($_POST['stream_post_type'] ?? 'post'));

        // Check if Fetch News was clicked
        if (isset($_POST['fetch_news'])) {
            self::fetchAndProcessNews($postId);
        }
    }

    /**
     * Fetch news from the API based on current stream config and display results.
     */
    public static function fetchAndProcessNews(int $postId): void
    {
        $apiKey = get_option('newsapi_api_key');
        $debugMode = (bool) get_option('newsapi_debug', false);

        $language = get_post_meta($postId, '_stream_language', true);
        $dataType = get_post_meta($postId, '_stream_data_type', true);
        $keywords = get_post_meta($postId, '_stream_keywords', true);
        $categoryId = (int) get_post_meta($postId, '_stream_category', true);
        $tags = (array) get_post_meta($postId, '_stream_tags', true);
        $amount = (int) get_post_meta($postId, '_stream_amount', true);
        $postStatus = get_post_meta($postId, '_stream_post_status', true);
        $postType = get_post_meta($postId, '_stream_post_type', true);

        $params = [
            'q' => $keywords,
            'language' => $language,
            // Additional parameters as needed by your NewsFetcherService...
        ];

        $fetcher = new \NewsApiPlugin\Services\NewsApiFetcherService($apiKey);

        try {
            $articles = $fetcher->fetchNews($params, true);
        } catch (\Exception $e) {
            // Store the error in a transient or an option
            $errorData = [
                'message' => $e->getMessage(),
            ];

            if ($debugMode) {
                $errorData['trace'] = $e->getTraceAsString();
            }

            set_transient('newsapi_fetch_error_' . $postId, $errorData, 300);

            return; // Stop the process here
        }

        if ($debugMode) {
            set_transient('newsapi_raw_response_' . $postId, $articles, 300);
        }

        // If fetch was successful
        if ($amount > 0 && count($articles) > $amount) {
            $articles = array_slice($articles, 0, $amount);
        }

        $publisher = new \NewsApiPlugin\Services\WPNewsPublisherService();
        $result = $publisher->publishNews($articles, $categoryId, $tags, $postStatus, $postType);

        set_transient('newsapi_fetch_results_' . $postId, $result, 300);
    }
}
