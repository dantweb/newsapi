<div class="wrap">
    <h1>Manage News Streams</h1>
    <form method="post">
        <h2>Create New Stream</h2>
        <p>
            <label for="stream_name">Stream Name:</label>
            <input type="text" name="stream_name" id="stream_name" required>
        </p>
        <p>
            <label for="parameters">API Parameters (JSON):</label>
            <textarea name="parameters" id="parameters" rows="4" required></textarea>
        </p>
        <p>
            <label for="category_id">Category ID:</label>
            <input type="number" name="category_id" id="category_id" required>
        </p>
        <p><input type="submit" value="Add Stream" class="button button-primary"></p>
    </form>

    <h2>Existing Streams</h2>
    <table class="widefat fixed" cellspacing="0">
        <thead>
        <tr>
            <th>Name</th>
            <th>Parameters</th>
            <th>Category ID</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($streams as $stream): ?>
            <tr>
                <td><?php echo esc_html($stream['name']); ?></td>
                <td><?php echo esc_html($stream['parameters']); ?></td>
                <td><?php echo esc_html($stream['category_id']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
