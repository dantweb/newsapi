<?php
declare(strict_types=1);

namespace NewsApiPlugin\Admin;

use NewsApiPlugin\Services\NewsGPTScraper;

class BlogScraperMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('save_post_newsapi_blog_scraper', [self::class, 'saveMetaBox']);
    }

    public static function addMetaBox(): void
    {
        add_meta_box(
            'newsapi_blog_scraper_details',
            'Blog Scraper Details',
            [self::class, 'renderMetaBox'],
            'newsapi_blog_scraper',
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('newsapi_blog_scraper_details_nonce', 'newsapi_blog_scraper_details_nonce_field');

        $categoryId = (int) get_post_meta($post->ID, '_blog_scraper_category', true);
        $tags = (array) get_post_meta($post->ID, '_blog_scraper_tags', true);
        $urls = get_post_meta($post->ID, '_blog_scraper_urls', true) ?: '';
        $amount = (int) get_post_meta($post->ID, '_blog_scraper_amount', true);
        $post_status = get_post_meta($post->ID, '_blog_scraper_post_status', true);

        // Category Selector
        $categories = get_categories(['hide_empty' => false]);
        echo '<p><label>Category:</label><br>';
        echo '<select name="blog_scraper_category">';
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
            echo '<label style="display:block;"><input type="checkbox" name="blog_scraper_tags[]" value="' . (int)$t->term_id . '" ' . $checked . '> ' . esc_html($t->name) . '</label>';
        }
        echo '</div></p>';

        // URLs textarea
        echo '<p><label>Blog URLs (one per line):</label><br>';
        echo '<textarea name="blog_scraper_urls" rows="5" style="width:100%;">' . esc_textarea($urls) . '</textarea></p>';

        // Amount
        echo '<p><label>Amount (per source):</label><br>';
        echo '<input type="number" name="blog_scraper_amount" value="' . esc_attr($amount) . '" min="1"></p>';

        // Post Status
        $statuses = ['publish', 'draft', 'pending'];
        echo '<p><label>Post Status:</label><br>';
        echo '<select name="blog_scraper_post_status">';
        foreach ($statuses as $st) {
            $selected = $post_status === $st ? 'selected' : '';
            echo '<option value="' . esc_attr($st) . '" ' . $selected . '>' . ucfirst($st) . '</option>';
        }
        echo '</select></p>';

        // Fetch button
        echo '<p><input type="submit" name="fetch_blog" class="button button-primary" value="Fetch"></p>';

        // Display results if any
        $results = get_transient('newsapi_blog_fetch_results_' . $post->ID);
        if ($results) {
            echo '<div class="notice notice-success"><p>Fetched ' . intval($results['count']) . ' blog articles.</p>';
            if (!empty($results['post_ids'])) {
                echo '<ul>';
                foreach ($results['post_ids'] as $id) {
                    echo '<li><a href="' . get_edit_post_link($id) . '" target="_blank">' . get_the_title($id) . '</a></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            delete_transient('newsapi_blog_fetch_results_' . $post->ID);
        }

        // Check for fetch errors
        $error = get_transient('newsapi_blog_fetch_error_' . $post->ID);
        if ($error) {
            echo '<div class="notice notice-error"><p><strong>Error fetching blog articles:</strong> ' . esc_html($error['message']) . '</p>';
            if (!empty($error['trace'])) {
                echo '<pre>' . esc_html($error['trace']) . '</pre>';
            }
            echo '</div>';
            delete_transient('newsapi_blog_fetch_error_' . $post->ID);
        }
    }

    public static function saveMetaBox(int $postId): void
    {
        if (!isset($_POST['newsapi_blog_scraper_details_nonce_field']) || !wp_verify_nonce($_POST['newsapi_blog_scraper_details_nonce_field'], 'newsapi_blog_scraper_details_nonce')) {
            return;
        }

        // Check user capability
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $categoryId = (int)($_POST['blog_scraper_category'] ?? 0);
        $tags = isset($_POST['blog_scraper_tags']) ? array_map('intval', (array)$_POST['blog_scraper_tags']) : [];
        $urls = isset($_POST['blog_scraper_urls']) ? sanitize_textarea_field($_POST['blog_scraper_urls']) : '';
        $amount = (int)($_POST['blog_scraper_amount'] ?? 0);
        $post_status = sanitize_text_field($_POST['blog_scraper_post_status'] ?? 'draft');

        update_post_meta($postId, '_blog_scraper_category', $categoryId);
        update_post_meta($postId, '_blog_scraper_tags', $tags);
        update_post_meta($postId, '_blog_scraper_urls', $urls);
        update_post_meta($postId, '_blog_scraper_amount', $amount);
        update_post_meta($postId, '_blog_scraper_post_status', $post_status);

        // Check if Fetch was clicked
        if (isset($_POST['fetch_blog'])) {
            self::fetchAndProcessBlog($postId);
        }
    }

    public static function fetchAndProcessBlog(int $postId): void
    {
        $debugMode = (bool) get_option('newsapi_debug', false);

        $categoryId = (int) get_post_meta($postId, '_blog_scraper_category', true);
        $tags = (array) get_post_meta($postId, '_blog_scraper_tags', true);
        $urls = get_post_meta($postId, '_blog_scraper_urls', true);
        $amount = (int) get_post_meta($postId, '_blog_scraper_amount', true);
        $postStatus = get_post_meta($postId, '_blog_scraper_post_status', true);

        // Convert URLs string into an array of URLs
        $urlsArray = array_filter(array_map('trim', explode("\n", $urls)));

        try {
            $articles = [];
            foreach ($urlsArray as $url) {
                if ($debugMode) {
                    error_log('Fetching blog articles: ' . $url);
                }
                // Fetch articles from this URL using the WebNewsScrapper service
                $scrapper = new NewsGPTScraper($debugMode);
                $articlesFromUrl = $scrapper->scrapeNewsFromUrl($url, $amount);

                // Merge with main articles array
                $articles = array_merge($articles, $articlesFromUrl);

                if ($debugMode) {
                    error_log("Fetched " . count($articlesFromUrl) . " articles from URL: " . $url);
                }
            }

            if ($debugMode) {
                set_transient('newsapi_blog_raw_response_' . $postId, $articles, 300);
            }

            // Publish the fetched items using WPNewsPublisherService
            $publisher = new \NewsApiPlugin\Services\WPNewsPublisherService();
            $result = $publisher->publishNews($articles, $categoryId, $tags, $postStatus, 'post');

            set_transient('newsapi_blog_fetch_results_' . $postId, $result, 300);

            if ($debugMode) {
                error_log("Published " . $result['count'] . " articles from blog scraper.");
            }
        } catch (\Exception $e) {
            $errorData = [
                'message' => $e->getMessage(),
            ];

            if ($debugMode) {
                $errorData['trace'] = $e->getTraceAsString();
                error_log("Error fetching blog articles: " . $e->getMessage());
                error_log("Trace: " . $e->getTraceAsString());
            }

            set_transient('newsapi_blog_fetch_error_' . $postId, $errorData, 300);
        }
    }
}