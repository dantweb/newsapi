<div class="wrap">
    <h1>Manage Runners</h1>
    <form method="post">
        <h2>Create New Runner</h2>
        <p>
            <label for="stream_id">Stream:</label>
            <select name="stream_id" id="stream_id">
                <?php foreach ($streams as $index => $stream): ?>
                    <option value="<?php echo $index; ?>"><?php echo esc_html($stream['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="schedule">Schedule (e.g., hourly, daily):</label>
            <input type="text" name="schedule" id="schedule" required>
        </p>
        <p><input type="submit" value="Add Runner" class="button button-primary"></p>
    </form>

    <h2>Existing Runners</h2>
    <table class="widefat fixed" cellspacing="0">
        <thead>
        <tr>
            <th>Stream</th>
            <th>Schedule</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($runners as $runner): ?>
            <tr>
                <td><?php echo esc_html($streams[$runner['stream_id']]['name'] ?? 'Unknown'); ?></td>
                <td><?php echo esc_html($runner['schedule']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
