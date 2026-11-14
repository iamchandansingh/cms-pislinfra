<?php
/**
 * Registers clean WordPress Admin Meta Boxes for all CPTs & Blogs (post),
 * including the SEO Panel and "Move to Completed Project" workflow UI.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Admin_Metaboxes {
    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'register_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_meta_boxes'], 10, 2);
        add_filter('post_row_actions', [__CLASS__, 'add_ongoing_row_action'], 10, 2);
        add_action('admin_action_pisl_move_to_completed', [__CLASS__, 'handle_row_move_action']);
        add_action('admin_notices', [__CLASS__, 'render_admin_notices']);
    }

    public static function register_meta_boxes(): void {
        foreach (PISL_Schemas::get_cpt_schemas() as $post_type => $cfg) {
            add_meta_box(
                'pisl_fields_box_' . $post_type,
                'Content & Media Fields',
                function ($post) use ($cfg) {
                    wp_nonce_field('pisl_save_meta_nonce', 'pisl_meta_nonce');
                    $vals = get_post_meta($post->ID, '_pisl_fields', true);
                    if (!is_array($vals)) $vals = [];
                    if ($post->post_type === 'post' && empty($vals['featuredImage']) && has_post_thumbnail($post->ID)) {
                        $vals['featuredImage'] = (int) get_post_thumbnail_id($post->ID);
                    }
                    if ($post->post_type === 'award' && empty($vals['images']) && has_post_thumbnail($post->ID)) {
                        $vals['images'] = [(int) get_post_thumbnail_id($post->ID)];
                    }
                    if ($post->post_type === 'case_study' && empty($vals['image']) && has_post_thumbnail($post->ID)) {
                        $vals['image'] = (int) get_post_thumbnail_id($post->ID);
                    }
                    PISL_Field_Renderer::render_fields($cfg['fields'], $vals, 'pisl_fields');
                },
                $post_type,
                'normal',
                'high'
            );

            if (!empty($cfg['has_seo'])) {
                add_meta_box(
                    'pisl_seo_box_' . $post_type,
                    'SEO & Social Metadata',
                    function ($post) {
                        $seo = get_post_meta($post->ID, '_pisl_seo', true);
                        if (!is_array($seo)) $seo = [];
                        PISL_Field_Renderer::render_fields(PISL_Schemas::get_seo_fields(), $seo, 'pisl_seo');
                    },
                    $post_type,
                    'normal',
                    'default'
                );
            }
        }

        add_meta_box(
            'pisl_move_completed_box',
            'Project Lifecycle Actions',
            [__CLASS__, 'render_move_to_completed_box'],
            'ongoing_project',
            'side',
            'high'
        );

        add_meta_box(
            'pisl_preview_website_box',
            'React Frontend Preview',
            [__CLASS__, 'render_preview_website_box'],
            array_keys(PISL_Schemas::get_cpt_schemas()),
            'side',
            'high'
        );
    }

    public static function render_preview_website_box(\WP_Post $post): void {
        if ($post->post_status === 'auto-draft') {
            echo '<p class="description">Save as draft or publish first to preview on the React website.</p>';
            return;
        }
        $rest_type = PISL_Preview_Auth::get_rest_type_for_post_type($post->post_type);
        $route = PISL_Preview_Auth::get_frontend_route_for_post($post);
        $preview_url = PISL_Preview_Auth::build_preview_url($rest_type, (string) $post->ID, $route);
        echo '<p class="description">Preview saved draft or published content directly inside the React frontend without making drafts public.</p>';
        echo '<p><a href="' . esc_url($preview_url) . '" target="_blank" rel="noopener noreferrer" class="button button-primary" style="width:100%;text-align:center;">Preview on Website &nearr;</a></p>';
    }

    public static function render_move_to_completed_box(\WP_Post $post): void {
        if ($post->post_status === 'auto-draft') {
            echo '<p class="description">Publish or save this ongoing project first before promoting it to Completed Projects.</p>';
            return;
        }
        echo '<p>Promote this ongoing project cleanly into <strong>Completed Projects</strong>, preserve all gallery media &amp; SEO metadata, update its Coverage Marker, and remove it from Ongoing Projects.</p>';
        echo '<button type="button" id="pisl-move-to-completed-btn" class="button button-primary button-large" data-post-id="' . intval($post->ID) . '" style="width:100%;">Move to Completed Project</button>';
        echo '<p id="pisl-move-status" style="margin-top:8px;"></p>';
    }

    public static function save_meta_boxes(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['pisl_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pisl_meta_nonce'])), 'pisl_save_meta_nonce')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $schemas = PISL_Schemas::get_cpt_schemas();
        if (!isset($schemas[$post->post_type])) return;

        $cfg = $schemas[$post->post_type];
        $prev_fields = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($prev_fields)) $prev_fields = [];

        if (isset($_POST['pisl_fields']) && is_array($_POST['pisl_fields'])) {
            $clean = PISL_Field_Renderer::sanitize_fields($cfg['fields'], $_POST['pisl_fields']);

            // Bi-directional sync between Client Logo Image field and WP Featured Image
            if ($post->post_type === 'client') {
                $logo_id = !empty($clean['logo']) && is_numeric($clean['logo']) ? (int) $clean['logo'] : 0;
                $thumb_id = (int) get_post_thumbnail_id($post_id);
                $prev_logo_id = !empty($prev_fields['logo']) && is_numeric($prev_fields['logo']) ? (int) $prev_fields['logo'] : 0;

                if ($logo_id > 0) {
                    set_post_thumbnail($post_id, $logo_id);
                    $clean['logoUrl'] = wp_get_attachment_url($logo_id) ?: ($clean['logoUrl'] ?? '');
                    update_post_meta($post_id, 'logo', $logo_id);
                } elseif ($prev_logo_id > 0 && $logo_id === 0) {
                    // Explicit removal in Content & Media Fields clears featured image too
                    delete_post_thumbnail($post_id);
                    $clean['logo']    = null;
                    $clean['logoUrl'] = '';
                    delete_post_meta($post_id, 'logo');
                } elseif ($thumb_id > 0) {
                    // User uploaded via WordPress Featured Image panel while logo field was empty
                    $clean['logo']    = $thumb_id;
                    $clean['logoUrl'] = wp_get_attachment_url($thumb_id) ?: '';
                    update_post_meta($post_id, 'logo', $thumb_id);
                } else {
                    $clean['logo'] = null;
                    delete_post_meta($post_id, 'logo');
                }
            }

            // Sync Blog Featured Image field with WP Featured Image without letting Gutenberg meta-box-loader wipe _thumbnail_id
            if ($post->post_type === 'post') {
                $img_id      = !empty($clean['featuredImage']) && is_numeric($clean['featuredImage']) ? (int) $clean['featuredImage'] : 0;
                $thumb_id    = (int) get_post_thumbnail_id($post_id);
                $prev_img_id = !empty($prev_fields['featuredImage']) && is_numeric($prev_fields['featuredImage']) ? (int) $prev_fields['featuredImage'] : 0;
                $is_gutenberg_metabox_save = !empty($_REQUEST['meta-box-loader']);

                if ($thumb_id > 0) {
                    if ($img_id > 0 && $img_id !== $prev_img_id) {
                        set_post_thumbnail($post_id, $img_id);
                        $clean['featuredImage'] = $img_id;
                    } else {
                        $clean['featuredImage'] = $thumb_id;
                    }
                } elseif ($img_id > 0 && ($img_id !== $prev_img_id || !$is_gutenberg_metabox_save)) {
                    set_post_thumbnail($post_id, $img_id);
                    $clean['featuredImage'] = $img_id;
                } else {
                    $clean['featuredImage'] = null;
                }
            }

            // Sync Award Gallery Images field with WP Featured Image without letting Gutenberg meta-box-loader wipe _thumbnail_id
            if ($post->post_type === 'award') {
                $thumb_id = (int) get_post_thumbnail_id($post_id);
                $gallery  = is_array($clean['images'] ?? null) ? array_values(array_filter($clean['images'])) : [];
                $first_id = 0;
                foreach ($gallery as $g_item) {
                    if (is_numeric($g_item) && (int) $g_item > 0) {
                        $first_id = (int) $g_item;
                        break;
                    }
                }
                if ($first_id > 0) {
                    if ($thumb_id === 0 || $gallery !== ($prev_fields['images'] ?? [])) {
                        set_post_thumbnail($post_id, $first_id);
                    }
                    $clean['images'] = $gallery;
                } elseif ($thumb_id > 0) {
                    $clean['images'] = [$thumb_id];
                }
            }

            // Sync Case Study Cover Image with WP Featured Image without letting Gutenberg meta-box-loader wipe _thumbnail_id
            if ($post->post_type === 'case_study') {
                $img_id      = !empty($clean['image']) && is_numeric($clean['image']) ? (int) $clean['image'] : 0;
                $thumb_id    = (int) get_post_thumbnail_id($post_id);
                $prev_img_id = !empty($prev_fields['image']) && is_numeric($prev_fields['image']) ? (int) $prev_fields['image'] : 0;
                $is_gutenberg_metabox_save = !empty($_REQUEST['meta-box-loader']);

                if ($thumb_id > 0) {
                    if ($img_id > 0 && $img_id !== $prev_img_id) {
                        set_post_thumbnail($post_id, $img_id);
                        $clean['image'] = $img_id;
                    } else {
                        $clean['image'] = $thumb_id;
                    }
                } elseif ($img_id > 0 && ($img_id !== $prev_img_id || !$is_gutenberg_metabox_save)) {
                    set_post_thumbnail($post_id, $img_id);
                    $clean['image'] = $img_id;
                } else {
                    foreach (['engagementImage', 'challengesImage', 'achievementsImage'] as $alt_key) {
                        if (!empty($clean[$alt_key]) && is_numeric($clean[$alt_key]) && (int) $clean[$alt_key] > 0) {
                            $clean['image'] = (int) $clean[$alt_key];
                            set_post_thumbnail($post_id, (int) $clean[$alt_key]);
                            break;
                        }
                    }
                }
            }

            update_post_meta($post_id, '_pisl_fields', $clean);
        }

        if (!empty($cfg['has_seo']) && isset($_POST['pisl_seo']) && is_array($_POST['pisl_seo'])) {
            $clean_seo = PISL_Field_Renderer::sanitize_fields(PISL_Schemas::get_seo_fields(), $_POST['pisl_seo']);
            update_post_meta($post_id, '_pisl_seo', $clean_seo);
        }

        PISL_Security_CORS::bump_cache_version();
    }

    public static function add_ongoing_row_action(array $actions, \WP_Post $post): array {
        $cpts = PISL_Schemas::get_cpt_schemas();
        if (isset($cpts[$post->post_type]) && current_user_can('edit_post', $post->ID) && $post->post_status !== 'trash') {
            $rest_type = PISL_Preview_Auth::get_rest_type_for_post_type($post->post_type);
            $route = PISL_Preview_Auth::get_frontend_route_for_post($post);
            $preview_url = PISL_Preview_Auth::build_preview_url($rest_type, (string) $post->ID, $route);
            $actions['pisl_preview_site'] = '<a href="' . esc_url($preview_url) . '" target="_blank" rel="noopener noreferrer">Preview on Site</a>';
        }
        if ($post->post_type === 'ongoing_project' && current_user_can('edit_post', $post->ID) && $post->post_status !== 'trash') {
            $url = wp_nonce_url(
                admin_url('admin.php?action=pisl_move_to_completed&post=' . $post->ID),
                'pisl_move_row_' . $post->ID
            );
            $actions['move_to_completed'] = '<a href="' . esc_url($url) . '" style="color:#059669;font-weight:600;" onclick="return confirm(\'Move this project to Completed Projects?\')">Move to Completed</a>';
        }
        return $actions;
    }

    public static function handle_row_move_action(): void {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        check_admin_referer('pisl_move_row_' . $post_id);
        if (!current_user_can('edit_post', $post_id)) {
            wp_die('Unauthorized');
        }
        $res = PISL_Workflows::move_ongoing_to_completed($post_id);
        if (is_wp_error($res)) {
            set_transient('pisl_admin_notice_' . get_current_user_id(), ['type' => 'error', 'text' => $res->get_error_message()], 30);
            wp_safe_redirect(admin_url('edit.php?post_type=ongoing_project'));
            exit;
        }
        set_transient('pisl_admin_notice_' . get_current_user_id(), ['type' => 'success', 'text' => 'Ongoing project moved to Completed Projects (ID ' . $res . ').'], 30);
        wp_safe_redirect(admin_url('post.php?post=' . $res . '&action=edit'));
        exit;
    }

    public static function render_admin_notices(): void {
        global $pagenow, $post;
        if (in_array($pagenow, ['post.php', 'post-new.php'], true) && $post instanceof \WP_Post && $post->post_type === 'client' && $post->post_status !== 'auto-draft') {
            $f = get_post_meta($post->ID, '_pisl_fields', true);
            $has_logo = (!empty($f['logo']) || !empty($f['logoUrl']) || has_post_thumbnail($post->ID));
            if (!$has_logo) {
                echo '<div class="notice notice-warning"><p><strong>Missing Client Logo:</strong> Upload or select a logo image under <em>Content &amp; Media Fields &rarr; Logo Image</em> (or Featured Image) so this client displays with its real company logo on the website.</p></div>';
            }
        }
        $key = 'pisl_admin_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if ($notice && is_array($notice)) {
            delete_transient($key);
            $cls = ($notice['type'] ?? '') === 'error' ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr($cls) . ' is-dismissible"><p>' . esc_html($notice['text'] ?? '') . '</p></div>';
        }
    }
}
