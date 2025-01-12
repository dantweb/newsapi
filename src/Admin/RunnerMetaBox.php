<?php
declare(strict_types=1);

namespace NewsApiPlugin\Admin;

class RunnerMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('save_post_newsapi_runner', [self::class, 'saveMetaBox']);
    }

    public static function addMetaBox(): void
    {
        add_meta_box(
            'newsapi_runner_details',
            'Runner Details',
            [self::class, 'renderMetaBox'],
            'newsapi_runner',
            'advanced',
            'default'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        // Fetch all streams
        $streams = get_posts([
            'post_type'   => 'newsapi_stream',
            'numberposts' => -1,
        ]);

        // Get existing runner metadata
        $selectedStream = get_post_meta($post->ID, '_runner_stream', true);
        $schedule = get_post_meta($post->ID, '_runner_schedule', true);

        // Render the fields
        echo '<p><label>Associated Stream:</label>';
        echo '<select name="runner_stream">';
        foreach ($streams as $stream) {
            $selected = $stream->ID === (int) $selectedStream ? 'selected' : '';
            echo '<option value="' . $stream->ID . '" ' . $selected . '>' . esc_html($stream->post_title) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label>Schedule (Cron):</label>';
        echo '<input type="text" name="runner_schedule" value="' . esc_attr($schedule) . '" placeholder="e.g., hourly, daily"></p>';
    }

    public static function saveMetaBox(int $postId): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Validate and save data
        if (isset($_POST['runner_stream'], $_POST['runner_schedule'])) {
            update_post_meta($postId, '_runner_stream', sanitize_text_field($_POST['runner_stream']));
            update_post_meta($postId, '_runner_schedule', sanitize_text_field($_POST['runner_schedule']));
        }
    }
}
