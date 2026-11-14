<?php
/**
 * Registers all Custom Post Types and Taxonomies replacing Strapi collection types.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Post_Types {
    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_types_and_taxonomies'], 5);
        add_action('init', [__CLASS__, 'customize_builtin_post_labels'], 6);
    }

    public static function customize_builtin_post_labels(): void {
        global $wp_post_types;
        if (isset($wp_post_types['post'])) {
            $labels = $wp_post_types['post']->labels;
            $labels->name               = 'Blog';
            $labels->singular_name      = 'Blog Post';
            $labels->menu_name          = 'Blog';
            $labels->all_items          = 'All Posts';
            $labels->add_new            = 'Add New Post';
            $labels->add_new_item       = 'Add New Post';
            $labels->edit_item          = 'Edit Post';
            $wp_post_types['post']->menu_icon = 'dashicons-edit-page';
            $wp_post_types['post']->menu_position = 5;
        }
    }

    public static function register_post_types_and_taxonomies(): void {
        $cpts = [
            'ongoing_project' => [
                'singular'  => 'Ongoing Project',
                'plural'    => 'Ongoing Projects',
                'rest_base' => 'ongoing-projects',
                'icon'      => 'dashicons-hammer',
                'pos'       => 6,
                'supports'  => ['title', 'thumbnail', 'revisions'],
                'taxonomies'=> ['project_category', 'project_state'],
            ],
            'completed_project' => [
                'singular'  => 'Completed Project',
                'plural'    => 'Completed Projects',
                'rest_base' => 'completed-projects',
                'icon'      => 'dashicons-building',
                'pos'       => 7,
                'supports'  => ['title', 'thumbnail', 'revisions'],
                'taxonomies'=> ['project_category', 'project_state'],
            ],
            'case_study' => [
                'singular'  => 'Case Study',
                'plural'    => 'Case Studies',
                'rest_base' => 'case-studies',
                'icon'      => 'dashicons-portfolio',
                'pos'       => 8,
                'supports'  => ['title', 'thumbnail', 'revisions'],
                'taxonomies'=> ['project_category'],
            ],
            'coverage_marker' => [
                'singular'  => 'Coverage Marker',
                'plural'    => 'Coverage Markers',
                'rest_base' => 'coverage-markers',
                'icon'      => 'dashicons-location-alt',
                'pos'       => 9,
                'supports'  => ['title', 'thumbnail'],
                'taxonomies'=> ['project_state'],
            ],
            'award' => [
                'singular'  => 'Award',
                'plural'    => 'Awards',
                'rest_base' => 'awards',
                'icon'      => 'dashicons-awards',
                'pos'       => 11,
                'supports'  => ['title', 'thumbnail'],
                'taxonomies'=> ['award_category'],
            ],
            'client' => [
                'singular'  => 'Client',
                'plural'    => 'Clients',
                'rest_base' => 'clients',
                'icon'      => 'dashicons-groups',
                'pos'       => 12,
                'supports'  => ['title', 'thumbnail'],
                'taxonomies'=> ['client_category'],
            ],
            'client_appreciation' => [
                'singular'  => 'Client Appreciation',
                'plural'    => 'Client Appreciations',
                'rest_base' => 'client-appreciations',
                'icon'      => 'dashicons-testimonial',
                'pos'       => 13,
                'supports'  => ['title', 'thumbnail'],
                'taxonomies'=> ['client_category'],
            ],
            'job_opening' => [
                'singular'  => 'Job Opening',
                'plural'    => 'Job Openings',
                'rest_base' => 'job-openings',
                'icon'      => 'dashicons-id-alt',
                'pos'       => 14,
                'supports'  => ['title'],
                'taxonomies'=> ['job_department'],
            ],
            'solution_page' => [
                'singular'  => 'Solution Page',
                'plural'    => 'Solution Pages',
                'rest_base' => 'solution-pages',
                'icon'      => 'dashicons-admin-tools',
                'pos'       => 15,
                'supports'  => ['title', 'thumbnail', 'revisions'],
                'taxonomies'=> [],
            ],
            'policy_document' => [
                'singular'  => 'Policy Document',
                'plural'    => 'Policy Documents',
                'rest_base' => 'policy-documents',
                'icon'      => 'dashicons-media-document',
                'pos'       => 16,
                'supports'  => ['title', 'thumbnail'],
                'taxonomies'=> ['policy_category'],
            ],
            'milestone' => [
                'singular'  => 'Award Milestone',
                'plural'    => 'Award Milestones',
                'rest_base' => 'milestones',
                'icon'      => 'dashicons-flag',
                'pos'       => 17,
                'supports'  => ['title', 'thumbnail'],
                'taxonomies'=> [],
            ],
            'press_release' => [
                'singular'  => 'Press Release',
                'plural'    => 'Press Releases',
                'rest_base' => 'press-releases',
                'icon'      => 'dashicons-megaphone',
                'pos'       => 18,
                'supports'  => ['title', 'editor', 'thumbnail'],
                'taxonomies'=> [],
            ],
        ];

        foreach ($cpts as $slug => $cfg) {
            register_post_type($slug, [
                'labels' => [
                    'name'               => $cfg['plural'],
                    'singular_name'      => $cfg['singular'],
                    'add_new'            => 'Add New',
                    'add_new_item'       => 'Add New ' . $cfg['singular'],
                    'edit_item'          => 'Edit ' . $cfg['singular'],
                    'new_item'           => 'New ' . $cfg['singular'],
                    'view_item'          => 'View ' . $cfg['singular'],
                    'search_items'       => 'Search ' . $cfg['plural'],
                    'not_found'          => 'No ' . strtolower($cfg['plural']) . ' found',
                ],
                'public'              => true,
                'publicly_queryable'  => true,
                'show_ui'             => true,
                'show_in_menu'        => false,
                'show_in_rest'        => true,
                'rest_base'           => $cfg['rest_base'],
                'menu_position'       => $cfg['pos'],
                'menu_icon'           => $cfg['icon'],
                'supports'            => $cfg['supports'],
                'taxonomies'          => $cfg['taxonomies'],
                'has_archive'         => false,
                'rewrite'             => ['slug' => $cfg['rest_base'], 'with_front' => false],
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
            ]);
        }

        // Custom Taxonomies
        register_taxonomy('project_category', ['ongoing_project', 'completed_project', 'case_study'], [
            'labels'       => ['name' => 'Project Categories', 'singular_name' => 'Project Category'],
            'public'       => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base'    => 'project-categories',
        ]);

        register_taxonomy('project_state', ['ongoing_project', 'completed_project', 'coverage_marker'], [
            'labels'       => ['name' => 'Project States', 'singular_name' => 'Project State'],
            'public'       => true,
            'hierarchical' => false,
            'show_in_rest' => true,
            'rest_base'    => 'project-states',
        ]);

        register_taxonomy('award_category', ['award'], [
            'labels'       => ['name' => 'Award Categories', 'singular_name' => 'Award Category'],
            'public'       => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base'    => 'award-categories',
        ]);

        register_taxonomy('job_department', ['job_opening'], [
            'labels'       => ['name' => 'Job Departments', 'singular_name' => 'Job Department'],
            'public'       => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base'    => 'job-departments',
        ]);

        register_taxonomy('client_category', ['client', 'client_appreciation'], [
            'labels'       => ['name' => 'Client Categories', 'singular_name' => 'Client Category'],
            'public'       => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base'    => 'client-categories',
        ]);

        register_taxonomy('policy_category', ['policy_document'], [
            'labels'       => ['name' => 'Policy Categories', 'singular_name' => 'Policy Category'],
            'public'       => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rest_base'    => 'policy-categories',
        ]);
    }
}
