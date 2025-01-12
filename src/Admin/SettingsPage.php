<?php

declare(strict_types=1);

namespace NewsApiPlugin\Admin;

class SettingsPage
{
    public static function register(): void
    {
        // Hook our settings registration into admin_init
        add_action('admin_init', [self::class, 'registerSettings']);
    }

    public static function registerSettings(): void
    {
        // Register a setting and add the fields/sections
        register_setting('newsapi_settings', 'newsapi_api_key');

        add_settings_section(
            'newsapi_main',
            'NewsAPI Configuration',
            function() {
                echo '<p>Enter your NewsAPI credentials below.</p>';
            },
            'newsapi-settings'
        );

        add_settings_field(
            'newsapi_api_key',
            'API Key',
            function() {
                $value = esc_attr(get_option('newsapi_api_key', ''));
                echo '<input type="text" name="newsapi_api_key" value="' . $value . '" class="regular-text">';
            },
            'newsapi-settings',
            'newsapi_main'
        );

        register_setting('newsapi_settings', 'newsapi_debug');

        add_settings_field(
            'newsapi_debug',
            'Debug Mode',
            function() {
                $value = get_option('newsapi_debug', '');
                echo '<input type="checkbox" name="newsapi_debug" value="1" ' . checked(1, $value, false) . '> Enable Debug Mode';
            },
            'newsapi-settings',
            'newsapi_main'
        );

        // Register fields for AI Config
        register_setting('newsapi_settings', 'newsapi_ai_openai_api_key');
        register_setting('newsapi_settings', 'newsapi_ai_url');
        register_setting('newsapi_settings', 'newsapi_ai_model');
        register_setting('newsapi_settings', 'newsapi_ai_max_tokens');
        register_setting('newsapi_settings', 'newsapi_ai_temperature');
        register_setting('newsapi_settings', 'newsapi_ai_prompt');

        // Add fields to the AI Config section
        add_settings_field(
            'newsapi_ai_url',
            'AI API Base URL',
            function() {
                $value = esc_attr(get_option('newsapi_ai_url', 'https://'));
                echo '<input type="text" name="newsapi_ai_url" value="' . $value . '" class="regular-text">';
            },
            'newsapi-settings',
            'newsapi_main'
        );

       add_settings_field(
            'newsapi_ai_openai_api_key',
            'AI API Key',
            function() {
                $value = esc_attr(get_option('newsapi_ai_openai_api_key', 'https://'));
                echo '<input type="text" name="newsapi_ai_openai_api_key" value="' . $value . '" class="regular-text">';
            },
            'newsapi-settings',
            'newsapi_main'
        );

        add_settings_field(
            'newsapi_ai_model',
            'Model',
            function() {
                $value = esc_attr(get_option('newsapi_ai_model', 'deepseek-chat'));
                echo '<input type="text" name="newsapi_ai_model" value="' . $value . '" class="regular-text">';
            },
            'newsapi-settings',
            'newsapi_main'
        );

        add_settings_field(
            'newsapi_ai_max_tokens',
            'Max Tokens',
            function() {
                $value = esc_attr(get_option('newsapi_ai_max_tokens', 500));
                echo '<input type="number" name="newsapi_ai_max_tokens" value="' . $value . '" class="regular-text">';
            },
            'newsapi-settings',
            'newsapi_main'
        );

        add_settings_field(
            'newsapi_ai_temperature',
            'Temperature',
            function() {
                $value = esc_attr(get_option('newsapi_ai_temperature', 0.7));
                echo '<input type="number" step="0.1" name="newsapi_ai_temperature" value="' . $value . '" class="regular-text">';
            },
            'newsapi-settings',
            'newsapi_main'
        );

        add_settings_field(
            'newsapi_ai_prompt',
            'Prompt',
            function() {
                $value = esc_textarea(get_option('newsapi_ai_prompt', "Analyze the following HTML content and return a JSON array of URLs that represent published posts. Only include URLs that are definitely links to published posts. The response must be a valid JSON array. Here is the HTML:\n\n"));
                echo '<textarea name="newsapi_ai_prompt" rows="5" class="large-text">' . $value . '</textarea>';
            },
            'newsapi-settings',
            'newsapi_main'
        );
    }

    public static function render(): void
    {
        include __DIR__ . '/../templates/admin-settings.php';
    }
}