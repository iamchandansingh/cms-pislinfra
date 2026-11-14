<?php
/**
 * Plugin Name: Pislinfra Headless CMS
 * Plugin URI: https://pislinfra.com
 * Description: Complete Headless CMS architecture for Pislinfra React frontend: CPTs, Taxonomies, Single-Type Page Managers, Media/PDF galleries, SEO metadata, ACF integration, Strapi-compatible REST API, workflows, and security.
 * Version: 1.0.0
 * Author: Pislinfra Engineering
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: pislinfra-cms
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PISL_CMS_VERSION', '1.0.0');
define('PISL_CMS_DIR', plugin_dir_path(__FILE__));
define('PISL_CMS_URL', plugin_dir_url(__FILE__));

require_once PISL_CMS_DIR . 'includes/class-schemas.php';
require_once PISL_CMS_DIR . 'includes/class-post-types.php';
require_once PISL_CMS_DIR . 'includes/class-media-helper.php';
require_once PISL_CMS_DIR . 'includes/class-security-cors.php';
require_once PISL_CMS_DIR . 'includes/class-workflows.php';
require_once PISL_CMS_DIR . 'includes/class-field-renderer.php';
require_once PISL_CMS_DIR . 'includes/class-admin-metaboxes.php';
require_once PISL_CMS_DIR . 'includes/class-admin-columns.php';
require_once PISL_CMS_DIR . 'includes/class-admin-projects.php';
require_once PISL_CMS_DIR . 'includes/class-admin-settings-hub.php';
require_once PISL_CMS_DIR . 'includes/class-admin-seo.php';
require_once PISL_CMS_DIR . 'includes/class-single-types-admin.php';
require_once PISL_CMS_DIR . 'includes/class-admin-menu.php';
require_once PISL_CMS_DIR . 'includes/class-rest-api.php';
require_once PISL_CMS_DIR . 'includes/class-preview-auth.php';
require_once PISL_CMS_DIR . 'includes/class-acf-integration.php';

final class PISL_Headless_CMS {
    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        PISL_Post_Types::init();
        PISL_Media_Helper::init();
        PISL_Security_CORS::init();
        PISL_Workflows::init();
        PISL_Admin_Metaboxes::init();
        PISL_Admin_Columns::init();
        PISL_Single_Types_Admin::init();
        PISL_Admin_Settings_Hub::init();
        PISL_Admin_SEO::init();
        PISL_Admin_Menu::init();
        PISL_REST_API::init();
        PISL_Preview_Auth::init();
        PISL_ACF_Integration::init();

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
    }

    public function enqueue_admin_assets(string $hook): void {
        if (!is_admin()) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('pisl-cms-admin', PISL_CMS_URL . 'assets/admin.css', [], PISL_CMS_VERSION);
        wp_enqueue_script('pisl-cms-admin', PISL_CMS_URL . 'assets/admin.js', ['jquery', 'jquery-ui-sortable'], PISL_CMS_VERSION, true);
        wp_localize_script('pisl-cms-admin', 'PISL_CMS_ADMIN', [
            'restBase' => esc_url_raw(rest_url('pislinfra/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
        ]);
    }

    public function register_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'pisl_headless_overview',
            'Pislinfra Headless CMS Overview',
            [$this, 'render_dashboard_widget']
        );
    }

    public function render_dashboard_widget(): void {
        $counts = [
            'Blogs'               => wp_count_posts('post')->publish ?? 0,
            'Ongoing Projects'    => wp_count_posts('ongoing_project')->publish ?? 0,
            'Completed Projects'  => wp_count_posts('completed_project')->publish ?? 0,
            'Case Studies'        => wp_count_posts('case_study')->publish ?? 0,
            'Awards'              => wp_count_posts('award')->publish ?? 0,
            'Clients'             => wp_count_posts('client')->publish ?? 0,
            'Client Appreciations'=> wp_count_posts('client_appreciation')->publish ?? 0,
            'Coverage Markers'    => wp_count_posts('coverage_marker')->publish ?? 0,
            'Job Openings'        => wp_count_posts('job_opening')->publish ?? 0,
            'Solution Pages'      => wp_count_posts('solution_page')->publish ?? 0,
            'Policy Documents'    => wp_count_posts('policy_document')->publish ?? 0,
        ];
        $frontend = defined('PISL_FRONTEND_URL') ? PISL_FRONTEND_URL : 'http://localhost:5173';
        echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-bottom:14px;">';
        foreach ($counts as $label => $num) {
            echo '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 10px;">';
            echo '<strong style="font-size:18px;color:#052A73;">' . intval($num) . '</strong><br>';
            echo '<span style="font-size:12px;color:#475569;">' . esc_html($label) . '</span></div>';
        }
        echo '</div>';
        echo '<p style="margin:0;display:flex;gap:8px;flex-wrap:wrap;">';
        echo '<a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=pisl-pages-hub')) . '">All Pages</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=pisl-all-projects')) . '">All Projects</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=pisl-seo-management')) . '">SEO Management</a> ';
        echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url($frontend) . '">View React Website</a></p>';
    }

    public static function activate(): void {
        PISL_Post_Types::register_post_types_and_taxonomies();
        self::configure_roles();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function configure_roles(): void {
        $editor = get_role('editor');
        $author = get_role('author');
        foreach ([$editor, $author] as $role) {
            if (!$role) continue;
            $role->add_cap('upload_files');
            $role->add_cap('edit_posts');
        }
        if ($editor) {
            $editor->add_cap('manage_options'); // Allows managing Pislinfra Single-Type Page Settings
            $editor->add_cap('manage_categories');
        }
    }
}

register_activation_hook(__FILE__, ['PISL_Headless_CMS', 'activate']);
register_deactivation_hook(__FILE__, ['PISL_Headless_CMS', 'deactivate']);

add_action('plugins_loaded', ['PISL_Headless_CMS', 'instance']);
