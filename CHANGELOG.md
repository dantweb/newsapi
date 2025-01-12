# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/), and this project adheres to [Semantic Versioning](https://semver.org/).

---

## [1.0.0] - 2024-12-08

### Added
- Initial release of the NewsAPI Plugin.
- Admin dashboard with support for:
    - Managing NewsAPI streams.
    - Managing RSS streams.
    - Scheduling runners for automated fetches.
    - Plugin settings page for API key and debug mode.
- WP-CLI support for running NewsAPI and RSS streams by ID:
    - `newsapi:run-stream` command with options for stream ID and type.
- NewsAPI integration to fetch news articles.
- RSS feed reader to fetch and process RSS feeds.
- Support for saving fetched articles as draft posts in WordPress.
- Debug mode to log API and RSS interactions for troubleshooting.

---

## [Unreleased]

### Planned
- Integration with additional news APIs.
- Enhanced scheduling options for runners.
- Support for fetching full article content via scraping.
- Improved error handling and logging.

---

## Notes

- This is the first stable release.
- Feedback and feature requests are welcome via [GitHub Issues](https://github.com/your-repo/newsapi-plugin/issues).

