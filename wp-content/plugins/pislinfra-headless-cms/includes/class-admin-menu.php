<?php
/**
 * Enterprise WordPress Admin Menu Hierarchy for PISL Headless CMS.
 *
 * Organizes all content into 13 top-level menus matching the editorial workflow:
 * 1. Dashboard
 * 2. Pages
 * 3. Blog
 * 4. Projects
 * 5. Case Studies
 * 6. Awards & Recognition
 * 7. Clients
 * 8. Careers
 * 9. Coverage
 * 10. Policies & Governance
 * 11. Media Library
 * 12. SEO Management
 * 13. Website Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Admin_Menu {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_organized_menus'], 25);
        add_action('admin_menu', [__CLASS__, 'cleanup_duplicate_or_unused_menus'], 999);
        add_filter('custom_menu_order', '__return_true');
        add_filter('menu_order', [__CLASS__, 'enforce_menu_order']);
        add_filter('parent_file', [__CLASS__, 'fix_active_parent_file']);
        add_filter('submenu_file', [__CLASS__, 'fix_active_submenu_file'], 10, 2);
    }

    public static function register_organized_menus(): void {
        // 2. Pages
        add_menu_page('Pages', 'Pages', 'edit_posts', 'pisl-pages-hub', ['PISL_Single_Types_Admin', 'render_hub_page'], 'dashicons-admin-page', 20);
        add_submenu_page('pisl-pages-hub', 'All Pages', 'All Pages', 'edit_posts', 'pisl-pages-hub', ['PISL_Single_Types_Admin', 'render_hub_page']);
        add_submenu_page('pisl-pages-hub', 'Add New Page', 'Add New Page', 'edit_posts', 'post-new.php?post_type=solution_page');

        $page_single_submenus = [
            'home-page'          => 'Home Page',
            'about-page'         => 'About Us',
            'leadership-page'    => 'Leadership',
            'ehs-page'           => 'EHS',
            'csr-page'           => 'CSR',
            'awards-page'        => 'Awards Overview',
        ];
        foreach ($page_single_submenus as $slug => $label) {
            self::add_single_type_submenu('pisl-pages-hub', $slug, $label);
        }

        add_submenu_page('pisl-pages-hub', 'Solutions Overview', 'Solutions Overview', 'edit_posts', 'edit.php?post_type=solution_page');

        foreach ([
            'contact-page'       => 'Contact Us',
            'governance-page'    => 'Company Policies',
            'annual-report-page' => 'Annual Reports',
        ] as $slug => $label) {
            self::add_single_type_submenu('pisl-pages-hub', $slug, $label);
        }

        // 3. Blog (core edit.php top-level menu + Blog Settings + Press Releases)
        self::add_single_type_submenu('edit.php', 'blog-page', 'Blog Settings');
        add_submenu_page('edit.php', 'Press Releases', 'Press Releases', 'edit_posts', 'edit.php?post_type=press_release');

        // 4. Projects
        add_menu_page('Projects', 'Projects', 'edit_posts', 'pisl-all-projects', ['PISL_Admin_Projects', 'render_all_projects_page'], 'dashicons-building', 22);
        add_submenu_page('pisl-all-projects', 'All Projects', 'All Projects', 'edit_posts', 'pisl-all-projects', ['PISL_Admin_Projects', 'render_all_projects_page']);
        add_submenu_page('pisl-all-projects', 'Ongoing Projects', 'Ongoing Projects', 'edit_posts', 'edit.php?post_type=ongoing_project');
        add_submenu_page('pisl-all-projects', 'Add Ongoing Project', 'Add Ongoing Project', 'edit_posts', 'post-new.php?post_type=ongoing_project');
        add_submenu_page('pisl-all-projects', 'Completed Projects', 'Completed Projects', 'edit_posts', 'edit.php?post_type=completed_project');
        add_submenu_page('pisl-all-projects', 'Add Completed Project', 'Add Completed Project', 'edit_posts', 'post-new.php?post_type=completed_project');
        add_submenu_page('pisl-all-projects', 'Project Categories', 'Project Categories', 'manage_categories', 'edit-tags.php?taxonomy=project_category&post_type=ongoing_project');
        add_submenu_page('pisl-all-projects', 'Project Settings', 'Project Settings', 'edit_posts', 'pisl-project-settings', ['PISL_Admin_Projects', 'render_project_settings_page']);

        // 5. Case Studies
        add_menu_page('Case Studies', 'Case Studies', 'edit_posts', 'edit.php?post_type=case_study', '', 'dashicons-portfolio', 24);
        add_submenu_page('edit.php?post_type=case_study', 'All Case Studies', 'All Case Studies', 'edit_posts', 'edit.php?post_type=case_study');
        add_submenu_page('edit.php?post_type=case_study', 'Add New Case Study', 'Add New Case Study', 'edit_posts', 'post-new.php?post_type=case_study');
        add_submenu_page('edit.php?post_type=case_study', 'Categories', 'Categories', 'manage_categories', 'edit-tags.php?taxonomy=project_category&post_type=case_study');
        self::add_single_type_submenu('edit.php?post_type=case_study', 'case-study-page', 'Case Study Settings');

        // 6. Awards & Recognition
        add_menu_page('Awards & Recognition', 'Awards & Recognition', 'edit_posts', 'edit.php?post_type=award', '', 'dashicons-awards', 26);
        add_submenu_page('edit.php?post_type=award', 'All Awards', 'All Awards', 'edit_posts', 'edit.php?post_type=award');
        add_submenu_page('edit.php?post_type=award', 'Add New Award', 'Add New Award', 'edit_posts', 'post-new.php?post_type=award');
        add_submenu_page('edit.php?post_type=award', 'Award Categories', 'Award Categories', 'manage_categories', 'edit-tags.php?taxonomy=award_category&post_type=award');
        add_submenu_page('edit.php?post_type=award', 'Milestones & Timeline', 'Milestones & Timeline', 'edit_posts', 'edit.php?post_type=milestone');
        add_submenu_page('edit.php?post_type=award', 'Awards Page Settings', 'Awards Page Settings', 'edit_posts', 'admin.php?page=pisl-single-awards-page');

        // 7. Clients
        add_menu_page('Clients', 'Clients', 'edit_posts', 'edit.php?post_type=client', '', 'dashicons-groups', 28);
        add_submenu_page('edit.php?post_type=client', 'All Clients', 'All Clients', 'edit_posts', 'edit.php?post_type=client');
        add_submenu_page('edit.php?post_type=client', 'Add New Client', 'Add New Client', 'edit_posts', 'post-new.php?post_type=client');
        add_submenu_page('edit.php?post_type=client', 'Client Categories', 'Client Categories', 'manage_categories', 'edit-tags.php?taxonomy=client_category&post_type=client');
        add_submenu_page('edit.php?post_type=client', 'Client Appreciation', 'Client Appreciation', 'edit_posts', 'edit.php?post_type=client_appreciation');
        add_submenu_page('edit.php?post_type=client', 'Client Settings', 'Client Settings', 'edit_posts', 'pisl-client-settings', ['PISL_Admin_Settings_Hub', 'render_client_settings_page']);

        // 8. Careers
        add_menu_page('Careers', 'Careers', 'edit_posts', 'edit.php?post_type=job_opening', '', 'dashicons-id-alt', 30);
        add_submenu_page('edit.php?post_type=job_opening', 'All Job Openings', 'All Job Openings', 'edit_posts', 'edit.php?post_type=job_opening');
        add_submenu_page('edit.php?post_type=job_opening', 'Add New Job', 'Add New Job', 'edit_posts', 'post-new.php?post_type=job_opening');
        add_submenu_page('edit.php?post_type=job_opening', 'Departments', 'Departments', 'manage_categories', 'edit-tags.php?taxonomy=job_department&post_type=job_opening');
        self::add_single_type_submenu('edit.php?post_type=job_opening', 'career-page', 'Careers Page Settings');

        // 9. Coverage
        add_menu_page('Coverage', 'Coverage', 'edit_posts', 'edit.php?post_type=coverage_marker', '', 'dashicons-location-alt', 32);
        add_submenu_page('edit.php?post_type=coverage_marker', 'All Locations', 'All Locations', 'edit_posts', 'edit.php?post_type=coverage_marker');
        add_submenu_page('edit.php?post_type=coverage_marker', 'Add Location', 'Add Location', 'edit_posts', 'post-new.php?post_type=coverage_marker');
        add_submenu_page('edit.php?post_type=coverage_marker', 'Coverage Map Settings', 'Coverage Map Settings', 'edit_posts', 'pisl-coverage-settings', ['PISL_Admin_Settings_Hub', 'render_coverage_settings_page']);

        // 10. Policies & Governance
        add_menu_page('Policies & Governance', 'Policies & Governance', 'edit_posts', 'edit.php?post_type=policy_document', '', 'dashicons-shield', 34);
        add_submenu_page('edit.php?post_type=policy_document', 'All Documents', 'All Documents', 'edit_posts', 'edit.php?post_type=policy_document');
        add_submenu_page('edit.php?post_type=policy_document', 'Add New Document', 'Add New Document', 'edit_posts', 'post-new.php?post_type=policy_document');
        add_submenu_page('edit.php?post_type=policy_document', 'Policy Categories', 'Policy Categories', 'manage_categories', 'edit-tags.php?taxonomy=policy_category&post_type=policy_document');
        add_submenu_page('edit.php?post_type=policy_document', 'Governance Settings', 'Governance Settings', 'edit_posts', 'pisl-governance-settings', ['PISL_Admin_Settings_Hub', 'render_governance_settings_page']);

        // 12. SEO Management (11. Media Library uses native upload.php ordered cleanly via menu_order)
        add_menu_page('SEO Management', 'SEO Management', 'edit_posts', 'pisl-seo-management', ['PISL_Admin_SEO', 'render_dashboard'], 'dashicons-chart-line', 38);
        add_submenu_page('pisl-seo-management', 'SEO Dashboard', 'SEO Dashboard', 'edit_posts', 'pisl-seo-management', ['PISL_Admin_SEO', 'render_dashboard']);
        add_submenu_page('pisl-seo-management', 'All Pages SEO', 'All Pages SEO', 'edit_posts', 'pisl-seo-all-pages', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-all-pages', 'All Pages SEO', 'Manage page-level SEO titles, descriptions, canonicals, and indexing for core website pages.', ['single_types']);
        });
        add_submenu_page('pisl-seo-management', 'Blog SEO', 'Blog SEO', 'edit_posts', 'pisl-seo-blog', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-blog', 'Blog SEO', 'Optimize article headlines, meta descriptions, keywords, and social metadata across all blog posts.', ['post']);
        });
        add_submenu_page('pisl-seo-management', 'Projects SEO', 'Projects SEO', 'edit_posts', 'pisl-seo-projects', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-projects', 'Projects SEO', 'Manage SEO metadata for ongoing and completed infrastructure project pages.', ['ongoing_project', 'completed_project']);
        });
        add_submenu_page('pisl-seo-management', 'Case Studies SEO', 'Case Studies SEO', 'edit_posts', 'pisl-seo-case-studies', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-case-studies', 'Case Studies SEO', 'Manage search visibility, schema, and meta snippets for engineering case studies.', ['case_study']);
        });
        add_submenu_page('pisl-seo-management', 'Awards SEO', 'Awards SEO', 'edit_posts', 'pisl-seo-awards', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-awards', 'Awards SEO', 'Manage SEO metadata for awards, recognitions, and milestone entries.', ['award']);
        });
        add_submenu_page('pisl-seo-management', 'Clients SEO', 'Clients SEO', 'edit_posts', 'pisl-seo-clients', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-clients', 'Clients SEO', 'Manage SEO metadata and social previews for client portfolio records.', ['client']);
        });
        add_submenu_page('pisl-seo-management', 'Careers SEO', 'Careers SEO', 'edit_posts', 'pisl-seo-careers', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-careers', 'Careers SEO', 'Manage SEO metadata for published career roles and job postings.', ['job_opening']);
        });
        add_submenu_page('pisl-seo-management', 'Solutions SEO', 'Solutions SEO', 'edit_posts', 'pisl-seo-solutions', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-solutions', 'Solutions SEO', 'Manage SEO metadata across PISL solution landing pages.', ['solution_page']);
        });
        add_submenu_page('pisl-seo-management', 'Policies SEO', 'Policies SEO', 'edit_posts', 'pisl-seo-policies', static function (): void {
            PISL_Admin_SEO::render_section_seo('pisl-seo-policies', 'Policies SEO', 'Manage SEO metadata for company policy and governance records.', ['policy_document']);
        });
        add_submenu_page('pisl-seo-management', 'Meta Titles & Descriptions', 'Meta Titles & Descriptions', 'edit_posts', 'pisl-seo-meta', static function (): void {
            PISL_Admin_SEO::render_bulk_editor('pisl-seo-meta', 'meta', 'Meta Titles & Descriptions', 'Bulk edit titles, meta descriptions, and focus keywords with live character indicators across pages and posts.');
        });
        add_submenu_page('pisl-seo-management', 'XML Sitemap', 'XML Sitemap', 'manage_options', 'pisl-seo-sitemap', ['PISL_Admin_SEO', 'render_sitemap_page']);
        add_submenu_page('pisl-seo-management', 'Schema & Structured Data', 'Schema & Structured Data', 'manage_options', 'pisl-seo-schema', ['PISL_Admin_SEO', 'render_schema_page']);
        add_submenu_page('pisl-seo-management', 'Canonical URLs', 'Canonical URLs', 'edit_posts', 'pisl-seo-canonicals', static function (): void {
            PISL_Admin_SEO::render_bulk_editor('pisl-seo-canonicals', 'canonical', 'Canonical URLs', 'Inspect and override canonical URLs across all pages, single types, and custom post types.');
        });
        add_submenu_page('pisl-seo-management', 'Robots & Indexing', 'Robots & Indexing', 'edit_posts', 'pisl-seo-robots', static function (): void {
            PISL_Admin_SEO::render_bulk_editor('pisl-seo-robots', 'robots', 'Robots & Indexing', 'Control global robots.txt directives and page-level noindex / nofollow indexing switches.');
        });
        add_submenu_page('pisl-seo-management', 'Redirects', 'Redirects', 'manage_options', 'pisl-seo-redirects', ['PISL_Admin_SEO', 'render_redirects_page']);
        add_submenu_page('pisl-seo-management', '404 Monitor', '404 Monitor', 'manage_options', 'pisl-seo-404', ['PISL_Admin_SEO', 'render_404_page']);
        add_submenu_page('pisl-seo-management', 'Social Sharing Metadata', 'Social Sharing Metadata', 'edit_posts', 'pisl-seo-social', static function (): void {
            PISL_Admin_SEO::render_bulk_editor('pisl-seo-social', 'social', 'Social Sharing Metadata', 'Manage global and per-page Open Graph & Twitter card titles, descriptions, and share images.');
        });
        add_submenu_page('pisl-seo-management', 'SEO Settings', 'SEO Settings', 'manage_options', 'pisl-seo-settings', ['PISL_Admin_SEO', 'render_settings_page']);

        // 13. Website Settings
        add_menu_page('Website Settings', 'Website Settings', 'edit_posts', 'pisl-single-header', '', 'dashicons-admin-settings', 40);
        self::add_single_type_submenu('pisl-single-header', 'top-header', 'Top Header Bar');
        self::add_single_type_submenu('pisl-single-header', 'header', 'Main Header & Navigation');
        self::add_single_type_submenu('pisl-single-header', 'footer', 'Footer Settings');
        add_submenu_page('pisl-single-header', 'Global Contact Details', 'Global Contact Details', 'edit_posts', 'pisl-settings-contact', ['PISL_Admin_Settings_Hub', 'render_contact_settings_page']);
        add_submenu_page('pisl-single-header', 'Social Media Links', 'Social Media Links', 'edit_posts', 'pisl-settings-social', ['PISL_Admin_Settings_Hub', 'render_social_settings_page']);
        add_submenu_page('pisl-single-header', 'Website Logo & Favicon', 'Website Logo & Favicon', 'edit_posts', 'pisl-settings-branding', ['PISL_Admin_Settings_Hub', 'render_branding_settings_page']);
        add_submenu_page('pisl-single-header', 'API & Integration Settings', 'API & Integration Settings', 'edit_posts', 'pisl-settings-integrations', ['PISL_Admin_Settings_Hub', 'render_integrations_settings_page']);

        // Hidden-from-sidebar direct single-type handlers so All Pages / Settings tabs / WpAdminBar still resolve directly
        foreach (['ongoing-page', 'completed-page', 'warehouse-page', 'policy-page'] as $hidden_slug) {
            self::add_single_type_submenu(null, $hidden_slug, PISL_Schemas::get_single_types()[$hidden_slug]['title'] ?? $hidden_slug);
        }
    }

    private static function add_single_type_submenu(?string $parent_slug, string $single_slug, string $menu_label): void {
        $types = PISL_Schemas::get_single_types();
        if (!isset($types[$single_slug])) {
            return;
        }
        $cfg = $types[$single_slug];
        add_submenu_page(
            $parent_slug,
            $cfg['title'],
            $menu_label,
            'edit_posts',
            'pisl-single-' . $single_slug,
            function () use ($single_slug, $cfg) {
                PISL_Single_Types_Admin::render_single_type_page($single_slug, $cfg);
            }
        );
    }

    public static function cleanup_duplicate_or_unused_menus(): void {
        // Rename "Media" to "Media Library" cleanly
        global $menu;
        if (is_array($menu)) {
            foreach ($menu as $k => $item) {
                if (($item[2] ?? '') === 'upload.php') {
                    $menu[$k][0] = 'Media Library';
                }
            }
        }

        // Remove duplicate default WP "Pages" and unused "Comments" from top-level sidebar
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('edit-comments.php');

        // Keep WordPress system/developer tools hidden from Editors so non-technical admins see a clean CMS sidebar
        if (!current_user_can('manage_network') && !in_array('administrator', wp_get_current_user()->roles, true)) {
            remove_menu_page('themes.php');
            remove_menu_page('plugins.php');
            remove_menu_page('tools.php');
            remove_menu_page('options-general.php');
            remove_menu_page('edit.php?post_type=acf-field-group');
        }
    }

    public static function enforce_menu_order(array $order): array {
        $desired = [
            'index.php',
            'pisl-pages-hub',
            'edit.php',
            'pisl-all-projects',
            'edit.php?post_type=case_study',
            'edit.php?post_type=award',
            'edit.php?post_type=client',
            'edit.php?post_type=job_opening',
            'edit.php?post_type=coverage_marker',
            'edit.php?post_type=policy_document',
            'upload.php',
            'pisl-seo-management',
            'pisl-single-header',
            'separator1',
            'separator2',
            'separator-last',
        ];
        $remaining = array_values(array_diff($order, $desired));
        return array_values(array_merge($desired, $remaining));
    }

    public static function fix_active_parent_file(?string $parent_file): ?string {
        global $current_screen, $pagenow;
        if (!$current_screen) {
            return $parent_file;
        }
        $pt  = $current_screen->post_type;
        $tax = $current_screen->taxonomy;

        if ($pt === 'solution_page') {
            return 'pisl-pages-hub';
        }
        if ($pt === 'press_release') {
            return 'edit.php';
        }
        if (in_array($pt, ['ongoing_project', 'completed_project'], true) || in_array($tax, ['project_state'], true)) {
            return 'pisl-all-projects';
        }
        if ($tax === 'project_category') {
            return ($pt === 'case_study') ? 'edit.php?post_type=case_study' : 'pisl-all-projects';
        }
        if ($pt === 'milestone') {
            return 'edit.php?post_type=award';
        }
        if ($pt === 'client_appreciation' || $tax === 'client_category') {
            return 'edit.php?post_type=client';
        }
        if ($tax === 'policy_category') {
            return 'edit.php?post_type=policy_document';
        }
        if ($pagenow === 'admin.php' && isset($_GET['page'])) {
            $page = sanitize_key(wp_unslash($_GET['page']));
            if ($page === 'pisl-single-awards-page') {
                // Keep Awards Overview under Pages unless accessed from Awards, or highlight Awards cleanly if user came from Awards
                return $parent_file;
            }
        }
        return $parent_file;
    }

    public static function fix_active_submenu_file(?string $submenu_file, string $parent_file): ?string {
        global $current_screen;
        if (!$current_screen) {
            return $submenu_file;
        }
        $pt  = $current_screen->post_type;
        $tax = $current_screen->taxonomy;

        if ($pt === 'solution_page' && $current_screen->base === 'post' && ($current_screen->action ?? '') === 'add') {
            return 'post-new.php?post_type=solution_page';
        }
        if ($pt === 'solution_page') {
            return 'edit.php?post_type=solution_page';
        }
        if ($pt === 'press_release') {
            return 'edit.php?post_type=press_release';
        }
        if ($pt === 'ongoing_project' || $pt === 'completed_project') {
            $is_add = $current_screen->base === 'post' && ($current_screen->action ?? '') === 'add';
            return ($is_add ? 'post-new.php' : 'edit.php') . '?post_type=' . $pt;
        }
        if ($pt === 'milestone') {
            return 'edit.php?post_type=milestone';
        }
        if ($pt === 'client_appreciation') {
            return 'edit.php?post_type=client_appreciation';
        }
        if ($tax === 'project_category') {
            return $pt === 'case_study'
                ? 'edit-tags.php?taxonomy=project_category&post_type=case_study'
                : 'edit-tags.php?taxonomy=project_category&post_type=ongoing_project';
        }
        if ($tax === 'client_category') {
            return 'edit-tags.php?taxonomy=client_category&post_type=client';
        }
        if ($tax === 'policy_category') {
            return 'edit-tags.php?taxonomy=policy_category&post_type=policy_document';
        }
        return $submenu_file;
    }
}
