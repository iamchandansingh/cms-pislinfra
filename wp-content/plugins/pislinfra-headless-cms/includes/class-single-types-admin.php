<?php
/**
 * Admin UI for all 19 Single-Type Pages & Site Settings (Header, Footer, Home, About, EHS, etc.).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Single_Types_Admin {
    public static function init(): void {
        add_action('admin_post_pisl_save_single_type', [__CLASS__, 'handle_save']);
    }

    public static function render_hub_page(): void {
        $search = isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash($_GET['s']))) : '';
        $group  = isset($_GET['pgroup']) ? trim(sanitize_text_field(wp_unslash($_GET['pgroup']))) : '';

        $rows = [];
        foreach (PISL_Schemas::get_single_types() as $slug => $cfg) {
            if (($cfg['group'] ?? '') === 'Site Header & Footer') {
                continue; // Managed under Website Settings
            }
            $stored = get_option('pisl_single_' . $slug, []);
            $status = is_array($stored) ? ($stored['_status'] ?? 'publish') : 'publish';
            $route  = PISL_Preview_Auth::get_frontend_route_for_single_type($slug);
            $rows[] = [
                'title'   => $cfg['title'],
                'group'   => $cfg['group'],
                'route'   => $route,
                'source'  => 'Single Type (' . $slug . ')',
                'status'  => $status,
                'updated' => is_array($stored) ? ($stored['updatedAt'] ?? '') : '',
                'editUrl' => admin_url('admin.php?page=pisl-single-' . $slug),
                'prevUrl' => PISL_Preview_Auth::build_preview_url($slug, $slug, $route),
                'apiUrl'  => rest_url('pislinfra/v1/' . $slug),
            ];
        }

        $sol_posts = get_posts([
            'post_type'      => 'solution_page',
            'post_status'    => ['publish', 'draft', 'pending'],
            'posts_per_page' => -1,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        ]);
        foreach ($sol_posts as $sp) {
            $route = PISL_Preview_Auth::get_frontend_route_for_post($sp);
            $rows[] = [
                'title'   => $sp->post_title,
                'group'   => 'Solutions & Services',
                'route'   => $route,
                'source'  => 'Solution Page (#' . $sp->ID . ')',
                'status'  => $sp->post_status,
                'updated' => $sp->post_modified,
                'editUrl' => get_edit_post_link($sp->ID),
                'prevUrl' => PISL_Preview_Auth::build_preview_url('solution-pages', (string) $sp->ID, $route),
                'apiUrl'  => rest_url('pislinfra/v1/solution-pages/' . $sp->ID),
            ];
        }

        $groups = array_values(array_unique(array_column($rows, 'group')));
        $filtered = array_filter($rows, function ($r) use ($search, $group) {
            if ($group !== '' && $r['group'] !== $group) return false;
            if ($search !== '' && stripos($r['title'] . ' ' . $r['route'] . ' ' . $r['source'], $search) === false) return false;
            return true;
        });

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">All Website Pages</h1> ';
        echo '<a href="' . esc_url(admin_url('post-new.php?post_type=solution_page')) . '" class="page-title-action">Add New Page</a>';
        echo '<p class="description">Central directory of every PISL website page (Core Landing Pages, About &amp; Governance, Solutions, Projects Landing, and Legal Pages).</p>';
        echo '<hr class="wp-header-end">';

        echo '<form method="get" style="display:flex;gap:8px;align-items:center;margin:12px 0 16px;">';
        echo '<input type="hidden" name="page" value="pisl-pages-hub" />';
        echo '<select name="pgroup"><option value="">All Page Sections</option>';
        foreach ($groups as $g) {
            echo '<option value="' . esc_attr($g) . '"' . selected($group, $g, false) . '>' . esc_html($g) . '</option>';
        }
        echo '</select>';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search page title or URL route..." class="regular-text" />';
        submit_button('Filter', 'secondary', '', false);
        if ($search !== '' || $group !== '') {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=pisl-pages-hub')) . '">Reset</a>';
        }
        echo '</form>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width:22%;">Page Title</th><th style="width:16%;">Section</th><th style="width:18%;">Website Route</th><th style="width:18%;">Data Source</th><th style="width:10%;">Status</th><th style="width:16%;">Actions</th></tr></thead><tbody>';
        foreach ($filtered as $r) {
            $badge = $r['status'] === 'publish'
                ? '<span class="pisl-badge pisl-badge-pub">Published</span>'
                : '<span class="pisl-badge pisl-badge-draft">' . esc_html(ucfirst($r['status'])) . '</span>';
            echo '<tr>';
            echo '<td><strong><a href="' . esc_url($r['editUrl']) . '">' . esc_html($r['title']) . '</a></strong></td>';
            echo '<td>' . esc_html($r['group']) . '</td>';
            echo '<td><code>' . esc_html($r['route']) . '</code></td>';
            echo '<td><code>' . esc_html($r['source']) . '</code></td>';
            echo '<td>' . $badge . '</td>';
            echo '<td><a class="button button-primary button-small" href="' . esc_url($r['editUrl']) . '">Edit</a> ';
            echo '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url($r['prevUrl']) . '">Preview</a> ';
            echo '<a class="button button-small" target="_blank" rel="noopener noreferrer" href="' . esc_url($r['apiUrl']) . '">JSON</a></td>';
            echo '</tr>';
        }
        if (empty($filtered)) {
            echo '<tr><td colspan="6">No pages matched your filter.</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function render_single_type_page(string $slug, array $cfg): void {
        $option_key = 'pisl_single_' . $slug;
        $stored = get_option($option_key, []);
        if (!is_array($stored)) $stored = [];
        $has_draft = !empty($stored['_has_draft']) && is_array($stored['_draft_data'] ?? null);
        $data      = $has_draft ? array_merge($stored, $stored['_draft_data']) : $stored;
        $status    = $has_draft ? 'draft' : ($stored['_status'] ?? 'publish');
        $seo       = isset($data['seo']) && is_array($data['seo']) ? $data['seo'] : [];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html($cfg['title']) . ' <code style="font-size:13px;">/wp-json/pislinfra/v1/' . esc_html($slug) . '</code></h1>';
        if (isset($_GET['updated'])) {
            $mode_msg = $has_draft
                ? 'Draft saved safely. Live public website continues serving the last published version; use Preview Draft to inspect changes.'
                : 'Published live and REST API cache refreshed.';
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($mode_msg) . '</p></div>';
        } elseif ($has_draft) {
            echo '<div class="notice notice-warning"><p><strong>Unpublished Working Draft Active:</strong> You are editing a draft. Public visitors still see the published version until you click <strong>Save &amp; Publish Live</strong>.</p></div>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pisl_save_single_' . $slug, 'pisl_single_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_single_type" />';
        echo '<input type="hidden" name="pisl_slug" value="' . esc_attr($slug) . '" />';
        $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ('pisl-single-' . $slug);
        if ($current_page !== 'pisl-single-' . $slug) {
            $ret = add_query_arg(array_filter([
                'page' => $current_page,
                'tab'  => isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : null,
            ]), admin_url('admin.php'));
            echo '<input type="hidden" name="pisl_return_url" value="' . esc_attr($ret) . '" />';
        }

        echo '<div class="postbox" style="padding:16px;margin-top:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:center;">';
        echo '<label for="pisl_single_status"><strong>Publishing Status:</strong></label> ';
        echo '<select id="pisl_single_status" name="pisl_single_status">';
        echo '<option value="publish"' . selected($status, 'publish', false) . '>Published (Live on Website)</option>';
        echo '<option value="draft"' . selected($status, 'draft', false) . '>Draft (Preview Only / Keep Live Unchanged)</option>';
        echo '</select> ';
        echo '<button type="submit" name="pisl_submit_mode" value="publish" class="button button-primary">Save &amp; Publish Live</button>';
        echo '<button type="submit" name="pisl_submit_mode" value="draft" class="button button-secondary">Save Working Draft</button>';
        if ($has_draft) {
            echo '<button type="submit" name="pisl_submit_mode" value="discard_draft" class="button button-link-delete" onclick="return confirm(\'Discard unpublished draft changes and revert editor to the live published version?\')">Discard Draft</button>';
        }
        $preview_route = PISL_Preview_Auth::get_frontend_route_for_single_type($slug);
        $preview_url   = PISL_Preview_Auth::build_preview_url($slug, $slug, $preview_route);
        echo ' <a href="' . esc_url($preview_url) . '" target="_blank" rel="noopener noreferrer" class="button">Preview ' . ($has_draft ? 'Draft' : 'on Website') . ' &nearr;</a>';
        echo '</div>';

        echo '<div class="postbox" style="padding:16px;"><h2>Content &amp; Media</h2>';
        PISL_Field_Renderer::render_fields($cfg['fields'], $data, 'pisl_fields');
        echo '</div>';

        if (!empty($cfg['has_seo'])) {
            $base_url = rtrim((string) PISL_Admin_SEO::get_global_settings()['frontendBaseUrl'], '/');
            $route_url = $base_url . $preview_route;
            echo '<div class="postbox" style="padding:16px;"><h2>SEO &amp; Social Metadata</h2>';
            echo '<div id="pisl-single-serp-preview" data-default-title="' . esc_attr($cfg['title']) . '" data-default-url="' . esc_attr($route_url) . '" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;margin-bottom:14px;max-width:680px;">';
            echo '<div style="font-size:11px;color:#475569;margin-bottom:3px;font-weight:600;text-transform:uppercase;">Live Google Search Preview</div>';
            echo '<div class="pisl-serp-url" style="font-size:12px;color:#15803d;word-break:break-all;">' . esc_html($seo['canonicalUrl'] ?? $route_url) . '</div>';
            echo '<div class="pisl-serp-title" style="font-size:18px;color:#1a0dab;font-weight:600;margin:2px 0;">' . esc_html($seo['seoTitle'] ?? $data['metaTitle'] ?? $cfg['title']) . '</div>';
            echo '<div class="pisl-serp-desc" style="font-size:13px;color:#475569;line-height:1.45;">' . esc_html($seo['seoDescription'] ?? $data['metaDescription'] ?? '') . '</div>';
            echo '<div class="pisl-serp-counts" style="font-size:11px;color:#64748b;margin-top:6px;"></div>';
            echo '</div>';
            PISL_Field_Renderer::render_fields(PISL_Schemas::get_seo_fields(), $seo, 'pisl_seo');
            echo '</div>';
        }

        echo '<button type="submit" name="pisl_submit_mode" value="publish" class="button button-primary">Save &amp; Publish Live</button> ';
        echo '<button type="submit" name="pisl_submit_mode" value="draft" class="button button-secondary">Save Working Draft</button>';
        echo '</form></div>';
    }

    public static function handle_save(): void {
        $slug = isset($_POST['pisl_slug']) ? sanitize_key(wp_unslash($_POST['pisl_slug'])) : '';
        check_admin_referer('pisl_save_single_' . $slug, 'pisl_single_nonce');
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        $types = PISL_Schemas::get_single_types();
        if (!isset($types[$slug])) {
            wp_die('Unknown page type');
        }

        $cfg  = $types[$slug];
        $prev = get_option('pisl_single_' . $slug, []);
        if (!is_array($prev)) $prev = [];

        $submit_mode = isset($_POST['pisl_submit_mode']) ? sanitize_key(wp_unslash($_POST['pisl_submit_mode'])) : '';
        if ($submit_mode === 'discard_draft') {
            unset($prev['_draft_data'], $prev['_has_draft']);
            update_option('pisl_single_' . $slug, $prev, false);
            $default_redirect = admin_url('admin.php?page=pisl-single-' . $slug . '&updated=1');
            $redirect_to = !empty($_POST['pisl_return_url'])
                ? add_query_arg('updated', '1', esc_url_raw(wp_unslash($_POST['pisl_return_url'])))
                : $default_redirect;
            wp_safe_redirect($redirect_to, 302);
            exit;
        }

        $raw   = isset($_POST['pisl_fields']) && is_array($_POST['pisl_fields']) ? $_POST['pisl_fields'] : [];
        $clean = PISL_Field_Renderer::sanitize_fields($cfg['fields'], $raw);

        if ($slug === 'governance-page' && isset($clean['slug'])) {
            $clean['slug'] = PISL_Workflows::validate_governance_slug((string) $clean['slug']);
        }

        if ($slug === 'header') {
            $top_updates = array_intersect_key($clean, array_flip(['address', 'phone', 'email']));
            if (!empty($top_updates)) {
                $th = get_option('pisl_single_top-header', []);
                if (!is_array($th)) $th = [];
                foreach ($top_updates as $tk => $tv) {
                    $th[$tk] = $tv;
                }
                $th['updatedAt'] = gmdate('c');
                update_option('pisl_single_top-header', $th, false);
            }
        } elseif ($slug === 'top-header') {
            $hdr_updates = array_intersect_key($clean, array_flip(['address', 'phone', 'email']));
            if (!empty($hdr_updates)) {
                $h = get_option('pisl_single_header', []);
                if (!is_array($h)) $h = [];
                foreach ($hdr_updates as $hk => $hv) {
                    $h[$hk] = $hv;
                }
                $h['updatedAt'] = gmdate('c');
                update_option('pisl_single_header', $h, false);
            }
        }

        if (!empty($cfg['has_seo'])) {
            $raw_seo = isset($_POST['pisl_seo']) && is_array($_POST['pisl_seo']) ? $_POST['pisl_seo'] : [];
            $clean['seo'] = PISL_Field_Renderer::sanitize_fields(PISL_Schemas::get_seo_fields(), $raw_seo);
        }

        $req_status = $submit_mode === 'draft'
            ? 'draft'
            : ($submit_mode === 'publish'
                ? 'publish'
                : ((isset($_POST['pisl_single_status']) && $_POST['pisl_single_status'] === 'draft') ? 'draft' : 'publish'));

        $was_published = ($prev['_status'] ?? 'publish') === 'publish' && count(array_diff(array_keys($prev), ['_status', '_draft_data', '_has_draft', '_id', '_documentId', '_createdAt', '_updatedAt', '_publishedAt', 'createdAt', 'updatedAt'])) > 0;

        if ($req_status === 'draft' && $was_published) {
            // Preserve live published payload while storing draft changes separately for preview
            $clean['updatedAt'] = gmdate('c');
            $prev['_has_draft']  = true;
            $prev['_draft_data'] = $clean;
            update_option('pisl_single_' . $slug, $prev, false);
        } else {
            $clean['_status']    = $req_status;
            $clean['_has_draft'] = false;
            $clean['updatedAt']  = gmdate('c');
            $clean['createdAt']  = $prev['createdAt'] ?? ($prev['_createdAt'] ?? gmdate('c'));
            $clean['_id']        = $prev['_id'] ?? 1;
            if (!empty($prev['_documentId'])) $clean['_documentId'] = $prev['_documentId'];
            if (!empty($prev['_createdAt']))  $clean['_createdAt']  = $prev['_createdAt'];
            $clean['_updatedAt'] = gmdate('c');
            if ($req_status === 'publish') {
                $clean['_publishedAt'] = gmdate('c');
            }
            update_option('pisl_single_' . $slug, $clean, false);
            PISL_Security_CORS::bump_cache_version();
        }

        $default_redirect = admin_url('admin.php?page=pisl-single-' . $slug . '&updated=1');
        $redirect_to = !empty($_POST['pisl_return_url'])
            ? add_query_arg('updated', '1', esc_url_raw(wp_unslash($_POST['pisl_return_url'])))
            : $default_redirect;
        wp_safe_redirect($redirect_to, 302);
        exit;
    }
}
