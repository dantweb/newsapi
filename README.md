# NewsAPI Plugin

The **NewsAPI Plugin** is a WordPress plugin that fetches news articles from [NewsAPI](https://newsapi.org/) or RSS feeds and stores them as draft posts in WordPress. It provides an admin interface for managing streams, scheduling runners, and configuring settings. For advanced users, WP-CLI commands allow programmatic management of streams and RSS feeds.

---

## Features

- Fetch news from NewsAPI or RSS feeds and save them as WordPress draft posts.
- Support for custom categories, tags, and content.
- Admin dashboard for managing streams and runners.
- WP-CLI commands for automation and programmatic usage.
- Debug mode for developers to inspect plugin operations.

---

## Installation

1. Clone or download the plugin to your WordPress `plugins` directory:
   ```bash
   git clone https://github.com/your-repo/newsapi-plugin.git wp-content/plugins/newsapi

2. Navigate to the Plugins section in your WordPress admin dashboard and activate the NewsAPI Plugin.

3. Go to Settings > NewsAPI to configure your API key and other options.


## Configuration

### NewsAPI Key

To fetch news using NewsAPI, you need an API key:

Navigate to Settings > NewsAPI in your WordPress admin.
Enter your API key in the API Key field and save.

### Debug Mode
Enable debug mode to log detailed interactions for troubleshooting:

Navigate to Settings > NewsAPI.
Check the Debug Mode box and save.

##Usage

###Admin Dashboard
#### Manage Streams:
Use the NewsAPI Streams menu to create and manage NewsAPI or RSS streams.

#### Manage Runners:
Schedule automatic fetching using the NewsAPI Runners menu.

## WP-CLI Commands
For advanced users, WP-CLI commands enable automation and programmatic interaction with the plugin.

### Run a NewsAPI or RSS Stream
Use the newsapi:run-stream command to run a specific stream.

Command:

````bash
wp newsapi:run-stream --id=<stream_id> --type=<stream_type>

````

Options:

--id (required): The ID of the stream or RSS stream.
--type (optional): The type of stream to run (stream for NewsAPI or rss for RSS). Defaults to stream.
Examples:

Run a NewsAPI Stream:

````bash
wp newsapi:run-stream --id=123 --type=news

````

```bash
wp newsapi:run-stream --id=456 --type=rss

````

## File Structure
bash
Copy code
newsapi/
├── src/
│   ├── Admin/                # Admin settings and interfaces
│   ├── Commands/             # WP-CLI commands
│   ├── Services/             # Business logic for news fetching
│   └── templates/            # HTML templates for admin pages
├── newsapi-plugin.php         # Main plugin entry point
├── README.md                  # Plugin documentation
└── wp-cli.yml                 # WP-CLI command configuration

### WP-CLI Setup
Ensure the plugin's CLI commands are registered by placing a wp-cli.yml file in the root of your WordPress installation (next to wp-config.php):

require:
   - wp-content/plugins/newsapi/src/Commands/NewsApiRunnerCommand.php
      This ensures WP-CLI can locate the custom commands provided by the plugin.


### Blog Scraper

The blog scraper allows you to scrape news articles directly from blog pages. It works by identifying news links on the page, clicking them, and scraping the content.

#### Usage

1. Go to **NewsAPI > Blog Scrapers** in the WordPress admin.
2. Add a new blog scraper and configure the URLs, category, tags, and other settings.
3. Click "Fetch" to scrape the articles and create draft posts.

#### Blog Scraper WP-CLI Command

You can also run the blog scraper manually using WP-CLI:

```bash
wp newsapi:run-blog-scraper --id=<scraper_id>
```

### Requirements
PHP 7.4 or higher
WordPress 5.9 or higher
WP-CLI (optional but recommended)
Support
For questions, issues, or feature requests, please open an issue on GitHub.

### License
This plugin is licensed under the GPL-2.0-or-later license. See the LICENSE file for details.


### Instructions
1. Replace placeholders like `https://github.com/your-repo/newsapi-plugin.git` with your actual repository URL.
2. Ensure the `LICENSE` file is added if required.
3. Place this file in the root of your plugin directory as `README.md`.