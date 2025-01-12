<div class="wrap">
    <h1>NewsAPI Plugin Settings</h1>

    <!-- Add tabs for navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=newsapi-settings" class="nav-tab <?php echo !isset($_GET['tab']) || $_GET['tab'] === 'main' ? 'nav-tab-active' : ''; ?>">Main Settings</a>
    </h2>

    <form method="post" action="options.php">
        <?php
        settings_fields('newsapi_settings');

        // Display the appropriate section based on the active tab
        if (!isset($_GET['tab']) || $_GET['tab'] === 'main') {
            do_settings_sections('newsapi-settings');
        } elseif ($_GET['tab'] === 'ai') {
            do_settings_sections('newsapi-settings');
        }

        submit_button();
        ?>
    </form>
</div>