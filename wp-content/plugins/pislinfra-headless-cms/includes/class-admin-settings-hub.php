<?php
/**
 * Dedicated Settings & SEO Management Hub for the PISL Headless CMS:
 * - SEO Management (Landing Pages, Content & Projects, Global SEO & Sitemaps)
 * - Global Contact Details (synced across Top Header, Footer, Contact Us)
 * - Social Media Links (synced across Top Header, Footer, Contact Us)
 * - Website Logo & Favicon (Header Logo, Footer Logo, WP Site Icon, Site Title)
 * - API & Integration Settings (Cache Flush, CORS/Frontend/REST diagnostics, Cache TTL)
 * - Client Settings, Coverage Map Settings, Governance Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Admin_Settings_Hub {
    public static function init(): void {
        add_action('admin_post_pisl_save_hub_settings', [__CLASS__, 'handle_save_hub_settings']);
        add_action('admin_post_pisl_save_seo_item', [__CLASS__, 'handle_save_seo_item']);
        add_action('admin_post_pisl_flush_cache', [__CLASS__, 'handle_flush_cache']);
    }

    public static function render_notice(): void {
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved and REST API cache refreshed.</p></div>';
        }
        if (!empty($_GET['flushed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>WordPress REST API cache flushed (version bumped).</p></div>';
        }
    }

    private static function update_single_option_fields(string $slug, array $updates): void {
        $prev = get_option('pisl_single_' . $slug, []);
        if (!is_array($prev)) {
            $prev = [];
        }
        foreach ($updates as $k => $v) {
            $prev[$k] = $v;
        }
        $prev['updatedAt'] = gmdate('c');
        update_option('pisl_single_' . $slug, $prev, false);
    }

    public static function handle_flush_cache(): void {
        check_admin_referer('pisl_flush_cache_nonce');
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }
        PISL_Security_CORS::bump_cache_version();
        wp_safe_redirect(admin_url('admin.php?page=pisl-settings-integrations&flushed=1'));
        exit;
    }

    public static function handle_save_seo_item(): void {
        check_admin_referer('pisl_save_seo_item_nonce');
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $kind   = isset($_POST['pisl_seo_kind']) ? sanitize_key(wp_unslash($_POST['pisl_seo_kind'])) : '';
        $target = isset($_POST['pisl_seo_target']) ? sanitize_text_field(wp_unslash($_POST['pisl_seo_target'])) : '';
        $return = isset($_POST['pisl_return_url']) ? esc_url_raw(wp_unslash($_POST['pisl_return_url'])) : admin_url('admin.php?page=pisl-seo-management');

        $title = sanitize_text_field(wp_unslash($_POST['metaTitle'] ?? ''));
        $desc  = sanitize_textarea_field(wp_unslash($_POST['metaDescription'] ?? ''));
        $kw    = sanitize_text_field(wp_unslash($_POST['primaryKeyword'] ?? ''));

        if ($kind === 'single' && isset(PISL_Schemas::get_single_types()[$target])) {
            $data = get_option('pisl_single_' . $target, []);
            if (!is_array($data)) $data = [];
            $seo = isset($data['seo']) && is_array($data['seo']) ? $data['seo'] : [];
            $seo['metaTitle']       = $title;
            $seo['metaDescription'] = $desc;
            if ($kw !== '') $seo['keywords'] = $kw;
            $data['seo']            = $seo;
            $data['metaTitle']      = $title;
            $data['metaDescription']= $desc;
            $data['primaryKeyword'] = $kw;
            $data['updatedAt']      = gmdate('c');
            update_option('pisl_single_' . $target, $data, false);
        } elseif ($kind === 'post' && ctype_digit($target) && current_user_can('edit_post', (int) $target)) {
            $pid = (int) $target;
            $seo = get_post_meta($pid, '_pisl_seo', true);
            if (!is_array($seo)) $seo = [];
            $seo['metaTitle']       = $title;
            $seo['metaDescription'] = $desc;
            if ($kw !== '') $seo['keywords'] = $kw;
            update_post_meta($pid, '_pisl_seo', $seo);

            $fields = get_post_meta($pid, '_pisl_fields', true);
            if (!is_array($fields)) $fields = [];
            if (array_key_exists('metaTitle', $fields))       $fields['metaTitle']       = $title;
            if (array_key_exists('metaDescription', $fields)) $fields['metaDescription'] = $desc;
            if (array_key_exists('primaryKeyword', $fields))  $fields['primaryKeyword']  = $kw;
            if (array_key_exists('focusKeyword', $fields))    $fields['focusKeyword']    = $kw;
            update_post_meta($pid, '_pisl_fields', $fields);
        }

        PISL_Security_CORS::bump_cache_version();
        wp_safe_redirect(add_query_arg('updated', '1', $return));
        exit;
    }

    public static function handle_save_hub_settings(): void {
        $section = isset($_POST['pisl_section']) ? sanitize_key(wp_unslash($_POST['pisl_section'])) : '';
        check_admin_referer('pisl_hub_' . $section, 'pisl_hub_nonce');
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $return_page = 'pisl-settings-' . $section;

        switch ($section) {
            case 'contact':
                $phone   = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
                $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
                $address = sanitize_textarea_field(wp_unslash($_POST['address'] ?? ''));
                $mapUrl  = esc_url_raw(wp_unslash($_POST['mapUrl'] ?? ''));
                $hours   = sanitize_text_field(wp_unslash($_POST['businessHours'] ?? ''));

                self::update_single_option_fields('top-header', compact('phone', 'email', 'address'));
                self::update_single_option_fields('header', compact('phone', 'email', 'address'));
                self::update_single_option_fields('footer', [
                    'phone'         => $phone,
                    'email'         => $email,
                    'address'       => $address,
                    'mapUrl'        => $mapUrl,
                    'businessHours' => $hours,
                ]);
                self::update_single_option_fields('contact-page', compact('phone', 'email', 'address', 'mapUrl'));
                break;

            case 'social':
                $linkedinUrl  = esc_url_raw(wp_unslash($_POST['linkedinUrl'] ?? ''));
                $facebookUrl  = esc_url_raw(wp_unslash($_POST['facebookUrl'] ?? ''));
                $twitterUrl   = esc_url_raw(wp_unslash($_POST['twitterUrl'] ?? ''));
                $instagramUrl = esc_url_raw(wp_unslash($_POST['instagramUrl'] ?? ''));

                self::update_single_option_fields('top-header', compact('linkedinUrl', 'facebookUrl', 'twitterUrl', 'instagramUrl'));
                self::update_single_option_fields('footer', ['linkedinUrl' => $linkedinUrl]);
                self::update_single_option_fields('contact-page', ['linkedinUrl' => $linkedinUrl]);
                break;

            case 'branding':
                $rawFields = isset($_POST['pisl_fields']) && is_array($_POST['pisl_fields']) ? $_POST['pisl_fields'] : [];
                $headerLogo = !empty($rawFields['headerLogo']) ? (int) $rawFields['headerLogo'] : null;
                $footerLogo = !empty($rawFields['footerLogo']) ? (int) $rawFields['footerLogo'] : null;
                $siteIcon   = !empty($rawFields['siteIcon']) ? (int) $rawFields['siteIcon'] : 0;

                if (isset($_POST['blogname'])) {
                    update_option('blogname', sanitize_text_field(wp_unslash($_POST['blogname'])));
                }
                if (isset($_POST['blogdescription'])) {
                    update_option('blogdescription', sanitize_text_field(wp_unslash($_POST['blogdescription'])));
                }
                update_option('site_icon', $siteIcon);
                if ($headerLogo !== null) {
                    self::update_single_option_fields('header', ['logo' => $headerLogo]);
                }
                if ($footerLogo !== null) {
                    self::update_single_option_fields('footer', ['logo' => $footerLogo]);
                }
                break;

            case 'integrations':
                $ttl = max(0, min(86400, (int) ($_POST['apiCacheTtlSeconds'] ?? 120)));
                $webhook = esc_url_raw(wp_unslash($_POST['frontendWebhookUrl'] ?? ''));
                update_option('pisl_integration_settings', [
                    'apiCacheTtlSeconds' => $ttl,
                    'frontendWebhookUrl' => $webhook,
                    'updatedAt'          => gmdate('c'),
                ], false);
                break;

            case 'client':
                $return_page = 'pisl-client-settings';
                $clientsTitle = sanitize_text_field(wp_unslash($_POST['clientsTitle'] ?? ''));
                $apprTitle    = sanitize_text_field(wp_unslash($_POST['clientAppreciationsTitle'] ?? ''));
                self::update_single_option_fields('home-page', ['clientsTitle' => $clientsTitle]);
                self::update_single_option_fields('awards-page', ['clientAppreciationsTitle' => $apprTitle]);
                update_option('pisl_client_settings', [
                    'subtitle'                  => sanitize_text_field(wp_unslash($_POST['subtitle'] ?? '')),
                    'showLogosOnHome'           => !empty($_POST['showLogosOnHome']),
                    'showAppreciationsOnAwards' => !empty($_POST['showAppreciationsOnAwards']),
                    'updatedAt'                 => gmdate('c'),
                ], false);
                break;

            case 'coverage':
                $return_page = 'pisl-coverage-settings';
                self::update_single_option_fields('home-page', [
                    'coverageTitle'    => sanitize_text_field(wp_unslash($_POST['coverageTitle'] ?? '')),
                    'statLocations'    => sanitize_text_field(wp_unslash($_POST['statLocations'] ?? '')),
                    'statMicroMarkets' => sanitize_text_field(wp_unslash($_POST['statMicroMarkets'] ?? '')),
                    'statSpaceSqFt'    => sanitize_text_field(wp_unslash($_POST['statSpaceSqFt'] ?? '')),
                    'statPipelineSqFt' => sanitize_text_field(wp_unslash($_POST['statPipelineSqFt'] ?? '')),
                ]);
                update_option('pisl_coverage_settings', [
                    'mapSubheading' => sanitize_textarea_field(wp_unslash($_POST['mapSubheading'] ?? '')),
                    'updatedAt'     => gmdate('c'),
                ], false);
                break;

            case 'seo_global':
                $return_page = 'pisl-seo-management';
                $rawFields = isset($_POST['pisl_fields']) && is_array($_POST['pisl_fields']) ? $_POST['pisl_fields'] : [];
                update_option('pisl_seo_global_settings', [
                    'titleSuffix'         => sanitize_text_field(wp_unslash($_POST['titleSuffix'] ?? '| PISL Infrastructure')),
                    'defaultMetaDesc'     => sanitize_textarea_field(wp_unslash($_POST['defaultMetaDesc'] ?? '')),
                    'defaultOgImage'      => !empty($rawFields['defaultOgImage']) ? (int) $rawFields['defaultOgImage'] : null,
                    'updatedAt'           => gmdate('c'),
                ], false);
                break;
        }

        PISL_Security_CORS::bump_cache_version();
        $extra = $section === 'seo_global' ? '&tab=global' : '';
        wp_safe_redirect(admin_url('admin.php?page=' . $return_page . $extra . '&updated=1'));
        exit;
    }

    public static function render_seo_management_page(): void {
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'pages';
        if (!in_array($tab, ['pages', 'content', 'global'], true)) {
            $tab = 'pages';
        }

        echo '<div class="wrap">';
        echo '<h1>SEO Management</h1>';
        echo '<p class="description">Centralized SEO metadata editor for Landing Pages, Blog Posts, Projects, Case Studies, Solutions, and Sitemaps.</p>';
        self::render_notice();

        echo '<h2 class="nav-tab-wrapper" style="margin:14px 0 18px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pisl-seo-management&tab=pages')) . '" class="nav-tab ' . ($tab === 'pages' ? 'nav-tab-active' : '') . '">Landing Pages SEO</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pisl-seo-management&tab=content')) . '" class="nav-tab ' . ($tab === 'content' ? 'nav-tab-active' : '') . '">Blog, Projects &amp; Solutions SEO</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pisl-seo-management&tab=global')) . '" class="nav-tab ' . ($tab === 'global' ? 'nav-tab-active' : '') . '">Global SEO &amp; Sitemaps</a>';
        echo '</h2>';

        if ($tab === 'pages') {
            self::render_seo_pages_tab();
        } elseif ($tab === 'content') {
            self::render_seo_content_tab();
        } else {
            self::render_seo_global_tab();
        }

        echo '</div>';
    }

    private static function render_seo_pages_tab(): void {
        $types = PISL_Schemas::get_single_types();
        $return_url = admin_url('admin.php?page=pisl-seo-management&tab=pages');
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width:18%;">Page</th><th style="width:14%;">Status</th><th>Meta Title, Meta Description &amp; Primary Keyword</th><th style="width:16%;">Actions</th></tr></thead><tbody>';

        foreach ($types as $slug => $cfg) {
            if (empty($cfg['has_seo'])) continue;
            $data = get_option('pisl_single_' . $slug, []);
            if (!is_array($data)) $data = [];
            $seo   = is_array($data['seo'] ?? null) ? $data['seo'] : [];
            $title = (string) ($seo['metaTitle'] ?? $data['metaTitle'] ?? '');
            $desc  = (string) ($seo['metaDescription'] ?? $data['metaDescription'] ?? '');
            $kw    = (string) ($seo['keywords'] ?? $data['primaryKeyword'] ?? '');
            $ready = trim($title) !== '' && trim($desc) !== '';

            echo '<tr>';
            echo '<td><strong>' . esc_html($cfg['title']) . '</strong><br><code>/' . esc_html($slug) . '</code></td>';
            echo '<td>' . ($ready ? '<span class="pisl-badge pisl-badge-pub">SEO Ready</span>' : '<span class="pisl-badge pisl-badge-warn">Needs Review</span>') . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="pisl-seo-inline-form">';
            wp_nonce_field('pisl_save_seo_item_nonce');
            echo '<input type="hidden" name="action" value="pisl_save_seo_item" />';
            echo '<input type="hidden" name="pisl_seo_kind" value="single" />';
            echo '<input type="hidden" name="pisl_seo_target" value="' . esc_attr($slug) . '" />';
            echo '<input type="hidden" name="pisl_return_url" value="' . esc_attr($return_url) . '" />';
            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:6px;">';
            echo '<input type="text" name="metaTitle" value="' . esc_attr($title) . '" placeholder="Meta Title" class="regular-text" style="width:100%;" />';
            echo '<input type="text" name="primaryKeyword" value="' . esc_attr($kw) . '" placeholder="Primary Keyword" class="regular-text" style="width:100%;" />';
            echo '</div>';
            echo '<div style="display:flex;gap:8px;align-items:flex-start;">';
            echo '<textarea name="metaDescription" rows="2" placeholder="Meta Description" style="flex:1;">' . esc_textarea($desc) . '</textarea>';
            echo '<button type="submit" class="button button-primary">Save SEO</button>';
            echo '</div>';
            echo '</form>';
            echo '</td>';
            echo '<td><a class="button" href="' . esc_url(admin_url('admin.php?page=pisl-single-' . $slug)) . '">Full Page Editor</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_seo_content_tab(): void {
        $seo_types = [
            'post'              => 'Blog Posts',
            'ongoing_project'   => 'Ongoing Projects',
            'completed_project' => 'Completed Projects',
            'case_study'        => 'Case Studies',
            'solution_page'     => 'Solution Pages',
        ];
        $pt_filter = isset($_GET['cpt']) && isset($seo_types[$_GET['cpt']]) ? sanitize_key(wp_unslash($_GET['cpt'])) : 'post';
        $search    = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged     = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page  = 15;

        $q = new \WP_Query([
            'post_type'      => $pt_filter,
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            's'              => $search,
        ]);

        $return_url = add_query_arg(['page' => 'pisl-seo-management', 'tab' => 'content', 'cpt' => $pt_filter, 'paged' => $paged, 's' => $search], admin_url('admin.php'));

        echo '<form method="get" style="display:flex;gap:8px;align-items:center;margin-bottom:14px;">';
        echo '<input type="hidden" name="page" value="pisl-seo-management" />';
        echo '<input type="hidden" name="tab" value="content" />';
        echo '<select name="cpt">';
        foreach ($seo_types as $k => $lbl) {
            echo '<option value="' . esc_attr($k) . '"' . selected($pt_filter, $k, false) . '>' . esc_html($lbl) . '</option>';
        }
        echo '</select>';
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search title..." />';
        submit_button('Filter', 'secondary', '', false);
        echo '</form>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th style="width:22%;">Record</th><th style="width:12%;">SEO Status</th><th>Meta Title, Description &amp; Keyword</th><th style="width:14%;">Actions</th></tr></thead><tbody>';

        if (empty($q->posts)) {
            echo '<tr><td colspan="4">No matching entries found.</td></tr>';
        }
        foreach ($q->posts as $p) {
            $seo    = get_post_meta($p->ID, '_pisl_seo', true);
            $fields = get_post_meta($p->ID, '_pisl_fields', true);
            if (!is_array($seo)) $seo = [];
            if (!is_array($fields)) $fields = [];
            $title = (string) ($seo['metaTitle'] ?? $fields['metaTitle'] ?? '');
            $desc  = (string) ($seo['metaDescription'] ?? $fields['metaDescription'] ?? '');
            $kw    = (string) ($seo['keywords'] ?? $fields['primaryKeyword'] ?? $fields['focusKeyword'] ?? '');

            echo '<tr>';
            echo '<td><strong>' . esc_html($p->post_title) . '</strong><br><small>ID #' . intval($p->ID) . ' · ' . esc_html(ucfirst($p->post_status)) . '</small></td>';
            echo '<td>' . PISL_Admin_Columns::render_seo_badge($p->ID, $fields) . '</td>';
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pisl_save_seo_item_nonce');
            echo '<input type="hidden" name="action" value="pisl_save_seo_item" />';
            echo '<input type="hidden" name="pisl_seo_kind" value="post" />';
            echo '<input type="hidden" name="pisl_seo_target" value="' . intval($p->ID) . '" />';
            echo '<input type="hidden" name="pisl_return_url" value="' . esc_attr($return_url) . '" />';
            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:6px;">';
            echo '<input type="text" name="metaTitle" value="' . esc_attr($title) . '" placeholder="Meta Title" style="width:100%;" />';
            echo '<input type="text" name="primaryKeyword" value="' . esc_attr($kw) . '" placeholder="Primary Keyword" style="width:100%;" />';
            echo '</div>';
            echo '<div style="display:flex;gap:8px;align-items:flex-start;">';
            echo '<textarea name="metaDescription" rows="2" placeholder="Meta Description" style="flex:1;">' . esc_textarea($desc) . '</textarea>';
            echo '<button type="submit" class="button button-primary">Save SEO</button>';
            echo '</div></form>';
            echo '</td>';
            echo '<td><a class="button" href="' . esc_url(get_edit_post_link($p->ID)) . '">Edit Record</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $total_pages = (int) $q->max_num_pages;
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo paginate_links([
                'base'      => add_query_arg('paged', '%#%'),
                'format'    => '',
                'current'   => $paged,
                'total'     => $total_pages,
            ]);
            echo '</div></div>';
        }
    }

    private static function render_seo_global_tab(): void {
        $opt = get_option('pisl_seo_global_settings', []);
        if (!is_array($opt)) $opt = [];
        $frontend = defined('PISL_FRONTEND_URL') ? rtrim(PISL_FRONTEND_URL, '/') : 'https://pislinfra.com';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="postbox" style="padding:18px;max-width:920px;">';
        wp_nonce_field('pisl_hub_seo_global', 'pisl_hub_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_hub_settings" />';
        echo '<input type="hidden" name="pisl_section" value="seo_global" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="titleSuffix">Default Title Suffix</label></th><td><input id="titleSuffix" name="titleSuffix" type="text" class="regular-text" value="' . esc_attr((string) ($opt['titleSuffix'] ?? '| PISL Infrastructure')) . '" /></td></tr>';
        echo '<tr><th><label for="defaultMetaDesc">Fallback Meta Description</label></th><td><textarea id="defaultMetaDesc" name="defaultMetaDesc" rows="3" class="large-text">' . esc_textarea((string) ($opt['defaultMetaDesc'] ?? '')) . '</textarea></td></tr>';
        echo '<tr><th>Default Social / OG Image</th><td>';
        PISL_Field_Renderer::render_fields([
            ['key' => 'defaultOgImage', 'label' => 'Default OG Share Image', 'type' => 'media'],
        ], ['defaultOgImage' => $opt['defaultOgImage'] ?? null], 'pisl_fields');
        echo '</td></tr>';
        echo '</table>';
        submit_button('Save Global SEO Defaults');
        echo '</form>';

        echo '<div class="postbox" style="padding:18px;max-width:920px;"><h2>Active XML Sitemaps &amp; Feeds</h2><ul>';
        foreach (['sitemap.xml', 'sitemap-blogs.xml', 'sitemap-projects.xml', 'sitemap-case-studies.xml', 'sitemap-careers.xml', 'sitemap-clients.xml', 'sitemap-documents.xml', 'rss.xml'] as $sm) {
            $url = $frontend . '/' . $sm;
            echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a></li>';
        }
        echo '</ul></div>';
    }

    public static function render_contact_settings_page(): void {
        $top     = get_option('pisl_single_top-header', []);
        $footer  = get_option('pisl_single_footer', []);
        $contact = get_option('pisl_single_contact-page', []);

        $phone   = (string) ($top['phone'] ?? $footer['phone'] ?? $contact['phone'] ?? '');
        $email   = (string) ($top['email'] ?? $footer['email'] ?? $contact['email'] ?? '');
        $address = (string) ($top['address'] ?? $footer['address'] ?? $contact['address'] ?? '');
        $mapUrl  = (string) ($footer['mapUrl'] ?? $contact['mapUrl'] ?? '');
        $hours   = (string) ($footer['businessHours'] ?? '');

        echo '<div class="wrap"><h1>Global Contact Details</h1>';
        echo '<p class="description">Updates global contact information consistently across Top Header, Footer, and Contact Us REST endpoints.</p>';
        self::render_notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="postbox" style="padding:18px;max-width:860px;">';
        wp_nonce_field('pisl_hub_contact', 'pisl_hub_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_hub_settings" />';
        echo '<input type="hidden" name="pisl_section" value="contact" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="phone">Primary Phone</label></th><td><input id="phone" name="phone" type="text" class="regular-text" value="' . esc_attr($phone) . '" /></td></tr>';
        echo '<tr><th><label for="email">Primary Email</label></th><td><input id="email" name="email" type="email" class="regular-text" value="' . esc_attr($email) . '" /></td></tr>';
        echo '<tr><th><label for="address">Corporate Address</label></th><td><textarea id="address" name="address" rows="3" class="large-text">' . esc_textarea($address) . '</textarea></td></tr>';
        echo '<tr><th><label for="mapUrl">Google Maps Embed / Link URL</label></th><td><input id="mapUrl" name="mapUrl" type="url" class="large-text" value="' . esc_attr($mapUrl) . '" /></td></tr>';
        echo '<tr><th><label for="businessHours">Business Hours</label></th><td><input id="businessHours" name="businessHours" type="text" class="regular-text" value="' . esc_attr($hours) . '" /></td></tr>';
        echo '</table>';
        submit_button('Save Global Contact Details');
        echo '</form></div>';
    }

    public static function render_social_settings_page(): void {
        $top    = get_option('pisl_single_top-header', []);
        $footer = get_option('pisl_single_footer', []);

        $linkedin  = (string) ($top['linkedinUrl'] ?? $footer['linkedinUrl'] ?? '');
        $facebook  = (string) ($top['facebookUrl'] ?? '');
        $twitter   = (string) ($top['twitterUrl'] ?? '');
        $instagram = (string) ($top['instagramUrl'] ?? '');

        echo '<div class="wrap"><h1>Social Media Links</h1>';
        echo '<p class="description">Manages official company social profiles used across Header, Footer, and Contact Us endpoints.</p>';
        self::render_notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="postbox" style="padding:18px;max-width:860px;">';
        wp_nonce_field('pisl_hub_social', 'pisl_hub_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_hub_settings" />';
        echo '<input type="hidden" name="pisl_section" value="social" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="linkedinUrl">LinkedIn URL</label></th><td><input id="linkedinUrl" name="linkedinUrl" type="url" class="large-text" value="' . esc_attr($linkedin) . '" /></td></tr>';
        echo '<tr><th><label for="facebookUrl">Facebook URL</label></th><td><input id="facebookUrl" name="facebookUrl" type="url" class="large-text" value="' . esc_attr($facebook) . '" /></td></tr>';
        echo '<tr><th><label for="twitterUrl">X / Twitter URL</label></th><td><input id="twitterUrl" name="twitterUrl" type="url" class="large-text" value="' . esc_attr($twitter) . '" /></td></tr>';
        echo '<tr><th><label for="instagramUrl">Instagram URL</label></th><td><input id="instagramUrl" name="instagramUrl" type="url" class="large-text" value="' . esc_attr($instagram) . '" /></td></tr>';
        echo '</table>';
        submit_button('Save Social Media Links');
        echo '</form></div>';
    }

    public static function render_branding_settings_page(): void {
        $header = get_option('pisl_single_header', []);
        $footer = get_option('pisl_single_footer', []);
        $vals = [
            'headerLogo' => $header['logo'] ?? null,
            'footerLogo' => $footer['logo'] ?? null,
            'siteIcon'   => (int) get_option('site_icon', 0),
        ];

        echo '<div class="wrap"><h1>Website Logo &amp; Favicon</h1>';
        echo '<p class="description">Manages Header Logo, Footer Logo, Site Identity, and WordPress Favicon.</p>';
        self::render_notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="postbox" style="padding:18px;max-width:920px;">';
        wp_nonce_field('pisl_hub_branding', 'pisl_hub_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_hub_settings" />';
        echo '<input type="hidden" name="pisl_section" value="branding" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="blogname">Website Title</label></th><td><input id="blogname" name="blogname" type="text" class="regular-text" value="' . esc_attr(get_option('blogname', '')) . '" /></td></tr>';
        echo '<tr><th><label for="blogdescription">Tagline</label></th><td><input id="blogdescription" name="blogdescription" type="text" class="large-text" value="' . esc_attr(get_option('blogdescription', '')) . '" /></td></tr>';
        echo '</table>';
        PISL_Field_Renderer::render_fields([
            ['key' => 'headerLogo', 'label' => 'Header Brand Logo', 'type' => 'media'],
            ['key' => 'footerLogo', 'label' => 'Footer Brand Logo', 'type' => 'media'],
            ['key' => 'siteIcon',   'label' => 'Website Favicon / Site Icon (512x512 PNG recommended)', 'type' => 'media'],
        ], $vals, 'pisl_fields');
        submit_button('Save Logo & Branding');
        echo '</form></div>';
    }

    public static function render_integrations_settings_page(): void {
        $opt         = get_option('pisl_integration_settings', []);
        if (!is_array($opt)) $opt = [];
        $ttl         = (int) ($opt['apiCacheTtlSeconds'] ?? 120);
        $webhook     = (string) ($opt['frontendWebhookUrl'] ?? '');
        $frontend    = defined('PISL_FRONTEND_URL') ? PISL_FRONTEND_URL : '';
        $cors        = defined('PISL_CORS_ORIGINS') ? PISL_CORS_ORIGINS : '';
        $cache_ver   = PISL_Security_CORS::get_cache_version();
        $health_url  = rest_url('pislinfra/v1/health');

        echo '<div class="wrap"><h1>API &amp; Integration Settings</h1>';
        self::render_notice();

        echo '<div class="postbox" style="padding:18px;max-width:920px;margin-bottom:18px;">';
        echo '<h2 style="margin-top:0;">Headless WordPress REST Status</h2>';
        echo '<table class="widefat striped" style="max-width:860px;"><tbody>';
        echo '<tr><td style="width:260px;"><strong>REST Namespace</strong></td><td><code>/wp-json/pislinfra/v1</code> (<a target="_blank" rel="noopener" href="' . esc_url($health_url) . '">Inspect Health JSON</a>)</td></tr>';
        echo '<tr><td><strong>Active Frontend URL</strong></td><td><code>' . esc_html($frontend) . '</code></td></tr>';
        echo '<tr><td><strong>Allowed CORS Origins</strong></td><td><code>' . esc_html($cors) . '</code></td></tr>';
        echo '<tr><td><strong>REST Cache Version</strong></td><td><code>v' . intval($cache_ver) . '</code></td></tr>';
        echo '<tr><td><strong>Signed Preview Token TTL</strong></td><td><code>' . intval(PISL_Preview_Auth::PREVIEW_TTL_SECONDS) . ' seconds (HMAC-SHA256)</code></td></tr>';
        echo '</tbody></table>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:14px;">';
        wp_nonce_field('pisl_flush_cache_nonce');
        echo '<input type="hidden" name="action" value="pisl_flush_cache" />';
        submit_button('Flush REST API Cache Now', 'secondary', 'submit', false);
        echo '</form></div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="postbox" style="padding:18px;max-width:920px;">';
        wp_nonce_field('pisl_hub_integrations', 'pisl_hub_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_hub_settings" />';
        echo '<input type="hidden" name="pisl_section" value="integrations" />';
        echo '<h2 style="margin-top:0;">Integration Preferences</h2>';
        echo '<table class="form-table">';
        echo '<tr><th><label for="apiCacheTtlSeconds">REST Transient Cache TTL (seconds)</label></th><td><input id="apiCacheTtlSeconds" name="apiCacheTtlSeconds" type="number" min="0" max="86400" value="' . intval($ttl) . '" /></td></tr>';
        echo '<tr><th><label for="frontendWebhookUrl">Optional Build / Revalidate Webhook URL</label></th><td><input id="frontendWebhookUrl" name="frontendWebhookUrl" type="url" class="large-text" placeholder="https://pislinfra.com/..." value="' . esc_attr($webhook) . '" /></td></tr>';
        echo '</table>';
        submit_button('Save Integration Settings');
        echo '</form></div>';
    }

    public static function render_client_settings_page(): void {
        $home   = get_option('pisl_single_home-page', []);
        $awards = get_option('pisl_single_awards-page', []);
        $opt    = get_option('pisl_client_settings', []);
        if (!is_array($opt)) $opt = [];

        echo '<div class="wrap"><h1>Client Settings</h1>';
        self::render_notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="postbox" style="padding:18px;max-width:860px;">';
        wp_nonce_field('pisl_hub_client', 'pisl_hub_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_hub_settings" />';
        echo '<input type="hidden" name="pisl_section" value="client" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="clientsTitle">Home Clients Section Title</label></th><td><input id="clientsTitle" name="clientsTitle" type="text" class="large-text" value="' . esc_attr((string) ($home['clientsTitle'] ?? 'Our Clients')) . '" /></td></tr>';
        echo '<tr><th><label for="clientAppreciationsTitle">Client Appreciations Section Title</label></th><td><input id="clientAppreciationsTitle" name="clientAppreciationsTitle" type="text" class="large-text" value="' . esc_attr((string) ($awards['clientAppreciationsTitle'] ?? 'Client Appreciations')) . '" /></td></tr>';
        echo '<tr><th><label for="subtitle">Clients Subtitle</label></th><td><input id="subtitle" name="subtitle" type="text" class="large-text" value="' . esc_attr((string) ($opt['subtitle'] ?? '')) . '" /></td></tr>';
        echo '<tr><th>Display Options</th><td><label><input type="checkbox" name="showLogosOnHome" value="1" ' . checked(!isset($opt['showLogosOnHome']) || !empty($opt['showLogosOnHome']), true, false) . ' /> Show client logos on Home page</label><br>';
        echo '<label><input type="checkbox" name="showAppreciationsOnAwards" value="1" ' . checked(!isset($opt['showAppreciationsOnAwards']) || !empty($opt['showAppreciationsOnAwards']), true, false) . ' /> Show client appreciations on Awards page</label></td></tr>';
        echo '</table>';
        submit_button('Save Client Settings');
        echo '</form></div>';
    }

    public static function render_coverage_settings_page(): void {
        $home = get_option('pisl_single_home-page', []);
        $opt  = get_option('pisl_coverage_settings', []);
        if (!is_array($opt)) $opt = [];

        echo '<div class="wrap"><h1>Coverage Map Settings</h1>';
        self::render_notice();
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="postbox" style="padding:18px;max-width:860px;">';
        wp_nonce_field('pisl_hub_coverage', 'pisl_hub_nonce');
        echo '<input type="hidden" name="action" value="pisl_save_hub_settings" />';
        echo '<input type="hidden" name="pisl_section" value="coverage" />';
        echo '<table class="form-table">';
        echo '<tr><th><label for="coverageTitle">Coverage Section Title</label></th><td><input id="coverageTitle" name="coverageTitle" type="text" class="large-text" value="' . esc_attr((string) ($home['coverageTitle'] ?? 'Pan-India Footprint')) . '" /></td></tr>';
        echo '<tr><th><label for="mapSubheading">Coverage Map Subheading</label></th><td><textarea id="mapSubheading" name="mapSubheading" rows="2" class="large-text">' . esc_textarea((string) ($opt['mapSubheading'] ?? '')) . '</textarea></td></tr>';
        echo '<tr><th><label for="statLocations">Locations Stat</label></th><td><input id="statLocations" name="statLocations" type="text" class="regular-text" value="' . esc_attr((string) ($home['statLocations'] ?? '')) . '" /></td></tr>';
        echo '<tr><th><label for="statMicroMarkets">Micro-Markets Stat</label></th><td><input id="statMicroMarkets" name="statMicroMarkets" type="text" class="regular-text" value="' . esc_attr((string) ($home['statMicroMarkets'] ?? '')) . '" /></td></tr>';
        echo '<tr><th><label for="statSpaceSqFt">Delivered Space Stat</label></th><td><input id="statSpaceSqFt" name="statSpaceSqFt" type="text" class="regular-text" value="' . esc_attr((string) ($home['statSpaceSqFt'] ?? '')) . '" /></td></tr>';
        echo '<tr><th><label for="statPipelineSqFt">Pipeline Space Stat</label></th><td><input id="statPipelineSqFt" name="statPipelineSqFt" type="text" class="regular-text" value="' . esc_attr((string) ($home['statPipelineSqFt'] ?? '')) . '" /></td></tr>';
        echo '</table>';
        submit_button('Save Coverage Map Settings');
        echo ' <a class="button" style="margin-left:8px;" href="' . esc_url(admin_url('edit-tags.php?taxonomy=project_state&post_type=coverage_marker')) . '">Manage Project States</a>';
        echo '</form></div>';
    }

    public static function render_governance_settings_page(): void {
        $tab = (isset($_GET['tab']) && $_GET['tab'] === 'privacy') ? 'privacy' : 'governance';
        $slug = $tab === 'privacy' ? 'policy-page' : 'governance-page';
        $types = PISL_Schemas::get_single_types();

        echo '<div class="wrap">';
        echo '<h2 class="nav-tab-wrapper" style="margin-bottom:16px;">';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pisl-governance-settings&tab=governance')) . '" class="nav-tab ' . ($tab === 'governance' ? 'nav-tab-active' : '') . '">Company Policies &amp; Governance</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=pisl-governance-settings&tab=privacy')) . '" class="nav-tab ' . ($tab === 'privacy' ? 'nav-tab-active' : '') . '">Privacy Policy Page</a>';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=policy_document')) . '" class="nav-tab">All Policy Documents &nearr;</a>';
        echo '</h2>';
        if (isset($types[$slug])) {
            PISL_Single_Types_Admin::render_single_type_page($slug, $types[$slug]);
        }
        echo '</div>';
    }
}
