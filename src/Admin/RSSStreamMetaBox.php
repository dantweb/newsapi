<?php

declare(strict_types=1);

namespace NewsApiPlugin\Admin;

class RSSStreamMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('save_post_newsapi_rss_stream', [self::class, 'saveMetaBox']);
    }

    public static function addMetaBox(): void
    {
        add_meta_box(
            'newsapi_rss_stream_details',
            'RSS Stream Details',
            [self::class, 'renderMetaBox'],
            'newsapi_rss_stream',
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('newsapi_rss_stream_details_nonce', 'newsapi_rss_stream_details_nonce_field');

        $categoryId = (int) get_post_meta($post->ID, '_rss_stream_category', true);
        $tags = (array) get_post_meta($post->ID, '_rss_stream_tags', true);
        $rssFeeds = get_post_meta($post->ID, '_rss_stream_feeds', true) ?: '';
        $amount = (int) get_post_meta($post->ID, '_rss_stream_amount', true);
        $post_status = (int) get_post_meta($post->ID, '_rss_stream_post_status', true);
        $maxSize = (int) get_post_meta($post->ID, '_rss_stream_max_size', true);

        // Category Selector
        $categories = get_categories(['hide_empty' => false]);
        echo '<p><label>Category:</label><br>';
        echo '<select name="rss_stream_category">';
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
            echo '<label style="display:block;"><input type="checkbox" name="rss_stream_tags[]" value="' . (int)$t->term_id . '" ' . $checked . '> ' . esc_html($t->name) . '</label>';
        }
        echo '</div></p>';

        // RSS Feeds textarea
        echo '<p><label>RSS Feeds (one per line):</label><br>';
        echo '<textarea name="rss_stream_feeds" rows="5" style="width:100%;">' . esc_textarea($rssFeeds) . '</textarea></p>';

        // Amount
        echo '<p><label>Amount (per source):</label><br>';
        echo '<input type="number" name="rss_stream_amount" value="' . esc_attr($amount) . '" min="1"></p>';

        // Max Size
        echo '<p><label>Max Size (total items):</label><br>';
        echo '<input type="number" name="rss_stream_max_size" value="' . esc_attr($maxSize) . '" min="1"></p>';


        // Post Status
        $statuses = ['publish', 'draft', 'pending'];
        echo '<p><label>Post Status:</label><br>';
        echo '<select name="stream_post_status">';
        foreach ($statuses as $st) {
            $selected = $post_status === $st ? 'selected' : '';
            echo '<option value="' . esc_attr($st) . '" ' . $selected . '>' . ucfirst($st) . '</option>';
        }
        echo '</select></p>';

        // Fetch button
        echo '<p><input type="submit" name="fetch_rss" class="button button-primary" value="Fetch"></p>';

        // Display results if any
        $results = get_transient('newsapi_rss_fetch_results_' . $post->ID);
        if ($results) {
            echo '<div class="notice notice-success"><p>Fetched ' . intval($results['count']) . ' RSS items.</p>';
            if (!empty($results['post_ids'])) {
                echo '<ul>';
                foreach ($results['post_ids'] as $id) {
                    echo '<li><a href="' . get_edit_post_link($id) . '" target="_blank">' . get_the_title($id) . '</a></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            delete_transient('newsapi_rss_fetch_results_' . $post->ID);
        }

        // Check for fetch errors
        $error = get_transient('newsapi_rss_fetch_error_' . $post->ID);
        if ($error) {
            echo '<div class="notice notice-error"><p><strong>Error fetching RSS feeds:</strong> ' . esc_html($error['message']) . '</p>';
            if (!empty($error['trace'])) {
                echo '<pre>' . esc_html($error['trace']) . '</pre>';
            }
            echo '</div>';
            delete_transient('newsapi_rss_fetch_error_' . $post->ID);
        }
    }

    public static function saveMetaBox(int $postId): void
    {
        if (!isset($_POST['newsapi_rss_stream_details_nonce_field']) || !wp_verify_nonce($_POST['newsapi_rss_stream_details_nonce_field'], 'newsapi_rss_stream_details_nonce')) {
            return;
        }

        // Check user capability
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $categoryId = (int)($_POST['rss_stream_category'] ?? 0);
        $tags = isset($_POST['rss_stream_tags']) ? array_map('intval', (array)$_POST['rss_stream_tags']) : [];
        $feeds = isset($_POST['rss_stream_feeds']) ? sanitize_textarea_field($_POST['rss_stream_feeds']) : '';
        $amount = (int)($_POST['rss_stream_amount'] ?? 0);
        $maxSize = (int)($_POST['rss_stream_max_size'] ?? 0);
        $post_status = sanitize_text_field($_POST['stream_post_status'] ?? 'draft');

        update_post_meta($postId, '_rss_stream_post_status', $post_status);
        update_post_meta($postId, '_rss_stream_category', $categoryId);
        update_post_meta($postId, '_rss_stream_tags', $tags);
        update_post_meta($postId, '_rss_stream_feeds', $feeds);
        update_post_meta($postId, '_rss_stream_amount', $amount);
        update_post_meta($postId, '_rss_stream_max_size', $maxSize);

        // Check if Fetch was clicked
        if (isset($_POST['fetch_rss'])) {
            self::fetchAndProcessRSS($postId);
        }
    }

    public static function fetchAndProcessRSS(int $postId): void
    {
        $debugMode = (bool) get_option('newsapi_debug', false);

        $categoryId = (int) get_post_meta($postId, '_rss_stream_category', true);
        $tags = (array) get_post_meta($postId, '_rss_stream_tags', true);
        $feeds = get_post_meta($postId, '_rss_stream_feeds', true);
        $amount = (int) get_post_meta($postId, '_rss_stream_amount', true);
        $maxSize = (int) get_post_meta($postId, '_rss_stream_max_size', true);
        $postStatus = get_post_meta($postId, '_rss_stream_post_status', true);

        // Convert feeds string into an array of feeds
        $feedsArray = array_filter(array_map('trim', explode("\n", $feeds)));

        try {
            $articles = [];
            foreach ($feedsArray as $feed) {
                // Fetch articles from this feed
                $feedArticles = (new \NewsApiPlugin\Services\RssReaderService)->readUrl($feed, $amount);

                // Convert SimpleXMLElement objects to arrays
                $feedArticlesArray = array_map(function ($item) {
                    return json_decode(json_encode($item), true);
                }, $feedArticles);

                // Merge with main articles array
                $articles = array_merge($articles, $feedArticlesArray);

                // If we reached maxSize, stop adding more
                if ($maxSize > 0 && count($articles) > $maxSize) {
                    $articles = array_slice($articles, 0, $maxSize);
                    break;
                }
            }

            if ($debugMode) {
                set_transient('newsapi_rss_raw_response_' . $postId, $articles, 300);
            }

            // Publish the fetched items using WPNewsPublisherService
            $publisher = new \NewsApiPlugin\Services\WPNewsPublisherService();
            $result = $publisher->publishNews($articles, $categoryId, $tags, $postStatus, 'post');

            set_transient('newsapi_rss_fetch_results_' . $postId, $result, 300);
        } catch (\Exception $e) {
            $errorData = [
                'message' => $e->getMessage(),
            ];

            if ($debugMode) {
                $errorData['trace'] = $e->getTraceAsString();
            }

            set_transient('newsapi_rss_fetch_error_' . $postId, $errorData, 300);
        }
    }


}
