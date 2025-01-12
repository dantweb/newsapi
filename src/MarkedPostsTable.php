<?php
declare(strict_types=1);

namespace NewsApiPlugin;

class MarkedPostsTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'post',
            'plural' => 'posts',
            'ajax' => false,
        ]);

        $this->process_bulk_action();
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" class="cb-select" name="post_ids[]" value="%s" />',
            'title' => 'Title',
            'category' => 'Category',
            'date_created' => 'Date Created',
            'read_status' => 'Read Status',
            'read_timestamp' => 'Date Read',
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'date_created' => ['date_created', true],
            'read_timestamp' => ['read_timestamp', false],
        ];
    }

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" class="cb-select" name="post_ids[]" value="%s" />',
            esc_attr($item->ID)
        );
    }


    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'title':
                return sprintf('<a href="%s">%s</a>', get_edit_post_link($item->ID), $item->post_title);
            case 'category':
                $categories = get_the_category($item->ID);
                return !empty($categories) ? $categories[0]->name : 'Uncategorized';
            case 'date_created':
                return mysql2date(get_option('date_format'), $item->post_date);
            case 'read_status':
                $read_status = get_post_meta($item->ID, 'read_status', true);
                return $read_status ? ucfirst($read_status) : 'Unread';
            case 'read_timestamp':
                $timestamp = get_post_meta($item->ID, 'read_timestamp', true);
                return $timestamp ? mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp) : 'N/A';
            default:
                return '';
        }
    }

    public function get_bulk_actions(): array
    {
        return [
            'mark_read' => 'Mark as Read',
            'mark_unread' => 'Mark as Unread',
        ];
    }

    public function process_bulk_action(): void
    {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-posts')) {
            error_log('Invalid nonce');
            return;
        }

        // Check for post IDs
        if (empty($_POST['post_ids'])) {
            error_log('No post IDs received');
            return;
        }

        $post_ids = array_map('intval', $_POST['post_ids']);
        $action = $this->current_action();

        error_log('Bulk action: ' . $action);
        error_log('Post IDs: ' . print_r($post_ids, true));

        foreach ($post_ids as $post_id) {
            if ($action === 'mark_read') {
                update_post_meta($post_id, 'read_status', 'read');
                update_post_meta($post_id, 'read_timestamp', current_time('mysql'));
            } elseif ($action === 'mark_unread') {
                update_post_meta($post_id, 'read_status', 'unread');
                update_post_meta($post_id, 'read_timestamp', current_time('mysql'));
            }
        }

        // Add success message
        add_action('admin_notices', function () use ($action) {
            printf('<div class="notice notice-success is-dismissible"><p>Bulk action "%s" applied successfully.</p></div>', esc_html($action));
        });
    }


    public function prepare_items(): void
    {
        $per_page = max(1, intval($_POST['per_page'] ?? 20));
        $current_page = $this->get_pagenum();
        $orderby = $_POST['orderby'] ?? 'date_created';
        $order = $_POST['order'] ?? 'DESC';

        $status = $_POST['status'] ?? 'all';
        $category = $_POST['category'] ?? '';
        $date_start = $_POST['date_start'] ?? '';
        $date_end = $_POST['date_end'] ?? '';

        $meta_query = [];
        if ($status === 'read') {
            $meta_query[] = ['key' => 'read_status', 'value' => 'read', 'compare' => '='];
        } elseif ($status === 'unread') {
            $meta_query[] = [
                'relation' => 'OR',
                ['key' => 'read_status', 'compare' => 'NOT EXISTS'],
                ['key' => 'read_status', 'value' => '', 'compare' => '='],
            ];
        }

        $tax_query = [];
        if (!empty($category)) {
            $tax_query[] = ['taxonomy' => 'category', 'field' => 'slug', 'terms' => $category];
        }

        $date_query = [];
        if (!empty($date_start)) {
            $date_query['after'] = $date_start;
        }
        if (!empty($date_end)) {
            $date_query['before'] = $date_end;
        }

        $args = [
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'orderby' => $orderby,
            'order' => $order,
            'meta_query' => $meta_query,
            'tax_query' => $tax_query,
            'date_query' => $date_query,
        ];

        $this->items = get_posts($args);

        $total_items = count(get_posts(array_merge($args, ['fields' => 'ids'])));
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        // Set pagination arguments
        $this->set_pagination_args([
            'total_items' => $total_items, // Total number of items
            'per_page' => $per_page,      // Items per page
            'total_pages' => ceil($total_items / $per_page), // Total pages
        ]);
    }


    public function display_filters(): void
    {
        $categories = get_categories(['hide_empty' => false]);

        echo '<div class="alignleft actions">';
        // Category filter
        echo '<select name="category">';
        echo '<option value="">All Categories</option>';
        foreach ($categories as $category) {
            $selected = ($_POST['category'] ?? '') === $category->slug ? 'selected' : '';
            printf('<option value="%s" %s>%s</option>', $category->slug, $selected, $category->name);
        }
        echo '</select>';

        // Date filter
        echo '<input type="date" name="date_start" value="' . esc_attr($_POST['date_start'] ?? '') . '" placeholder="Start Date">';
        echo '<input type="date" name="date_end" value="' . esc_attr($_POST['date_end'] ?? '') . '" placeholder="End Date">';

        // Read status filter
        echo '<select name="status">';
        $statuses = ['all' => 'All', 'read' => 'Read', 'unread' => 'Unread'];
        foreach ($statuses as $value => $label) {
            $selected = ($_POST['status'] ?? 'all') === $value ? 'selected' : '';
            printf('<option value="%s" %s>%s</option>', $value, $selected, $label);
        }
        echo '</select>';

        // Per page filter
        echo '<input type="number" name="per_page" value="' . esc_attr($_POST['per_page'] ?? 20) . '" min="1" placeholder="Posts per page">';
        echo '<input type="submit" name="filter_action" id="post-query-submit" class="button" value="Filter">';
        echo '</div>';
    }


    public function display_rows(): void
    {
        foreach ($this->items as $item) {
            echo '<tr>';
            echo '<th scope="row" class="check-column">' . $this->column_cb($item) . '</th>';
            foreach ($this->get_columns() as $column_name => $column_display_name) {
                if ($column_name === 'cb') {
                    continue;
                }
                echo '<td>' . $this->column_default($item, $column_name) . '</td>';
            }
            echo '</tr>';
        }
    }

    public function display(): void
    {
        echo '<form method="post">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page'] ?? '') . '">';
        wp_nonce_field('bulk-posts', '_wpnonce');

        // Filters
        $this->display_filters();

        // Top Bulk Actions
        echo '<div class="tablenav top">';
        $this->bulk_actions('top');
        $this->pagination('top'); // Pagination at the top
        echo '<br class="clear">';
        echo '</div>';

        // Start Table
        echo '<table class="wp-list-table widefat fixed striped table-view-list posts">';
        $this->print_column_headers(); // Table headers
        echo '<tbody id="the-list">';
        $this->display_rows_or_placeholder(); // Table rows
        echo '</tbody>';
        $this->print_column_headers(false); // Footer headers
        echo '</table>';

        // Bottom Bulk Actions
        echo '<div class="tablenav bottom">';
        $this->bulk_actions('bottom');
        $this->pagination('bottom'); // Pagination at the bottom
        echo '<br class="clear">';
        echo '</div>';

        echo '</form>';
    }


    public function print_column_headers($with_id = true): void
    {
        $columns = $this->get_columns();
        echo '<thead>';
        echo '<tr>';
        foreach ($columns as $column_id => $column_name) {
            if ($column_id === 'cb') {
                // Add WordPress-specific bulk action checkbox
                echo '<th scope="col" id="cb" width="200px" class="manage-column column-cb check-column">';
                echo $column_name;
                echo '</th>';
            } else {
                $class = sprintf('class="manage-column column-%s"', esc_attr($column_id));
                echo sprintf('<th scope="col" %s>%s</th>', $class, esc_html($column_name));
            }
        }
        echo '</tr>';
        echo '</thead>';
    }

}
