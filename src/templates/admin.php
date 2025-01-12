<div class="wrap">
    <h1>NewsAPI Plugin</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('newsapi-plugin-options');
        do_settings_sections('newsapi-plugin');
        submit_button();
        ?>
    </form>
</div>
