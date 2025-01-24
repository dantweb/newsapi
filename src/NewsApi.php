<?php
declare(strict_types=1);

namespace NewsApiPlugin;

use NewsApiPlugin\Admin\BlogScraperMetaBox;
use NewsApiPlugin\Admin\GoogleScholarMetaBox;
use NewsApiPlugin\Admin\PubMedMetaBox;
use NewsApiPlugin\Admin\RSSStreamMetaBox;
use NewsApiPlugin\Admin\SettingsPage;
use NewsApiPlugin\Admin\StreamMetaBox;
use NewsApiPlugin\Admin\RunnerMetaBox;

class NewsApi
{
    public static function init(): void
    {
        // Register custom post types
        add_action('init', [self::class, 'registerPostTypes']);

        // Register admin menu (after CPTs are registered so the menu items appear correctly)
        add_action('admin_menu', [self::class, 'registerAdminMenu']);

        // Register settings and meta boxes
        add_action('admin_init', [SettingsPage::class, 'register']);
        add_action('add_meta_boxes', [StreamMetaBox::class, 'register'], 10);
        add_action('add_meta_boxes', [RunnerMetaBox::class, 'register'], 20);
        add_action('add_meta_boxes', [PubMedMetaBox::class, 'register'], 30);
        add_action('add_meta_boxes', [GoogleScholarMetaBox::class, 'register'], 40);

        add_action('admin_init', [\NewsApiPlugin\Admin\SettingsPage::class, 'registerSettings']);
        StreamMetaBox::register();
        RSSStreamMetaBox::register();
        BlogScraperMetaBox::register();
        PubMedMetaBox::register();
        GoogleScholarMetaBox::register();

        add_action('template_redirect', [__CLASS__, 'restrictFeedAccess']);
        add_action('init', [self::class, 'addActions']);
    }

    /**
     * Check if the `newsapi_google_scholar` post type is created.
     *
     * @return bool
     */
    public static function isGoogleScholarPostTypeCreated(): bool
    {
        return post_type_exists('newsapi_google_scholar');
    }

    /**
     * Restrict access to the feed based on a secret token.
     */
    public static function restrictFeedAccess(): void
    {
        $secret_token = 'your_secret_token_here';

        if (is_feed() && (!isset($_GET['token']) || $_GET['token'] !== $secret_token)) {
            wp_die('Access to this feed is restricted. Please provide the correct token.');
        }
    }

    /**
     * Register custom post types.
     */
    public static function registerPostTypes(): void
    {
        register_post_type('newsapi_stream', [
            'label' => 'NewsAPI Streams',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'newsapi-plugin',
            'supports' => ['title'],
            'menu_icon' => 'dashicons-rss',
        ]);

        register_post_type('newsapi_rss_stream', [
            'label' => 'RSS Streams',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'newsapi-plugin',
            'supports' => ['title'],
            'menu_icon' => 'dashicons-rss',
        ]);

        register_post_type('newsapi_runner', [
            'label' => 'Runners',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'newsapi-plugin',
            'supports' => ['title', 'editor', 'custom-fields'],
            'menu_icon' => 'dashicons-clock',
        ]);

        register_post_type('newsapi_blog_scraper', [
            'label' => 'Blog Scrapers',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'newsapi-plugin',
            'supports' => ['title'],
            'menu_icon' => 'dashicons-admin-site',
        ]);

        register_post_type('newsapi_pubmed', [
            'label' => 'PubMed',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'newsapi-plugin',
            'supports' => ['title'],
            'menu_icon' => 'dashicons-admin-site',
        ]);

        register_post_type('newsapi_gs', [
            'label' => 'Google Scholar',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'newsapi-plugin',
            'supports' => ['title'],
            'menu_icon' => 'dashicons-admin-site',
        ]);
    }

