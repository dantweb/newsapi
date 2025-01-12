<?php
declare(strict_types=1);

namespace NewsApiPlugin\Admin;

class StreamsPage
{
    public static function register(): void
    {
        add_menu_page(
            'News Streams',
            'News Streams',
            'read',
            'newsapi-streams',
            [self::class, 'render'],
            'dashicons-rss',
            101
        );
    }

    public static function render(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stream_name'], $_POST['parameters'], $_POST['category_id'])) {
            $streams = get_option('newsapi_streams', []);
            $streams[] = [
                'name' => sanitize_text_field($_POST['stream_name']),
                'parameters' => sanitize_text_field($_POST['parameters']),
                'category_id' => (int)$_POST['category_id'],
            ];
            update_option('newsapi_streams', $streams);
            echo '<div class="notice notice-success is-dismissible"><p>Stream added successfully!</p></div>';
        }

        $streams = get_option('newsapi_streams', []);
        include __DIR__ . '/../templates/admin-streams.php';
    }
}
