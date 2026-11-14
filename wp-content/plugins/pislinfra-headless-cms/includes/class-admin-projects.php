<?php
/**
 * Unified "All Projects" WP_List_Table (combining Ongoing & Completed Projects)
 * and "Project Settings" admin screen.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PISL_Projects_List_Table extends \WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'project',
            'plural'   => 'projects',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array {
        return [
            'cb'           => '<input type="checkbox" />',
            'thumb'        => 'Image',
            'title'        => 'Project Name',
            'project_type' => 'Project Type',
            'category'     => 'Category',
            'client'       => 'Client',
            'location'     => 'Location / State',
            'seo'          => 'SEO',
            'status'       => 'Status / Date',
        ];
    }

    protected function get_sortable_columns(): array {
        return [
            'title'        => ['title', false],
            'project_type' => ['post_type', false],
            'status'       => ['date', true],
        ];
    }

    protected function get_bulk_actions(): array {
        return [
            'publish' => 'Publish Selected',
            'draft'   => 'Move to Draft',
            'trash'   => 'Move to Trash',
        ];
    }

    public function process_bulk_action(): void {
        $action = $this->current_action();
        if (!$action || empty($_POST['project']) || !is_array($_POST['project'])) {
            return;
        }
        check_admin_referer('bulk-' . $this->_args['plural']);
        foreach ($_POST['project'] as $raw_id) {
            $pid = (int) $raw_id;
            if ($pid <= 0 || !current_user_can('edit_post', $pid)) {
                continue;
            }
            if ($action === 'publish') {
                wp_update_post(['ID' => $pid, 'post_status' => 'publish']);
            } elseif ($action === 'draft') {
                wp_update_post(['ID' => $pid, 'post_status' => 'draft']);
            } elseif ($action === 'trash' && current_user_can('delete_post', $pid)) {
                wp_trash_post($pid);
            }
        }
        PISL_Security_CORS::bump_cache_version();
    }

    public function prepare_items(): void {
        $this->process_bulk_action();

        $per_page = 20;
        $paged    = max(1, (int) ($_GET['paged'] ?? 1));
        $type_f   = isset($_GET['ptype']) ? sanitize_key(wp_unslash($_GET['ptype'])) : 'all';
        $cat_f    = isset($_GET['project_category']) ? sanitize_text_field(wp_unslash($_GET['project_category'])) : '';
        $state_f  = isset($_GET['project_state']) ? sanitize_text_field(wp_unslash($_GET['project_state'])) : '';
        $status_f = isset($_GET['pstatus']) ? sanitize_key(wp_unslash($_GET['pstatus'])) : 'any';
        $search   = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $orderby  = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'date';
        $order    = (isset($_GET['order']) && strtolower((string) $_GET['order']) === 'asc') ? 'ASC' : 'DESC';

        $post_types = ($type_f === 'ongoing_project' || $type_f === 'completed_project')
            ? [$type_f]
            : ['ongoing_project', 'completed_project'];

        $statuses = in_array($status_f, ['publish', 'draft', 'pending', 'trash'], true)
            ? [$status_f]
            : ['publish', 'draft', 'pending', 'future'];

        $tax_query = [];
        if ($cat_f !== '') {
            $tax_query[] = ['taxonomy' => 'project_category', 'field' => 'slug', 'terms' => $cat_f];
        }
        if ($state_f !== '') {
            $tax_query[] = ['taxonomy' => 'project_state', 'field' => 'slug', 'terms' => $state_f];
        }

        $args = [
            'post_type'      => $post_types,
            'post_status'    => $statuses,
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => in_array($orderby, ['title', 'post_type', 'date'], true) ? $orderby : 'date',
            'order'          => $order,
            's'              => $search,
        ];
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $q = new \WP_Query($args);
        $this->items = $q->posts;
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $this->set_pagination_args([
            'total_items' => (int) $q->found_posts,
            'per_page'    => $per_page,
            'total_pages' => (int) $q->max_num_pages,
        ]);
    }

    protected function column_cb($item): string {
        return '<input type="checkbox" name="project[]" value="' . intval($item->ID) . '" />';
    }

    protected function column_thumb($item): string {
        $f = PISL_Admin_Columns::get_fields((int) $item->ID);
        $imgs = is_array($f['images'] ?? null) ? $f['images'] : [];
        return PISL_Admin_Columns::render_thumb_html($imgs[0] ?? 0, (int) $item->ID);
    }

    protected function column_title($item): string {
        $edit_link = get_edit_post_link($item->ID);
        $rest_type = PISL_Preview_Auth::get_rest_type_for_post_type($item->post_type);
        $route     = PISL_Preview_Auth::get_frontend_route_for_post($item);
        $preview   = PISL_Preview_Auth::build_preview_url($rest_type, (string) $item->ID, $route);

        $actions = [
            'edit'    => '<a href="' . esc_url($edit_link) . '">Edit</a>',
            'preview' => '<a href="' . esc_url($preview) . '" target="_blank" rel="noopener noreferrer">Preview on Site</a>',
        ];
        if ($item->post_type === 'ongoing_project' && $item->post_status !== 'trash') {
            $move_url = wp_nonce_url(
                admin_url('admin.php?action=pisl_move_to_completed&post=' . $item->ID),
                'pisl_move_row_' . $item->ID
            );
            $actions['move'] = '<a href="' . esc_url($move_url) . '" style="color:#059669;font-weight:600;" onclick="return confirm(\'Move to Completed Projects?\')">Move to Completed</a>';
        }
        $trash_link = get_delete_post_link($item->ID);
        if ($trash_link) {
            $actions['trash'] = '<a href="' . esc_url($trash_link) . '" class="submitdelete">Trash</a>';
        }

        return '<strong><a class="row-title" href="' . esc_url($edit_link) . '">' . esc_html($item->post_title ?: '(Untitled)') . '</a></strong>' . $this->row_actions($actions);
    }

    protected function column_default($item, $column_name): string {
        $f = PISL_Admin_Columns::get_fields((int) $item->ID);
        switch ($column_name) {
            case 'project_type':
                return $item->post_type === 'ongoing_project'
                    ? '<span class="pisl-badge pisl-badge-warn">Ongoing</span>'
                    : '<span class="pisl-badge pisl-badge-pub">Completed</span>';
            case 'category':
                $terms = wp_get_post_terms($item->ID, 'project_category', ['fields' => 'names']);
                return esc_html((!is_wp_error($terms) && !empty($terms)) ? implode(', ', $terms) : ((string) ($f['category'] ?? '—')));
            case 'client':
                return esc_html((string) ($f['client'] ?? '—'));
            case 'location':
                $st = wp_get_post_terms($item->ID, 'project_state', ['fields' => 'names']);
                $st_name = (!is_wp_error($st) && !empty($st)) ? implode(', ', $st) : (string) ($f['state'] ?? '');
                $loc = trim((string) ($f['location'] ?? ''));
                return esc_html($loc ?: ($st_name ?: '—'));
            case 'seo':
                return PISL_Admin_Columns::render_seo_badge((int) $item->ID, $f);
            case 'status':
                $st_label = ucfirst((string) $item->post_status);
                return '<strong>' . esc_html($st_label) . '</strong><br><small>' . esc_html(get_the_date('M j, Y', $item)) . '</small>';
            default:
                return '—';
        }
    }
}

class PISL_Admin_Projects {
    public static function render_all_projects_page(): void {
        $table = new PISL_Projects_List_Table();
        $table->prepare_items();

        $ong_c  = wp_count_posts('ongoing_project');
        $comp_c = wp_count_posts('completed_project');
        $ong_tot  = (int) ($ong_c->publish ?? 0) + (int) ($ong_c->draft ?? 0);
        $comp_tot = (int) ($comp_c->publish ?? 0) + (int) ($comp_c->draft ?? 0);

        $cur_type   = isset($_GET['ptype']) ? sanitize_key(wp_unslash($_GET['ptype'])) : 'all';
        $cur_status = isset($_GET['pstatus']) ? sanitize_key(wp_unslash($_GET['pstatus'])) : 'any';
        $cur_cat    = isset($_GET['project_category']) ? sanitize_text_field(wp_unslash($_GET['project_category'])) : '';
        $cur_state  = isset($_GET['project_state']) ? sanitize_text_field(wp_unslash($_GET['project_state'])) : '';

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">All Projects</h1> ';
        echo '<a href="' . esc_url(admin_url('post-new.php?post_type=ongoing_project')) . '" class="page-title-action">Add Ongoing Project</a> ';
        echo '<a href="' . esc_url(admin_url('post-new.php?post_type=completed_project')) . '" class="page-title-action">Add Completed Project</a>';
        echo '<hr class="wp-header-end">';

        echo '<ul class="subsubsub">';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=pisl-all-projects')) . '" class="' . ($cur_type === 'all' ? 'current' : '') . '">All <span class="count">(' . ($ong_tot + $comp_tot) . ')</span></a> | </li>';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=pisl-all-projects&ptype=ongoing_project')) . '" class="' . ($cur_type === 'ongoing_project' ? 'current' : '') . '">Ongoing <span class="count">(' . $ong_tot . ')</span></a> | </li>';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=pisl-all-projects&ptype=completed_project')) . '" class="' . ($cur_type === 'completed_project' ? 'current' : '') . '">Completed <span class="count">(' . $comp_tot . ')</span></a> | </li>';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=pisl-all-projects&pstatus=draft')) . '" class="' . ($cur_status === 'draft' ? 'current' : '') . '">Drafts</a> | </li>';
        echo '<li><a href="' . esc_url(admin_url('admin.php?page=pisl-all-projects&pstatus=trash')) . '" class="' . ($cur_status === 'trash' ? 'current' : '') . '">Trash</a></li>';
        echo '</ul>';

        echo '<form method="get" style="clear:both;margin:10px 0;">';
        echo '<input type="hidden" name="page" value="pisl-all-projects" />';
        if ($cur_type !== 'all') echo '<input type="hidden" name="ptype" value="' . esc_attr($cur_type) . '" />';
        wp_dropdown_categories([
            'show_option_all' => 'All Project Categories',
            'taxonomy'        => 'project_category',
            'name'            => 'project_category',
            'value_field'     => 'slug',
            'selected'        => $cur_cat,
            'hierarchical'    => true,
            'hide_empty'      => false,
        ]);
        echo ' ';
        wp_dropdown_categories([
            'show_option_all' => 'All Project States',
            'taxonomy'        => 'project_state',
            'name'            => 'project_state',
            'value_field'     => 'slug',
            'selected'        => $cur_state,
            'hierarchical'    => false,
            'hide_empty'      => false,
        ]);
        submit_button('Filter', 'secondary', '', false, ['style' => 'margin-left:6px;']);
        $table->search_box('Search Projects', 'pisl_projects');
        echo '</form>';

        echo '<form method="post">';
        $table->display();
        echo '</form></div>';
    }

    public static function render_project_settings_page(): void {
        $tab = (isset($_GET['tab']) && $_GET['tab'] === 'completed') ? 'completed' : 'ongoing';
        $slug = $tab === 'completed' ? 'completed-page' : 'ongoing-page';
        $types = PISL_Schemas::get_single_types();

        echo '<div class="wrap">';
        echo '<h2 class="nav-tab-wrapper" style="margin-bottom:16px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pisl-project-settings&tab=ongoing')) . '" class="nav-tab ' . ($tab === 'ongoing' ? 'nav-tab-active' : '') . '">Ongoing Projects Page Settings</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pisl-project-settings&tab=completed')) . '" class="nav-tab ' . ($tab === 'completed' ? 'nav-tab-active' : '') . '">Completed Projects Page Settings</a>';
        echo '<a href="' . esc_url(admin_url('edit-tags.php?taxonomy=project_state&post_type=ongoing_project')) . '" class="nav-tab">Manage Project States &nearr;</a>';
        echo '</h2>';
        if (isset($types[$slug])) {
            PISL_Single_Types_Admin::render_single_type_page($slug, $types[$slug]);
        }
        echo '</div>';
    }
}
