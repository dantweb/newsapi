<?php
declare(strict_types=1);

namespace NewsApiPlugin;

use NewsApiPlugin\Admin\BlogScraperMetaBox;
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
        add_action('add_meta_boxes', [StreamMetaBox::class, 'register']);
        add_action('add_meta_boxes', [RunnerMetaBox::class, 'register']);

        add_action('admin_init', [\NewsApiPlugin\Admin\SettingsPage::class, 'registerSettings']);
        StreamMetaBox::register();
        RSSStreamMetaBox::register();
        BlogScraperMetaBox::register();

        add_action('template_redirect', [__CLASS__, 'restrictFeedAccess']);
        add_action('init', [self::class, 'addActions']);
    }

    public static function restrictFeedAccess(): void
    {
        $secret_token = 'your_secret_token_here';

        if (is_feed() && (!isset($_GET['token']) || $_GET['token'] !== $secret_token)) {
            wp_die('Access to this feed is restricted. Please provide the correct token.');
        }
    }


    public static function registerPostTypes(): void
    {
        register_post_type('newsapi_stream', [
            'label' => 'NewsAPI Streams',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'newsapi-plugin',
            'supports' => ['title'], // remove 'editor'
            'menu_icon' => 'dashicons-rss',
        ]);


        // New RSS streams CPT
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

    }

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


    public static function filter_feed_query($query): \WP_Query
    {
        // Check if this is a feed request
        if (!$query->is_feed || !isset($_GET['token'])) {
//            error_log('[filter_feed_query] Not a feed query or token missing.');
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



    public static function addActions(): void
    {
        add_action('the_post', [self::class, 'mark_post_as_read']);
        add_filter('pre_get_posts', [self::class, 'filter_feed_query']);
        add_action('the_post', function ($post) {
            error_log('[RSS Feed] Processing post: ' . $post->post_title . ' (ID: ' . $post->ID . ')');
        });

    }


    public static function mark_post_as_read(\WP_Post $post): void
    {
        if (is_feed() && isset($_GET['mark_as_read']) && $_GET['mark_as_read'] === 'true') {
            update_post_meta($post->ID, 'read_status', 'read');
            update_post_meta($post->ID, 'read_timestamp', current_time('mysql'));
        }
    }

// The main "NewsAPI" admin page could be a dashboard or just an intro page
    public static function renderAdminPage(): void
    {
        echo '<div class="wrap"><h1>NewsAPI Dashboard</h1><p>Welcome to the NewsAPI plugin admin.</p></div>';
    }

    public static function count_posts_by_meta($meta_key, $meta_value): ?string
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