    /**
     * Register the admin menu.
     */
    public static function registerAdminMenu(): void
    {
        // Top-level menu: NewsAPI
        add_menu_page(
            'NewsAPI Plugin',
            'NewsAPI',
            'manage_options',
            'newsapi-plugin',
            [self::class, 'renderAdminPage'],
            'dashicons-admin-site',
            100
        );

        // Settings submenu
        add_submenu_page(
            'newsapi-plugin',
            'Settings',
            'Settings',
            'manage_options',
            'newsapi-settings',
            [SettingsPage::class, 'render']
        );

        add_submenu_page(
            'newsapi-plugin',
            'Marked Posts',
            'Marked Posts',
            'manage_options',
            'newsapi-marked-posts',
            [self::class, 'renderMarkedPostsPage']
        );
    }

    /**
     * Filter the feed query based on custom parameters.
     *
     * @param \WP_Query $query
     * @return \WP_Query
     */
    public static function filterFeedQuery(\WP_Query $query): \WP_Query
    {
        // Check if this is a feed request
        if (!$query->is_feed || !isset($_GET['token'])) {
            return $query;
        }

        error_log('[filter_feed_query] RSS feed detected.');

        // Ensure correct post type
        $query->set('post_type', 'post');

        // Handle `show_only_unread`
        if ($query->is_feed() && $query->is_main_query()) {
            // Handle the 'show_on_page' parameter
            if (isset($_GET['show_on_page']) && is_numeric($_GET['show_on_page'])) {
                $query->set('posts_per_rss', (int) $_GET['show_on_page']);
            }

            // Handle the 'show_unread_only' parameter
            if (isset($_GET['show_unread_only']) && $_GET['show_unread_only'] === 'true') {
                $meta_query = $query->get('meta_query', []);
                $meta_query[] = [
                    'key' => 'read_status',
                    'value' => 'unread',
                    'compare' => '='
                ];
                $query->set('meta_query', $meta_query);
            }
        }

        return $query;
    }

    /**
     * Add actions for feed processing.
     */
    public static function addActions(): void
    {
        add_action('the_post', [self::class, 'markPostAsRead']);
        add_filter('pre_get_posts', [self::class, 'filterFeedQuery']);
        add_action('the_post', function ($post) {
            error_log('[RSS Feed] Processing post: ' . $post->post_title . ' (ID: ' . $post->ID . ')');
        });
    }

    /**
     * Mark a post as read when accessed via the feed.
     *
     * @param \WP_Post $post
     */
    public static function markPostAsRead(\WP_Post $post): void
    {
        if (is_feed() && isset($_GET['mark_as_read']) && $_GET['mark_as_read'] === 'true') {
            update_post_meta($post->ID, 'read_status', 'read');
            update_post_meta($post->ID, 'read_timestamp', current_time('mysql'));
        }
    }

    /**
     * Render the main admin page.
     */
    public static function renderAdminPage(): void
    {
        echo '<div class="wrap"><h1>NewsAPI Dashboard</h1><p>Welcome to the NewsAPI plugin admin.</p></div>';
    }

    /**
     * Count posts by meta key and value.
     *
     * @param string $meta_key
     * @param string $meta_value
     * @return string|null
     */
    public static function countPostsByMeta(string $meta_key, string $meta_value): ?string
    {
        global $wpdb;
        if ($meta_value == 'all') {
            return $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_status = 'publish'");
        }
        return $wpdb->get_var("
            SELECT COUNT(*) FROM $wpdb->posts p
            INNER JOIN $wpdb->postmeta pm ON p.ID = pm.post_id
            WHERE p.post_status = 'publish'
            AND pm.meta_key = '$meta_key'
            AND pm.meta_value = '$meta_value'
        ");
    }

    /**
     * Render the Marked Posts page.
     */
    public static function renderMarkedPostsPage(): void
    {
        $table = new MarkedPostsTable();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1>Marked Posts</h1>
            <?php $table->display() ?>
        </div>
        <?php
    }
}