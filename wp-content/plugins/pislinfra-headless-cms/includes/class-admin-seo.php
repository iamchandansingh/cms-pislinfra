<?php
/**
 * Complete Enterprise SEO Management Module for PISL Headless CMS.
 *
 * Powers all 19 SEO Management submenus, unified Single-Type + CPT SEO storage,
 * live Google SERP Preview, Search/Filter/Pagination, Dynamic XML Sitemap,
 * Redirect Manager, 404 Monitor, Robots/Indexing, Schema & Structured Data,
 * Canonical URLs, Social Sharing Metadata, and Global SEO Settings.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Admin_SEO {
    const OPT_GLOBAL    = 'pisl_seo_global_settings';
    const OPT_SITEMAP   = 'pisl_seo_sitemap_settings';
    const OPT_SCHEMA    = 'pisl_seo_schema_settings';
    const OPT_ROBOTS    = 'pisl_seo_robots_settings';
    const OPT_SOCIAL    = 'pisl_seo_social_settings';
    const OPT_REDIRECTS = 'pisl_seo_redirects';
    const OPT_404_LOGS  = 'pisl_seo_404_logs';

    public static function init(): void {
        add_action('admin_post_pisl_seo_save_record', [__CLASS__, 'handle_save_record']);
        add_action('admin_post_pisl_seo_bulk_save', [__CLASS__, 'handle_bulk_save']);
        add_action('admin_post_pisl_seo_save_module', [__CLASS__, 'handle_save_module']);
        add_action('admin_post_pisl_seo_save_redirect', [__CLASS__, 'handle_save_redirect']);
        add_action('admin_post_pisl_seo_redirect_action', [__CLASS__, 'handle_redirect_action']);
        add_action('admin_post_pisl_seo_404_action', [__CLASS__, 'handle_404_action']);

        // WP-side Redirect enforcement, 404 capture, and robots.txt integration
        add_action('template_redirect', [__CLASS__, 'maybe_handle_wp_redirect_and_404'], 1);
        add_filter('robots_txt', [__CLASS__, 'filter_robots_txt'], 20, 2);
    }

    public static function get_defaults(): array {
        return [
            'frontendBaseUrl' => 'https://pislinfra.com',
            'titleSuffix'     => '| Pislinfra',
            'titleSeparator'  => '|',
            'defaultTitle'    => 'Pislinfra | Industrial Infrastructure & EPC Company India',
            'defaultMetaDesc' => 'Pislinfra delivers Grade-A warehousing, logistics parks, and industrial construction across India with 17+ years of engineering excellence.',
            'defaultKeywords' => 'Pislinfra, warehouse construction India, industrial infrastructure, logistics parks, turnkey EPC',
            'googleVerify'    => '',
            'bingVerify'      => '',
            'indexNowKey'     => 'pislinfra2026indexnowkey',
            'trailingSlash'   => 'no',
        ];
    }

    public static function get_global_settings(): array {
        $saved = get_option(self::OPT_GLOBAL, []);
        return array_merge(self::get_defaults(), is_array($saved) ? $saved : []);
    }

    public static function get_sitemap_settings(): array {
        $defaults = [
            'enabled'            => true,
            'includeSinglePages' => true,
            'includeBlogs'       => true,
            'includeOngoing'     => true,
            'includeCompleted'   => true,
            'includeCaseStudies' => true,
            'includeSolutions'   => true,
            'includeCareers'     => true,
            'includeAwards'      => false,
            'includeClients'     => false,
            'includePolicies'    => true,
            'defaultChangefreq'  => 'weekly',
            'defaultPriority'    => '0.8',
            'excludeNoindex'     => true,
        ];
        $saved = get_option(self::OPT_SITEMAP, []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    public static function get_schema_settings(): array {
        $defaults = [
            'orgName'             => 'Pragati Infra Solutions Pvt. Ltd.',
            'orgAltName'          => 'Pislinfra',
            'orgUrl'              => 'https://pislinfra.com',
            'orgLogo'             => 'https://pislinfra.com/logo.png',
            'orgPhone'            => '+91-8527040411',
            'orgEmail'            => 'info@pislinfra.com',
            'areaServed'          => 'IN',
            'sameAs'              => "https://www.linkedin.com/company/pislinfra\nhttps://twitter.com/Pislinfra\nhttps://www.facebook.com/pislinfra",
            'enableBreadcrumbs'   => true,
            'enableSpeakable'     => true,
            'enableOrganization'  => true,
            'defaultBlogSchema'   => 'BlogPosting',
            'defaultProjectSchema'=> 'CivicStructure',
            'defaultPageSchema'   => 'WebPage',
        ];
        $saved = get_option(self::OPT_SCHEMA, []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    public static function get_robots_settings(): array {
        $defaults = [
            'globalNoIndex'    => false,
            'globalNoFollow'   => false,
            'robotsTxtContent' => "User-agent: *\nAllow: /\nDisallow: /wp-admin/\nSitemap: https://pislinfra.com/sitemap.xml\n",
        ];
        $saved = get_option(self::OPT_ROBOTS, []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    public static function get_social_settings(): array {
        $defaults = [
            'ogSiteName'      => 'Pislinfra - Pragati Infra Solutions',
            'twitterHandle'   => '@Pislinfra',
            'twitterCardType' => 'summary_large_image',
            'defaultOgImage'  => null,
        ];
        $saved = get_option(self::OPT_SOCIAL, []);
        return array_merge($defaults, is_array($saved) ? $saved : []);
    }

    public static function get_redirects(): array {
        $saved = get_option(self::OPT_REDIRECTS, []);
        return is_array($saved) ? array_values($saved) : [];
    }

    public static function get_404_logs(): array {
        $saved = get_option(self::OPT_404_LOGS, []);
        return is_array($saved) ? array_values($saved) : [];
    }

    public static function log_404_hit(string $path, string $referrer = '', string $user_agent = '', string $source = 'wp'): void {
        $clean_path = '/' . ltrim((string) parse_url($path, PHP_URL_PATH), '/');
        if ($clean_path === '/' || str_starts_with($clean_path, '/wp-admin') || str_starts_with($clean_path, '/wp-json')) {
            return;
        }
        $logs = self::get_404_logs();
        $found = false;
        foreach ($logs as &$entry) {
            if (($entry['path'] ?? '') === $clean_path) {
                $entry['hits']     = (int) ($entry['hits'] ?? 1) + 1;
                $entry['lastHit']  = gmdate('c');
                $entry['referrer'] = $referrer ?: ($entry['referrer'] ?? '');
                $entry['source']   = $source;
                $found = true;
                break;
            }
        }
        unset($entry);
        if (!$found) {
            array_unshift($logs, [
                'id'       => substr(md5($clean_path . microtime(true)), 0, 10),
                'path'     => sanitize_text_field($clean_path),
                'referrer' => esc_url_raw($referrer),
                'ua'       => sanitize_text_field(substr($user_agent, 0, 120)),
                'source'   => sanitize_key($source),
                'hits'     => 1,
                'lastHit'  => gmdate('c'),
            ]);
            if (count($logs) > 200) {
                $logs = array_slice($logs, 0, 200);
            }
        }
        update_option(self::OPT_404_LOGS, $logs, false);
    }

    public static function maybe_handle_wp_redirect_and_404(): void {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        $req_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $req_path = '/' . ltrim((string) parse_url($req_uri, PHP_URL_PATH), '/');
        $req_norm = rtrim($req_path, '/') ?: '/';

        $redirects = self::get_redirects();
        foreach ($redirects as &$r) {
            if (empty($r['active'])) continue;
            $from = '/' . ltrim(rtrim((string) ($r['source'] ?? ''), '/'), '/');
            if ($from === $req_norm && !empty($r['target'])) {
                $r['hits'] = (int) ($r['hits'] ?? 0) + 1;
                $r['lastHit'] = gmdate('c');
                update_option(self::OPT_REDIRECTS, $redirects, false);
                $code = in_array((int) ($r['code'] ?? 301), [301, 302, 307], true) ? (int) $r['code'] : 301;
                wp_redirect(esc_url_raw($r['target']), $code);
                exit;
            }
        }
        unset($r);

        if (is_404()) {
            $ref = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
            $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
            self::log_404_hit($req_path, $ref, $ua, 'wp');
        }
    }

    public static function filter_robots_txt(string $output, bool $public): string {
        $cfg = self::get_robots_settings();
        if (!empty($cfg['globalNoIndex'])) {
            return "User-agent: *\nDisallow: /\n";
        }
        return !empty($cfg['robotsTxtContent']) ? (string) $cfg['robotsTxtContent'] . "\n" : $output;
    }

    private static function single_frontend_path(string $slug): string {
        $map = [
            'home-page'          => '/',
            'about-page'         => '/about',
            'leadership-page'    => '/about/leadership',
            'ehs-page'           => '/about/ehs',
            'csr-page'           => '/about/csr',
            'awards-page'        => '/about/awards',
            'contact-page'       => '/contact-us',
            'governance-page'    => '/company-policy',
            'policy-page'        => '/privacy-policy',
            'annual-report-page' => '/annual-reports',
            'blog-page'          => '/blog',
            'ongoing-page'       => '/projects/ongoing',
            'completed-page'     => '/projects/completed',
            'case-study-page'    => '/projects/case-study',
            'career-page'        => '/careers',
            'warehouse-page'     => '/solutions/warehouse',
        ];
        return $map[$slug] ?? '/' . $slug;
    }

    private static function post_frontend_path(\WP_Post $post): string {
        $fields = get_post_meta($post->ID, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];
        $slug = !empty($fields['slug']) ? (string) $fields['slug'] : ($post->post_name ?: (string) $post->ID);
        switch ($post->post_type) {
            case 'post':              return '/blog/' . $slug;
            case 'ongoing_project':   return '/project/ongoing/' . $slug;
            case 'completed_project': return '/project/completed/' . $slug;
            case 'case_study':        return '/projects/case-study/' . $slug;
            case 'solution_page':     return '/solutions/' . $slug;
            case 'award':             return '/about/awards';
            case 'client':            return '/';
            case 'job_opening':       return '/careers/' . $slug;
            case 'policy_document':   return '/company-policy';
            default:                  return '/' . $slug;
        }
    }

    public static function collect_seo_records(array $types = ['single', 'post', 'ongoing_project', 'completed_project', 'case_study', 'award', 'client', 'job_opening', 'solution_page', 'policy_document']): array {
        $globals = self::get_global_settings();
        $baseUrl = rtrim((string) $globals['frontendBaseUrl'], '/');
        $rows = [];

        if (in_array('single', $types, true)) {
            foreach (PISL_Schemas::get_single_types() as $slug => $cfg) {
                if (empty($cfg['has_seo'])) continue;
                $data = get_option('pisl_single_' . $slug, []);
                if (!is_array($data)) $data = [];
                $seo = isset($data['seo']) && is_array($data['seo']) ? $data['seo'] : [];

                $title   = trim((string) ($seo['seoTitle'] ?? $seo['metaTitle'] ?? $data['metaTitle'] ?? ''));
                $desc    = trim((string) ($seo['seoDescription'] ?? $seo['metaDescription'] ?? $data['metaDescription'] ?? ''));
                $focus   = trim((string) ($seo['focusKeyword'] ?? $data['primaryKeyword'] ?? ''));
                $secKw   = trim((string) ($seo['secondaryKeywords'] ?? $seo['seoKeywords'] ?? $data['focusKeywords'] ?? ''));
                $path    = self::single_frontend_path($slug);
                $canon   = trim((string) ($seo['canonicalUrl'] ?? '')) ?: ($baseUrl . $path);
                $ogTitle = trim((string) ($seo['ogTitle'] ?? ''));
                $ogDesc  = trim((string) ($seo['ogDescription'] ?? ''));
                $ogImg   = $seo['ogImage'] ?? '';
                $schemaT = trim((string) ($seo['schemaType'] ?? 'WebPage'));
                $noIdx   = !empty($seo['noIndex']);
                $noFol   = !empty($seo['noFollow']);

                $status = ($title !== '' && $desc !== '') ? 'ready' : (($title !== '' || $desc !== '') ? 'partial' : 'missing');
                $rows[] = [
                    'kind'              => 'single',
                    'target'            => $slug,
                    'typeLabel'         => 'Landing Page',
                    'name'              => $cfg['title'],
                    'path'              => $path,
                    'seoTitle'          => $title,
                    'seoDescription'    => $desc,
                    'focusKeyword'      => $focus,
                    'secondaryKeywords' => $secKw,
                    'canonicalUrl'      => $canon,
                    'customCanonical'   => trim((string) ($seo['canonicalUrl'] ?? '')),
                    'ogTitle'           => $ogTitle,
                    'ogDescription'     => $ogDesc,
                    'ogImage'           => $ogImg,
                    'schemaType'        => $schemaT,
                    'noIndex'           => $noIdx,
                    'noFollow'          => $noFol,
                    'status'            => $status,
                    'editLink'          => admin_url('admin.php?page=pisl-single-' . $slug),
                    'lastmod'           => (string) ($data['updatedAt'] ?? $data['_updatedAt'] ?? gmdate('c')),
                ];
            }
        }

        $cpt_labels = [
            'post'              => 'Blog Post',
            'ongoing_project'   => 'Ongoing Project',
            'completed_project' => 'Completed Project',
            'case_study'        => 'Case Study',
            'award'             => 'Award',
            'client'            => 'Client',
            'job_opening'       => 'Career / Job',
            'solution_page'     => 'Solution Page',
            'policy_document'   => 'Policy Document',
        ];
        $cpt_schemas = PISL_Schemas::get_cpt_schemas();

        foreach ($types as $pt) {
            if ($pt === 'single' || !isset($cpt_labels[$pt])) continue;
            $posts = get_posts([
                'post_type'      => $pt,
                'post_status'    => ['publish', 'draft', 'pending'],
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            foreach ($posts as $p) {
                $seo = get_post_meta($p->ID, '_pisl_seo', true);
                if (!is_array($seo)) $seo = [];
                $f = get_post_meta($p->ID, '_pisl_fields', true);
                if (!is_array($f)) $f = [];

                $title   = trim((string) ($seo['seoTitle'] ?? $seo['metaTitle'] ?? $f['metaTitle'] ?? ''));
                $desc    = trim((string) ($seo['seoDescription'] ?? $seo['metaDescription'] ?? $f['metaDescription'] ?? ''));
                $focus   = trim((string) ($seo['focusKeyword'] ?? $f['focusKeyword'] ?? $f['primaryKeyword'] ?? ''));
                $secKw   = trim((string) ($seo['secondaryKeywords'] ?? $seo['seoKeywords'] ?? $f['focusKeywords'] ?? ''));
                $path    = self::post_frontend_path($p);
                $canon   = trim((string) ($seo['canonicalUrl'] ?? '')) ?: ($baseUrl . $path);
                $ogTitle = trim((string) ($seo['ogTitle'] ?? ''));
                $ogDesc  = trim((string) ($seo['ogDescription'] ?? ''));
                $ogImg   = $seo['ogImage'] ?? ($f['featuredImage'] ?? $f['image'] ?? $f['logo'] ?? '');
                $schemaT = trim((string) ($seo['schemaType'] ?? ($pt === 'post' ? 'BlogPosting' : ($pt === 'job_opening' ? 'JobPosting' : 'WebPage'))));
                $noIdx   = !empty($seo['noIndex']);
                $noFol   = !empty($seo['noFollow']);

                $status = ($title !== '' && $desc !== '') ? 'ready' : (($title !== '' || $desc !== '') ? 'partial' : 'missing');
                $rows[] = [
                    'kind'              => 'post',
                    'target'            => (string) $p->ID,
                    'postType'          => $pt,
                    'postStatus'        => $p->post_status,
                    'typeLabel'         => $cpt_labels[$pt],
                    'name'              => $p->post_title ?: ($f['name'] ?? $f['title'] ?? 'Item #' . $p->ID),
                    'path'              => $path,
                    'seoTitle'          => $title,
                    'seoDescription'    => $desc,
                    'focusKeyword'      => $focus,
                    'secondaryKeywords' => $secKw,
                    'canonicalUrl'      => $canon,
                    'customCanonical'   => trim((string) ($seo['canonicalUrl'] ?? '')),
                    'ogTitle'           => $ogTitle,
                    'ogDescription'     => $ogDesc,
                    'ogImage'           => $ogImg,
                    'schemaType'        => $schemaT,
                    'noIndex'           => $noIdx,
                    'noFollow'          => $noFol,
                    'status'            => $status,
                    'editLink'          => get_edit_post_link($p->ID, 'raw') ?: admin_url('post.php?post=' . $p->ID . '&action=edit'),
                    'lastmod'           => mysql2date('c', $p->post_modified_gmt ?: $p->post_modified, false),
                ];
            }
        }
        return $rows;
    }

    private static function persist_record_seo(string $kind, string $target, array $updates): void {
        if ($kind === 'single' && isset(PISL_Schemas::get_single_types()[$target])) {
            $data = get_option('pisl_single_' . $target, []);
            if (!is_array($data)) $data = [];
            $seo = isset($data['seo']) && is_array($data['seo']) ? $data['seo'] : [];

            foreach ($updates as $k => $v) {
                $seo[$k] = $v;
            }
            // Keep backwards-compatible alias keys in sync without discarding data
            if (array_key_exists('seoTitle', $updates)) {
                $seo['metaTitle']   = $updates['seoTitle'];
                $data['metaTitle']  = $updates['seoTitle'];
            }
            if (array_key_exists('seoDescription', $updates)) {
                $seo['metaDescription']  = $updates['seoDescription'];
                $data['metaDescription'] = $updates['seoDescription'];
            }
            if (array_key_exists('focusKeyword', $updates)) {
                $data['primaryKeyword'] = $updates['focusKeyword'];
            }
            if (array_key_exists('secondaryKeywords', $updates)) {
                $seo['seoKeywords']    = $updates['secondaryKeywords'];
                $data['focusKeywords'] = $updates['secondaryKeywords'];
            }
            $data['seo']       = $seo;
            $data['updatedAt'] = gmdate('c');
            update_option('pisl_single_' . $target, $data, false);
        } elseif ($kind === 'post' && ctype_digit($target) && current_user_can('edit_post', (int) $target)) {
            $pid = (int) $target;
            $seo = get_post_meta($pid, '_pisl_seo', true);
            if (!is_array($seo)) $seo = [];
            foreach ($updates as $k => $v) {
                $seo[$k] = $v;
            }
            if (array_key_exists('seoTitle', $updates))          $seo['metaTitle']       = $updates['seoTitle'];
            if (array_key_exists('seoDescription', $updates))    $seo['metaDescription'] = $updates['seoDescription'];
            if (array_key_exists('secondaryKeywords', $updates)) $seo['seoKeywords']     = $updates['secondaryKeywords'];
            update_post_meta($pid, '_pisl_seo', $seo);

            $fields = get_post_meta($pid, '_pisl_fields', true);
            if (!is_array($fields)) $fields = [];
            $sync_map = [
                'seoTitle'          => 'metaTitle',
                'seoDescription'    => 'metaDescription',
                'focusKeyword'      => 'focusKeyword',
                'secondaryKeywords' => 'focusKeywords',
            ];
            $touched = false;
            foreach ($sync_map as $sk => $fk) {
                if (array_key_exists($sk, $updates) && array_key_exists($fk, $fields)) {
                    $fields[$fk] = $updates[$sk];
                    $touched = true;
                }
            }
            if (array_key_exists('focusKeyword', $updates) && array_key_exists('primaryKeyword', $fields)) {
                $fields['primaryKeyword'] = $updates['focusKeyword'];
                $touched = true;
            }
            if ($touched) {
                update_post_meta($pid, '_pisl_fields', $fields);
            }
        }
    }

    public static function handle_save_record(): void {
        check_admin_referer('pisl_seo_save_record_nonce');
        if (!current_user_can('edit_posts')) wp_die('Unauthorized');

        $kind   = sanitize_key(wp_unslash($_POST['pisl_seo_kind'] ?? ''));
        $target = sanitize_text_field(wp_unslash($_POST['pisl_seo_target'] ?? ''));
        $return = isset($_POST['pisl_return_url']) ? esc_url_raw(wp_unslash($_POST['pisl_return_url'])) : admin_url('admin.php?page=pisl-seo-management');

        $updates = [
            'seoTitle'          => sanitize_text_field(wp_unslash($_POST['seoTitle'] ?? '')),
            'seoDescription'    => sanitize_textarea_field(wp_unslash($_POST['seoDescription'] ?? '')),
            'focusKeyword'      => sanitize_text_field(wp_unslash($_POST['focusKeyword'] ?? '')),
            'secondaryKeywords' => sanitize_text_field(wp_unslash($_POST['secondaryKeywords'] ?? '')),
            'canonicalUrl'      => esc_url_raw(wp_unslash($_POST['canonicalUrl'] ?? '')),
            'ogTitle'           => sanitize_text_field(wp_unslash($_POST['ogTitle'] ?? '')),
            'ogDescription'     => sanitize_textarea_field(wp_unslash($_POST['ogDescription'] ?? '')),
            'ogImage'           => is_numeric($_POST['ogImage'] ?? '') ? (int) $_POST['ogImage'] : sanitize_text_field(wp_unslash($_POST['ogImage'] ?? '')),
            'schemaType'        => sanitize_text_field(wp_unslash($_POST['schemaType'] ?? '')),
            'noIndex'           => !empty($_POST['noIndex']),
            'noFollow'          => !empty($_POST['noFollow']),
        ];

        self::persist_record_seo($kind, $target, $updates);
        PISL_Security_CORS::bump_cache_version();
        wp_safe_redirect(add_query_arg('updated', '1', $return));
        exit;
    }

    public static function handle_bulk_save(): void {
        check_admin_referer('pisl_seo_bulk_save_nonce');
        if (!current_user_can('edit_posts')) wp_die('Unauthorized');

        $mode   = sanitize_key(wp_unslash($_POST['bulk_mode'] ?? 'meta'));
        $items  = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
        $return = isset($_POST['pisl_return_url']) ? esc_url_raw(wp_unslash($_POST['pisl_return_url'])) : admin_url('admin.php?page=pisl-seo-meta');

        foreach ($items as $raw_item) {
            if (!is_array($raw_item)) continue;
            $kind   = sanitize_key($raw_item['kind'] ?? '');
            $target = sanitize_text_field($raw_item['target'] ?? '');
            if ($kind === '' || $target === '') continue;

            $updates = [];
            if ($mode === 'meta') {
                $updates['seoTitle']          = sanitize_text_field(wp_unslash($raw_item['seoTitle'] ?? ''));
                $updates['seoDescription']    = sanitize_textarea_field(wp_unslash($raw_item['seoDescription'] ?? ''));
                $updates['focusKeyword']      = sanitize_text_field(wp_unslash($raw_item['focusKeyword'] ?? ''));
                $updates['secondaryKeywords'] = sanitize_text_field(wp_unslash($raw_item['secondaryKeywords'] ?? ''));
            } elseif ($mode === 'canonical') {
                $updates['canonicalUrl'] = esc_url_raw(wp_unslash($raw_item['canonicalUrl'] ?? ''));
            } elseif ($mode === 'robots') {
                $updates['noIndex']  = !empty($raw_item['noIndex']);
                $updates['noFollow'] = !empty($raw_item['noFollow']);
            } elseif ($mode === 'social') {
                $updates['ogTitle']       = sanitize_text_field(wp_unslash($raw_item['ogTitle'] ?? ''));
                $updates['ogDescription'] = sanitize_textarea_field(wp_unslash($raw_item['ogDescription'] ?? ''));
                $updates['ogImage']       = is_numeric($raw_item['ogImage'] ?? '') ? (int) $raw_item['ogImage'] : sanitize_text_field(wp_unslash($raw_item['ogImage'] ?? ''));
            }
            self::persist_record_seo($kind, $target, $updates);
        }

        PISL_Security_CORS::bump_cache_version();
        wp_safe_redirect(add_query_arg('updated', '1', $return));
        exit;
    }

    public static function handle_save_module(): void {
        $mod = sanitize_key(wp_unslash($_POST['pisl_seo_module'] ?? ''));
        check_admin_referer('pisl_seo_mod_' . $mod, 'pisl_seo_mod_nonce');
        if (!current_user_can('edit_posts')) wp_die('Unauthorized');

        $return = isset($_POST['pisl_return_url']) ? esc_url_raw(wp_unslash($_POST['pisl_return_url'])) : admin_url('admin.php?page=pisl-seo-settings');

        switch ($mod) {
            case 'sitemap':
                update_option(self::OPT_SITEMAP, [
                    'enabled'            => !empty($_POST['enabled']),
                    'includeSinglePages' => !empty($_POST['includeSinglePages']),
                    'includeBlogs'       => !empty($_POST['includeBlogs']),
                    'includeOngoing'     => !empty($_POST['includeOngoing']),
                    'includeCompleted'   => !empty($_POST['includeCompleted']),
                    'includeCaseStudies' => !empty($_POST['includeCaseStudies']),
                    'includeSolutions'   => !empty($_POST['includeSolutions']),
                    'includeCareers'     => !empty($_POST['includeCareers']),
                    'includeAwards'      => !empty($_POST['includeAwards']),
                    'includeClients'     => !empty($_POST['includeClients']),
                    'includePolicies'    => !empty($_POST['includePolicies']),
                    'excludeNoindex'     => !empty($_POST['excludeNoindex']),
                    'defaultChangefreq'  => sanitize_key(wp_unslash($_POST['defaultChangefreq'] ?? 'weekly')),
                    'defaultPriority'    => sanitize_text_field(wp_unslash($_POST['defaultPriority'] ?? '0.8')),
                ], false);
                break;

            case 'schema':
                update_option(self::OPT_SCHEMA, [
                    'orgName'              => sanitize_text_field(wp_unslash($_POST['orgName'] ?? '')),
                    'orgAltName'           => sanitize_text_field(wp_unslash($_POST['orgAltName'] ?? '')),
                    'orgUrl'               => esc_url_raw(wp_unslash($_POST['orgUrl'] ?? '')),
                    'orgLogo'              => esc_url_raw(wp_unslash($_POST['orgLogo'] ?? '')),
                    'orgPhone'             => sanitize_text_field(wp_unslash($_POST['orgPhone'] ?? '')),
                    'orgEmail'             => sanitize_email(wp_unslash($_POST['orgEmail'] ?? '')),
                    'areaServed'           => sanitize_text_field(wp_unslash($_POST['areaServed'] ?? 'IN')),
                    'sameAs'               => sanitize_textarea_field(wp_unslash($_POST['sameAs'] ?? '')),
                    'enableBreadcrumbs'    => !empty($_POST['enableBreadcrumbs']),
                    'enableSpeakable'      => !empty($_POST['enableSpeakable']),
                    'enableOrganization'   => !empty($_POST['enableOrganization']),
                    'defaultBlogSchema'    => sanitize_text_field(wp_unslash($_POST['defaultBlogSchema'] ?? 'BlogPosting')),
                    'defaultProjectSchema' => sanitize_text_field(wp_unslash($_POST['defaultProjectSchema'] ?? 'CivicStructure')),
                    'defaultPageSchema'    => sanitize_text_field(wp_unslash($_POST['defaultPageSchema'] ?? 'WebPage')),
                ], false);
                break;

            case 'robots':
                update_option(self::OPT_ROBOTS, [
                    'globalNoIndex'    => !empty($_POST['globalNoIndex']),
                    'globalNoFollow'   => !empty($_POST['globalNoFollow']),
                    'robotsTxtContent' => sanitize_textarea_field(wp_unslash($_POST['robotsTxtContent'] ?? '')),
                ], false);
                break;

            case 'social':
                $rawFields = isset($_POST['pisl_fields']) && is_array($_POST['pisl_fields']) ? $_POST['pisl_fields'] : [];
                update_option(self::OPT_SOCIAL, [
                    'ogSiteName'      => sanitize_text_field(wp_unslash($_POST['ogSiteName'] ?? '')),
                    'twitterHandle'   => sanitize_text_field(wp_unslash($_POST['twitterHandle'] ?? '')),
                    'twitterCardType' => sanitize_text_field(wp_unslash($_POST['twitterCardType'] ?? 'summary_large_image')),
                    'defaultOgImage'  => !empty($rawFields['defaultOgImage']) ? (int) $rawFields['defaultOgImage'] : null,
                ], false);
                break;

            case 'settings':
                update_option(self::OPT_GLOBAL, [
                    'frontendBaseUrl' => esc_url_raw(wp_unslash($_POST['frontendBaseUrl'] ?? 'https://pislinfra.com')),
                    'titleSeparator'  => sanitize_text_field(wp_unslash($_POST['titleSeparator'] ?? '|')),
                    'titleSuffix'     => sanitize_text_field(wp_unslash($_POST['titleSuffix'] ?? '| Pislinfra')),
                    'defaultTitle'    => sanitize_text_field(wp_unslash($_POST['defaultTitle'] ?? '')),
                    'defaultMetaDesc' => sanitize_textarea_field(wp_unslash($_POST['defaultMetaDesc'] ?? '')),
                    'defaultKeywords' => sanitize_textarea_field(wp_unslash($_POST['defaultKeywords'] ?? '')),
                    'googleVerify'    => sanitize_text_field(wp_unslash($_POST['googleVerify'] ?? '')),
                    'bingVerify'      => sanitize_text_field(wp_unslash($_POST['bingVerify'] ?? '')),
                    'indexNowKey'     => sanitize_text_field(wp_unslash($_POST['indexNowKey'] ?? '')),
                    'trailingSlash'   => sanitize_key(wp_unslash($_POST['trailingSlash'] ?? 'no')),
                ], false);
                break;
        }

        PISL_Security_CORS::bump_cache_version();
        wp_safe_redirect(add_query_arg('updated', '1', $return));
        exit;
    }

    public static function handle_save_redirect(): void {
        check_admin_referer('pisl_seo_save_redirect_nonce');
        if (!current_user_can('edit_posts')) wp_die('Unauthorized');

        $source = '/' . ltrim(sanitize_text_field(wp_unslash($_POST['source'] ?? '')), '/');
        $target = sanitize_text_field(wp_unslash($_POST['target'] ?? ''));
        $code   = in_array((int) ($_POST['code'] ?? 301), [301, 302, 307], true) ? (int) $_POST['code'] : 301;
        $active = !empty($_POST['active']);

        if ($source !== '/' && $target !== '') {
            $redirects = self::get_redirects();
            $redirects[] = [
                'id'      => substr(md5($source . microtime(true)), 0, 10),
                'source'  => $source,
                'target'  => $target,
                'code'    => $code,
                'active'  => $active,
                'hits'    => 0,
                'lastHit' => null,
            ];
            update_option(self::OPT_REDIRECTS, $redirects, false);
            PISL_Security_CORS::bump_cache_version();
        }
        wp_safe_redirect(admin_url('admin.php?page=pisl-seo-redirects&updated=1'));
        exit;
    }

    public static function handle_redirect_action(): void {
        $id  = sanitize_key(wp_unslash($_GET['rid'] ?? ''));
        $act = sanitize_key(wp_unslash($_GET['ract'] ?? ''));
        check_admin_referer('pisl_seo_redir_' . $id);
        if (!current_user_can('edit_posts')) wp_die('Unauthorized');

        $redirects = self::get_redirects();
        if ($act === 'delete') {
            $redirects = array_values(array_filter($redirects, fn($r) => ($r['id'] ?? '') !== $id));
        } elseif ($act === 'toggle') {
            foreach ($redirects as &$r) {
                if (($r['id'] ?? '') === $id) {
                    $r['active'] = empty($r['active']);
                }
            }
            unset($r);
        }
        update_option(self::OPT_REDIRECTS, $redirects, false);
        PISL_Security_CORS::bump_cache_version();
        wp_safe_redirect(admin_url('admin.php?page=pisl-seo-redirects&updated=1'));
        exit;
    }

    public static function handle_404_action(): void {
        $act = sanitize_key(wp_unslash($_GET['act404'] ?? ''));
        $id  = sanitize_key(wp_unslash($_GET['lid'] ?? ''));
        check_admin_referer('pisl_seo_404_nonce');
        if (!current_user_can('edit_posts')) wp_die('Unauthorized');

        if ($act === 'clear') {
            update_option(self::OPT_404_LOGS, [], false);
        } elseif ($act === 'delete' && $id !== '') {
            $logs = array_values(array_filter(self::get_404_logs(), fn($l) => ($l['id'] ?? '') !== $id));
            update_option(self::OPT_404_LOGS, $logs, false);
        }
        wp_safe_redirect(admin_url('admin.php?page=pisl-seo-404&updated=1'));
        exit;
    }

    public static function generate_sitemap_xml(): string {
        $cfg     = self::get_sitemap_settings();
        $globals = self::get_global_settings();
        $base    = rtrim((string) $globals['frontendBaseUrl'], '/');

        if (empty($cfg['enabled'])) {
            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        }

        $include_types = [];
        if (!empty($cfg['includeSinglePages'])) $include_types[] = 'single';
        if (!empty($cfg['includeBlogs']))       $include_types[] = 'post';
        if (!empty($cfg['includeOngoing']))     $include_types[] = 'ongoing_project';
        if (!empty($cfg['includeCompleted']))   $include_types[] = 'completed_project';
        if (!empty($cfg['includeCaseStudies'])) $include_types[] = 'case_study';
        if (!empty($cfg['includeSolutions']))   $include_types[] = 'solution_page';
        if (!empty($cfg['includeCareers']))     $include_types[] = 'job_opening';
        if (!empty($cfg['includeAwards']))      $include_types[] = 'award';
        if (!empty($cfg['includeClients']))     $include_types[] = 'client';
        if (!empty($cfg['includePolicies']))    $include_types[] = 'policy_document';

        $records = self::collect_seo_records($include_types);
        $seen = [];
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($records as $r) {
            if (($r['kind'] === 'post') && ($r['postStatus'] ?? 'publish') !== 'publish') continue;
            if (!empty($cfg['excludeNoindex']) && !empty($r['noIndex'])) continue;

            $loc = !empty($r['customCanonical']) ? $r['customCanonical'] : ($base . $r['path']);
            if (isset($seen[$loc])) continue;
            $seen[$loc] = true;

            $prio = ($r['path'] === '/') ? '1.0' : (($r['kind'] === 'single' || ($r['postType'] ?? '') === 'solution_page') ? '0.9' : (string) $cfg['defaultPriority']);
            $lastmod = !empty($r['lastmod']) ? gmdate('Y-m-d', strtotime((string) $r['lastmod']) ?: time()) : gmdate('Y-m-d');

            $xml .= "  <url>\n";
            $xml .= '    <loc>' . esc_xml($loc) . "</loc>\n";
            $xml .= '    <lastmod>' . esc_xml($lastmod) . "</lastmod>\n";
            $xml .= '    <changefreq>' . esc_xml((string) $cfg['defaultChangefreq']) . "</changefreq>\n";
            $xml .= '    <priority>' . esc_xml($prio) . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>';
        return $xml;
    }

    private static function render_header_nav(string $title, string $subtitle, string $active_slug): void {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html($title) . '</h1>';
        echo '<p class="description">' . esc_html($subtitle) . '</p>';
        if (!empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>SEO settings updated and REST API cache refreshed.</p></div>';
        }
        $tabs = [
            'pisl-seo-management' => 'Dashboard',
            'pisl-seo-all-pages'  => 'All Pages',
            'pisl-seo-blog'       => 'Blog',
            'pisl-seo-projects'   => 'Projects',
            'pisl-seo-case-studies'=> 'Case Studies',
            'pisl-seo-awards'     => 'Awards',
            'pisl-seo-clients'    => 'Clients',
            'pisl-seo-careers'    => 'Careers',
            'pisl-seo-solutions'  => 'Solutions',
            'pisl-seo-policies'   => 'Policies',
            'pisl-seo-meta'       => 'Meta Titles & Descriptions',
            'pisl-seo-sitemap'    => 'XML Sitemap',
            'pisl-seo-schema'     => 'Schema',
            'pisl-seo-canonicals' => 'Canonicals',
            'pisl-seo-robots'     => 'Robots',
            'pisl-seo-redirects'  => 'Redirects',
            'pisl-seo-404'        => '404 Monitor',
            'pisl-seo-social'     => 'Social',
            'pisl-seo-settings'   => 'Settings',
        ];
        echo '<div style="display:flex;flex-wrap:wrap;gap:6px;margin:12px 0 18px;padding-bottom:10px;border-bottom:1px solid #dcdcde;">';
        foreach ($tabs as $slug => $lbl) {
            $cls = $slug === $active_slug ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr($cls) . '" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">' . esc_html($lbl) . '</a>';
        }
        echo '</div>';
    }

    private static function render_serp_preview(string $title, string $url, string $desc): void {
        $t = $title !== '' ? $title : 'SEO Title Preview | Pislinfra';
        $d = $desc !== '' ? $desc : 'Meta description preview appears here for search engine snippets.';
        echo '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;max-width:600px;">';
        echo '<div style="font-size:12px;color:#202124;margin-bottom:2px;word-break:break-all;">' . esc_html($url) . '</div>';
        echo '<div style="font-size:17px;line-height:1.3;color:#1a0dab;font-weight:500;">' . esc_html($t) . '</div>';
        echo '<div style="font-size:13px;line-height:1.45;color:#4d5156;margin-top:4px;">' . esc_html($d) . '</div>';
        echo '</div>';
    }

    // 1. SEO Dashboard
    public static function render_dashboard(): void {
        self::render_header_nav('SEO Dashboard', 'Site-wide SEO health, completion metrics across all PISL pages & CPTs, sitemap, schema, redirects, and 404 monitor.', 'pisl-seo-management');
        $all = self::collect_seo_records();
        $total = count($all);
        $ready = 0; $partial = 0; $missing = 0; $noindex = 0;
        $by_type = [];
        foreach ($all as $r) {
            if ($r['status'] === 'ready') $ready++;
            elseif ($r['status'] === 'partial') $partial++;
            else $missing++;
            if (!empty($r['noIndex'])) $noindex++;
            $tl = $r['typeLabel'];
            if (!isset($by_type[$tl])) $by_type[$tl] = ['total' => 0, 'ready' => 0, 'missing' => 0];
            $by_type[$tl]['total']++;
            if ($r['status'] === 'ready') $by_type[$tl]['ready']++;
            elseif ($r['status'] === 'missing') $by_type[$tl]['missing']++;
        }
        $redirects = self::get_redirects();
        $logs404   = self::get_404_logs();

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:20px;">';
        $cards = [
            ['Total URLs Tracked', $total, '#0a2a66'],
            ['SEO Ready', $ready, '#166534'],
            ['Partial SEO', $partial, '#9a3412'],
            ['Missing SEO', $missing, '#991b1b'],
            ['NoIndex URLs', $noindex, '#475569'],
            ['Active Redirects', count(array_filter($redirects, fn($r) => !empty($r['active']))), '#1d4ed8'],
            ['Logged 404 Paths', count($logs404), '#b45309'],
        ];
        foreach ($cards as [$lbl, $val, $col]) {
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;">';
            echo '<div style="font-size:12px;color:#64748b;text-transform:uppercase;">' . esc_html($lbl) . '</div>';
            echo '<div style="font-size:26px;font-weight:700;color:' . esc_attr($col) . ';margin-top:4px;">' . esc_html((string) $val) . '</div>';
            echo '</div>';
        }
        echo '</div>';

        echo '<h2 style="margin-top:16px;">SEO Completion by Section</h2>';
        echo '<table class="widefat striped" style="margin-bottom:20px;"><thead><tr><th>Section</th><th>Total</th><th>SEO Ready</th><th>Missing</th><th>Quick Action</th></tr></thead><tbody>';
        $section_links = [
            'Landing Page'      => 'pisl-seo-all-pages',
            'Blog Post'         => 'pisl-seo-blog',
            'Ongoing Project'   => 'pisl-seo-projects',
            'Completed Project' => 'pisl-seo-projects',
            'Case Study'        => 'pisl-seo-case-studies',
            'Award'             => 'pisl-seo-awards',
            'Client'            => 'pisl-seo-clients',
            'Career / Job'      => 'pisl-seo-careers',
            'Solution Page'     => 'pisl-seo-solutions',
            'Policy Document'   => 'pisl-seo-policies',
        ];
        foreach ($by_type as $tl => $st) {
            $slug = $section_links[$tl] ?? 'pisl-seo-meta';
            echo '<tr><td><strong>' . esc_html($tl) . '</strong></td><td>' . esc_html((string) $st['total']) . '</td><td><span class="pisl-badge pisl-badge-pub">' . esc_html((string) $st['ready']) . '</span></td><td><span class="pisl-badge pisl-badge-warn">' . esc_html((string) $st['missing']) . '</span></td><td><a class="button button-small" href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '">Manage SEO</a></td></tr>';
        }
        echo '</tbody></table>';

        $globals = self::get_global_settings();
        echo '<h2>Headless React SEO Architecture Status</h2>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th style="width:240px;">SEO Engine Mode</th><td><span class="pisl-badge pisl-badge-pub">Native PISL Unified SEO</span> — Single source of truth synced across `_pisl_seo`, `pisl_single_*`, REST API, and React `BlogSEO.jsx` (no conflicting SEO plugins).</td></tr>';
        echo '<tr><th>Frontend Canonical Base</th><td><code>' . esc_html($globals['frontendBaseUrl']) . '</code></td></tr>';
        echo '<tr><th>Dynamic XML Sitemap</th><td><a target="_blank" href="' . esc_url(rest_url('pislinfra/v1/sitemap.xml')) . '">' . esc_html(rest_url('pislinfra/v1/sitemap.xml')) . '</a></td></tr>';
        echo '<tr><th>Live SEO Config REST</th><td><a target="_blank" href="' . esc_url(rest_url('pislinfra/v1/seo-config')) . '">' . esc_html(rest_url('pislinfra/v1/seo-config')) . '</a></td></tr>';
        echo '</tbody></table>';
        echo '</div>';
    }

    // Shared section SEO manager for submenus 2..10
    public static function render_section_seo(string $page_slug, string $heading, string $desc, array $types): void {
        self::render_header_nav($heading, $desc, $page_slug);

        // If editing a specific item (`edit_kind` + `edit_target`), show full 12-field SEO Editor + Live SERP Preview
        if (!empty($_GET['edit_kind']) && isset($_GET['edit_target'])) {
            $ek = sanitize_key(wp_unslash($_GET['edit_kind']));
            $et = sanitize_text_field(wp_unslash($_GET['edit_target']));
            $all = self::collect_seo_records($types);
            $found = null;
            foreach ($all as $item) {
                if ($item['kind'] === $ek && (string) $item['target'] === $et) {
                    $found = $item;
                    break;
                }
            }
            if ($found) {
                $back = remove_query_arg(['edit_kind', 'edit_target']);
                echo '<p><a href="' . esc_url($back) . '" class="button">&larr; Back to ' . esc_html($heading) . '</a></p>';
                echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:20px;max-width:920px;">';
                echo '<h2 style="margin-top:0;">Editing SEO: ' . esc_html($found['name']) . ' <code>' . esc_html($found['path']) . '</code></h2>';
                echo '<h4>Search Engine Preview</h4>';
                self::render_serp_preview($found['seoTitle'] ?: $found['name'], $found['canonicalUrl'], $found['seoDescription']);
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:16px;">';
                wp_nonce_field('pisl_seo_save_record_nonce');
                echo '<input type="hidden" name="action" value="pisl_seo_save_record" />';
                echo '<input type="hidden" name="pisl_seo_kind" value="' . esc_attr($found['kind']) . '" />';
                echo '<input type="hidden" name="pisl_seo_target" value="' . esc_attr($found['target']) . '" />';
                echo '<input type="hidden" name="pisl_return_url" value="' . esc_url($back) . '" />';
                echo '<table class="form-table"><tbody>';
                echo '<tr><th><label>SEO Title</label></th><td><input type="text" name="seoTitle" value="' . esc_attr($found['seoTitle']) . '" class="large-text" /><p class="description">Recommended 50–60 chars (current: ' . strlen($found['seoTitle']) . ')</p></td></tr>';
                echo '<tr><th><label>Meta Description</label></th><td><textarea name="seoDescription" rows="3" class="large-text">' . esc_textarea($found['seoDescription']) . '</textarea><p class="description">Recommended 140–160 chars (current: ' . strlen($found['seoDescription']) . ')</p></td></tr>';
                echo '<tr><th><label>Focus Keyword</label></th><td><input type="text" name="focusKeyword" value="' . esc_attr($found['focusKeyword']) . '" class="regular-text" /></td></tr>';
                echo '<tr><th><label>Secondary Keywords</label></th><td><input type="text" name="secondaryKeywords" value="' . esc_attr($found['secondaryKeywords']) . '" class="large-text" /></td></tr>';
                echo '<tr><th><label>Canonical URL</label></th><td><input type="url" name="canonicalUrl" value="' . esc_attr($found['customCanonical']) . '" placeholder="' . esc_attr($found['canonicalUrl']) . '" class="large-text" /></td></tr>';
                echo '<tr><th><label>Robots Indexing</label></th><td><label style="margin-right:16px;"><input type="checkbox" name="noIndex" value="1"' . ($found['noIndex'] ? ' checked' : '') . ' /> NoIndex</label><label><input type="checkbox" name="noFollow" value="1"' . ($found['noFollow'] ? ' checked' : '') . ' /> NoFollow</label></td></tr>';
                echo '<tr><th><label>Open Graph Title</label></th><td><input type="text" name="ogTitle" value="' . esc_attr($found['ogTitle']) . '" class="large-text" /></td></tr>';
                echo '<tr><th><label>Open Graph Description</label></th><td><textarea name="ogDescription" rows="2" class="large-text">' . esc_textarea($found['ogDescription']) . '</textarea></td></tr>';
                echo '<tr><th><label>Social Sharing Image</label></th><td>';
                PISL_Field_Renderer::render_single_media_picker('ogImage', $found['ogImage']);
                echo '</td></tr>';
                echo '<tr><th><label>Schema Type</label></th><td><input type="text" name="schemaType" value="' . esc_attr($found['schemaType']) . '" class="regular-text" placeholder="WebPage / BlogPosting / CivicStructure" /></td></tr>';
                echo '</tbody></table>';
                submit_button('Save SEO Metadata');
                echo '</form></div></div>';
                return;
            }
        }

        $q       = isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash($_GET['s']))) : '';
        $statusF = isset($_GET['seo_status']) ? sanitize_key(wp_unslash($_GET['seo_status'])) : '';
        $paged   = max(1, (int) ($_GET['paged'] ?? 1));
        $perPage = 20;

        $records = self::collect_seo_records($types);
        $records = array_values(array_filter($records, function ($r) use ($q, $statusF) {
            if ($statusF === 'noindex' && empty($r['noIndex'])) return false;
            if (in_array($statusF, ['ready', 'partial', 'missing'], true) && $r['status'] !== $statusF) return false;
            if ($q !== '') {
                $hay = strtolower($r['name'] . ' ' . $r['path'] . ' ' . $r['seoTitle'] . ' ' . $r['focusKeyword']);
                if (!str_contains($hay, strtolower($q))) return false;
            }
            return true;
        }));

        $total = count($records);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $slice = array_slice($records, ($paged - 1) * $perPage, $perPage);

        echo '<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '" />';
        echo '<input type="search" name="s" value="' . esc_attr($q) . '" placeholder="Search title, URL, focus keyword..." class="regular-text" />';
        echo '<select name="seo_status"><option value="">All Statuses (' . esc_html((string) $total) . ')</option>';
        foreach (['ready' => 'SEO Ready', 'partial' => 'Partial SEO', 'missing' => 'Missing SEO', 'noindex' => 'NoIndex'] as $sv => $sl) {
            echo '<option value="' . esc_attr($sv) . '"' . ($statusF === $sv ? ' selected' : '') . '>' . esc_html($sl) . '</option>';
        }
        echo '</select>';
        submit_button('Filter', 'secondary', '', false);
        echo '</form>';

        echo '<table class="widefat striped"><thead><tr><th>Title / URL</th><th>Type</th><th>SEO Title &amp; Meta Description</th><th>Focus Keyword</th><th>Robots / Schema</th><th>SEO Status</th><th>Actions</th></tr></thead><tbody>';
        if (empty($slice)) {
            echo '<tr><td colspan="7">No matching SEO records found.</td></tr>';
        } else {
            foreach ($slice as $r) {
                $badgeCls = $r['status'] === 'ready' ? 'pisl-badge-pub' : ($r['status'] === 'partial' ? 'pisl-badge-warn' : 'pisl-badge-draft');
                $badgeTxt = $r['status'] === 'ready' ? 'SEO Ready' : ($r['status'] === 'partial' ? 'Partial' : 'Missing SEO');
                $editSeoUrl = add_query_arg(['edit_kind' => $r['kind'], 'edit_target' => $r['target']], admin_url('admin.php?page=' . $page_slug));

                echo '<tr>';
                echo '<td><strong>' . esc_html($r['name']) . '</strong><br><code style="font-size:11px;">' . esc_html($r['path']) . '</code></td>';
                echo '<td>' . esc_html($r['typeLabel']) . '</td>';
                echo '<td style="max-width:340px;">';
                echo '<div style="color:#1a0dab;font-weight:600;font-size:13px;">' . esc_html($r['seoTitle'] ?: '(Not set)') . '</div>';
                echo '<div style="color:#4d5156;font-size:12px;margin-top:3px;">' . esc_html(wp_trim_words($r['seoDescription'] ?: '(No meta description)', 18)) . '</div>';
                echo '</td>';
                echo '<td>' . ($r['focusKeyword'] ? '<code>' . esc_html($r['focusKeyword']) . '</code>' : '—') . '</td>';
                echo '<td>' . ($r['noIndex'] ? '<span class="pisl-badge pisl-badge-warn">noindex</span>' : '<span class="pisl-badge pisl-badge-info">index</span>') . ' ' . esc_html($r['schemaType']) . '</td>';
                echo '<td><span class="pisl-badge ' . esc_attr($badgeCls) . '">' . esc_html($badgeTxt) . '</span></td>';
                echo '<td><a class="button button-primary button-small" href="' . esc_url($editSeoUrl) . '">Edit SEO</a> <a class="button button-small" href="' . esc_url($r['editLink']) . '">Content</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        if ($totalPages > 1) {
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            echo paginate_links([
                'base'    => add_query_arg('paged', '%#%'),
                'format'  => '',
                'current' => $paged,
                'total'   => $totalPages,
            ]);
            echo '</div></div>';
        }
        echo '</div>';
    }

    // 11, 14, 15, 18: Bulk Editors (Meta Titles & Descriptions, Canonical URLs, Robots & Indexing, Social Sharing Metadata)
    public static function render_bulk_editor(string $page_slug, string $mode, string $heading, string $desc): void {
        self::render_header_nav($heading, $desc, $page_slug);

        if ($mode === 'robots') {
            $robots = self::get_robots_settings();
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;margin-bottom:18px;">';
            echo '<h2 style="margin-top:0;">Global Robots &amp; robots.txt Rules</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pisl_seo_mod_robots', 'pisl_seo_mod_nonce');
            echo '<input type="hidden" name="action" value="pisl_seo_save_module" /><input type="hidden" name="pisl_seo_module" value="robots" /><input type="hidden" name="pisl_return_url" value="' . esc_url(admin_url('admin.php?page=' . $page_slug)) . '" />';
            echo '<p><label style="margin-right:18px;"><input type="checkbox" name="globalNoIndex" value="1"' . (!empty($robots['globalNoIndex']) ? ' checked' : '') . ' /> Global NoIndex (discourage all search engines)</label>';
            echo '<label><input type="checkbox" name="globalNoFollow" value="1"' . (!empty($robots['globalNoFollow']) ? ' checked' : '') . ' /> Global NoFollow</label></p>';
            echo '<p><label><strong>robots.txt Directives</strong></label><br><textarea name="robotsTxtContent" rows="5" class="large-text code">' . esc_textarea($robots['robotsTxtContent']) . '</textarea></p>';
            submit_button('Save Global Robots Settings');
            echo '</form></div>';
        }

        if ($mode === 'social') {
            $soc = self::get_social_settings();
            echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;margin-bottom:18px;">';
            echo '<h2 style="margin-top:0;">Default Open Graph &amp; Twitter Card Settings</h2>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            wp_nonce_field('pisl_seo_mod_social', 'pisl_seo_mod_nonce');
            echo '<input type="hidden" name="action" value="pisl_seo_save_module" /><input type="hidden" name="pisl_seo_module" value="social" /><input type="hidden" name="pisl_return_url" value="' . esc_url(admin_url('admin.php?page=' . $page_slug)) . '" />';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th>OG Site Name</th><td><input type="text" name="ogSiteName" value="' . esc_attr($soc['ogSiteName']) . '" class="regular-text" /></td></tr>';
            echo '<tr><th>Twitter Handle</th><td><input type="text" name="twitterHandle" value="' . esc_attr($soc['twitterHandle']) . '" class="regular-text" /></td></tr>';
            echo '<tr><th>Twitter Card Type</th><td><select name="twitterCardType"><option value="summary_large_image"' . selected($soc['twitterCardType'], 'summary_large_image', false) . '>summary_large_image</option><option value="summary"' . selected($soc['twitterCardType'], 'summary', false) . '>summary</option></select></td></tr>';
            echo '<tr><th>Default Social Sharing Image</th><td>';
            PISL_Field_Renderer::render_single_media_picker('pisl_fields[defaultOgImage]', $soc['defaultOgImage']);
            echo '</td></tr></tbody></table>';
            submit_button('Save Global Social Defaults');
            echo '</form></div>';
        }

        $q     = isset($_GET['s']) ? trim(sanitize_text_field(wp_unslash($_GET['s']))) : '';
        $typeF = isset($_GET['rtype']) ? sanitize_key(wp_unslash($_GET['rtype'])) : '';
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $perPage = 15;

        $all = self::collect_seo_records($typeF ? [$typeF] : ['single', 'post', 'ongoing_project', 'completed_project', 'case_study', 'solution_page', 'award', 'client', 'job_opening', 'policy_document']);
        if ($q !== '') {
            $all = array_values(array_filter($all, fn($r) => str_contains(strtolower($r['name'] . ' ' . $r['path'] . ' ' . $r['seoTitle']), strtolower($q))));
        }

        $total = count($all);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $slice = array_slice($all, ($paged - 1) * $perPage, $perPage);

        echo '<form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($page_slug) . '" />';
        echo '<input type="search" name="s" value="' . esc_attr($q) . '" placeholder="Filter records..." class="regular-text" />';
        echo '<select name="rtype"><option value="">All Content Types</option>';
        foreach (['single' => 'Landing Pages', 'post' => 'Blog Posts', 'ongoing_project' => 'Ongoing Projects', 'completed_project' => 'Completed Projects', 'case_study' => 'Case Studies', 'solution_page' => 'Solutions', 'award' => 'Awards', 'client' => 'Clients', 'job_opening' => 'Careers', 'policy_document' => 'Policies'] as $tv => $tl) {
            echo '<option value="' . esc_attr($tv) . '"' . ($typeF === $tv ? ' selected' : '') . '>' . esc_html($tl) . '</option>';
        }
        echo '</select>';
        submit_button('Filter', 'secondary', '', false);
        echo '</form>';

        $current_url = add_query_arg(['paged' => $paged, 's' => $q, 'rtype' => $typeF], admin_url('admin.php?page=' . $page_slug));
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('pisl_seo_bulk_save_nonce');
        echo '<input type="hidden" name="action" value="pisl_seo_bulk_save" />';
        echo '<input type="hidden" name="bulk_mode" value="' . esc_attr($mode) . '" />';
        echo '<input type="hidden" name="pisl_return_url" value="' . esc_url($current_url) . '" />';

        echo '<table class="widefat striped"><thead><tr><th>Page / Record</th>';
        if ($mode === 'meta') {
            echo '<th>SEO Title &amp; Focus Keyword</th><th>Meta Description &amp; Secondary Keywords</th>';
        } elseif ($mode === 'canonical') {
            echo '<th>Custom Canonical URL (leave blank for auto-computed)</th><th>Effective URL</th>';
        } elseif ($mode === 'robots') {
            echo '<th>Index / NoIndex</th><th>Follow / Nofollow</th>';
        } elseif ($mode === 'social') {
            echo '<th>OG Title &amp; OG Description</th><th>Social Image</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ($slice as $idx => $r) {
            $pfx = "items[$idx]";
            echo '<tr>';
            echo '<td style="width:24%;"><strong>' . esc_html($r['name']) . '</strong><br><small>' . esc_html($r['typeLabel']) . ' &bull; <code>' . esc_html($r['path']) . '</code></small>';
            echo '<input type="hidden" name="' . esc_attr($pfx . '[kind]') . '" value="' . esc_attr($r['kind']) . '" />';
            echo '<input type="hidden" name="' . esc_attr($pfx . '[target]') . '" value="' . esc_attr($r['target']) . '" /></td>';

            if ($mode === 'meta') {
                echo '<td><input type="text" name="' . esc_attr($pfx . '[seoTitle]') . '" value="' . esc_attr($r['seoTitle']) . '" placeholder="SEO Title" class="large-text" style="margin-bottom:6px;" />';
                echo '<input type="text" name="' . esc_attr($pfx . '[focusKeyword]') . '" value="' . esc_attr($r['focusKeyword']) . '" placeholder="Focus Keyword" class="large-text" /></td>';
                echo '<td><textarea name="' . esc_attr($pfx . '[seoDescription]') . '" rows="2" placeholder="Meta Description (140-160 chars)" class="large-text" style="margin-bottom:6px;">' . esc_textarea($r['seoDescription']) . '</textarea>';
                echo '<input type="text" name="' . esc_attr($pfx . '[secondaryKeywords]') . '" value="' . esc_attr($r['secondaryKeywords']) . '" placeholder="Secondary keywords (comma-separated)" class="large-text" /></td>';
            } elseif ($mode === 'canonical') {
                echo '<td><input type="url" name="' . esc_attr($pfx . '[canonicalUrl]') . '" value="' . esc_attr($r['customCanonical']) . '" placeholder="' . esc_attr($r['canonicalUrl']) . '" class="large-text" /></td>';
                echo '<td><code>' . esc_html($r['canonicalUrl']) . '</code></td>';
            } elseif ($mode === 'robots') {
                echo '<td><label><input type="checkbox" name="' . esc_attr($pfx . '[noIndex]') . '" value="1"' . ($r['noIndex'] ? ' checked' : '') . ' /> NoIndex</label></td>';
                echo '<td><label><input type="checkbox" name="' . esc_attr($pfx . '[noFollow]') . '" value="1"' . ($r['noFollow'] ? ' checked' : '') . ' /> NoFollow</label></td>';
            } elseif ($mode === 'social') {
                echo '<td><input type="text" name="' . esc_attr($pfx . '[ogTitle]') . '" value="' . esc_attr($r['ogTitle']) . '" placeholder="OG Title (falls back to SEO Title)" class="large-text" style="margin-bottom:6px;" />';
                echo '<textarea name="' . esc_attr($pfx . '[ogDescription]') . '" rows="2" placeholder="OG Description" class="large-text">' . esc_textarea($r['ogDescription']) . '</textarea></td>';
                echo '<td>';
                PISL_Field_Renderer::render_single_media_picker($pfx . '[ogImage]', $r['ogImage']);
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p style="margin-top:14px;">';
        submit_button('Save Changes on This Page', 'primary', 'submit', false);
        echo '</p></form>';

        if ($totalPages > 1) {
            echo '<div class="tablenav bottom"><div class="tablenav-pages">' . paginate_links([
                'base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $totalPages,
            ]) . '</div></div>';
        }
        echo '</div>';
    }

    // 12. XML Sitemap
    public static function render_sitemap_page(): void {
        self::render_header_nav('XML Sitemap', 'Dynamic XML sitemap configuration & live preview for search engines targeting https://pislinfra.com.', 'pisl-seo-sitemap');
        $cfg = self::get_sitemap_settings();
        $xml = self::generate_sitemap_xml();
        $url_count = substr_count($xml, '<url>');

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;margin-bottom:20px;">';
        wp_nonce_field('pisl_seo_mod_sitemap', 'pisl_seo_mod_nonce');
        echo '<input type="hidden" name="action" value="pisl_seo_save_module" /><input type="hidden" name="pisl_seo_module" value="sitemap" /><input type="hidden" name="pisl_return_url" value="' . esc_url(admin_url('admin.php?page=pisl-seo-sitemap')) . '" />';
        echo '<p><strong>Live XML Sitemap Endpoint:</strong> <a target="_blank" href="' . esc_url(rest_url('pislinfra/v1/sitemap.xml')) . '">' . esc_html(rest_url('pislinfra/v1/sitemap.xml')) . '</a> (' . esc_html((string) $url_count) . ' URLs included)</p>';
        echo '<table class="form-table"><tbody>';
        $toggles = [
            'enabled'            => 'Enable Dynamic XML Sitemap',
            'excludeNoindex'     => 'Automatically exclude NoIndex URLs',
            'includeSinglePages' => 'Include Landing Pages',
            'includeBlogs'       => 'Include Blog Posts',
            'includeOngoing'     => 'Include Ongoing Projects',
            'includeCompleted'   => 'Include Completed Projects',
            'includeCaseStudies' => 'Include Case Studies',
            'includeSolutions'   => 'Include Solution Pages',
            'includeCareers'     => 'Include Careers / Jobs',
            'includePolicies'    => 'Include Governance / Policies',
            'includeAwards'      => 'Include Individual Award Records',
            'includeClients'     => 'Include Individual Client Records',
        ];
        foreach ($toggles as $k => $lbl) {
            echo '<tr><th>' . esc_html($lbl) . '</th><td><label><input type="checkbox" name="' . esc_attr($k) . '" value="1"' . (!empty($cfg[$k]) ? ' checked' : '') . ' /> Enabled</label></td></tr>';
        }
        echo '<tr><th>Default Change Frequency</th><td><select name="defaultChangefreq">';
        foreach (['daily', 'weekly', 'monthly'] as $cf) {
            echo '<option value="' . esc_attr($cf) . '"' . selected($cfg['defaultChangefreq'], $cf, false) . '>' . esc_html($cf) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>Default Priority</th><td><input type="text" name="defaultPriority" value="' . esc_attr($cfg['defaultPriority']) . '" class="small-text" /></td></tr>';
        echo '</tbody></table>';
        submit_button('Save Sitemap Settings');
        echo '</form>';

        echo '<h3>Generated XML Sitemap Preview (first 35 lines)</h3>';
        $preview = implode("\n", array_slice(explode("\n", $xml), 0, 35));
        echo '<pre style="background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;overflow:auto;max-height:340px;">' . esc_html($preview) . '</pre>';
        echo '</div>';
    }

    // 13. Schema & Structured Data
    public static function render_schema_page(): void {
        self::render_header_nav('Schema & Structured Data', 'Configure Organization Knowledge Graph, Speakable, Breadcrumb, and default JSON-LD types served to React.', 'pisl-seo-schema');
        $cfg = self::get_schema_settings();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;margin-bottom:20px;">';
        wp_nonce_field('pisl_seo_mod_schema', 'pisl_seo_mod_nonce');
        echo '<input type="hidden" name="action" value="pisl_seo_save_module" /><input type="hidden" name="pisl_seo_module" value="schema" /><input type="hidden" name="pisl_return_url" value="' . esc_url(admin_url('admin.php?page=pisl-seo-schema')) . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Organization Name</th><td><input type="text" name="orgName" value="' . esc_attr($cfg['orgName']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Alternate / Brand Name</th><td><input type="text" name="orgAltName" value="' . esc_attr($cfg['orgAltName']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Organization URL</th><td><input type="url" name="orgUrl" value="' . esc_attr($cfg['orgUrl']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Organization Logo URL</th><td><input type="url" name="orgLogo" value="' . esc_attr($cfg['orgLogo']) . '" class="large-text" /></td></tr>';
        echo '<tr><th>Contact Phone</th><td><input type="text" name="orgPhone" value="' . esc_attr($cfg['orgPhone']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Contact Email</th><td><input type="email" name="orgEmail" value="' . esc_attr($cfg['orgEmail']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>SameAs Social URLs (1 per line)</th><td><textarea name="sameAs" rows="3" class="large-text">' . esc_textarea($cfg['sameAs']) . '</textarea></td></tr>';
        echo '<tr><th>Default Schema Types</th><td>Blog: <input type="text" name="defaultBlogSchema" value="' . esc_attr($cfg['defaultBlogSchema']) . '" class="regular-text" style="width:140px;" /> Projects: <input type="text" name="defaultProjectSchema" value="' . esc_attr($cfg['defaultProjectSchema']) . '" class="regular-text" style="width:140px;" /> Pages: <input type="text" name="defaultPageSchema" value="' . esc_attr($cfg['defaultPageSchema']) . '" class="regular-text" style="width:140px;" /></td></tr>';
        echo '<tr><th>JSON-LD Graph Modules</th><td>';
        echo '<label style="margin-right:16px;"><input type="checkbox" name="enableOrganization" value="1"' . (!empty($cfg['enableOrganization']) ? ' checked' : '') . ' /> Organization</label>';
        echo '<label style="margin-right:16px;"><input type="checkbox" name="enableBreadcrumbs" value="1"' . (!empty($cfg['enableBreadcrumbs']) ? ' checked' : '') . ' /> BreadcrumbList</label>';
        echo '<label><input type="checkbox" name="enableSpeakable" value="1"' . (!empty($cfg['enableSpeakable']) ? ' checked' : '') . ' /> Speakable</label>';
        echo '</td></tr></tbody></table>';
        submit_button('Save Schema Settings');
        echo '</form></div>';
    }

    // 16. Redirects
    public static function render_redirects_page(): void {
        self::render_header_nav('Redirects', 'Manage 301/302/307 URL redirects enforced on WordPress and synchronized with the React frontend.', 'pisl-seo-redirects');
        $prefill_src = isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '';

        echo '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px 20px;margin-bottom:20px;">';
        echo '<h2 style="margin-top:0;">Add New Redirect</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
        wp_nonce_field('pisl_seo_save_redirect_nonce');
        echo '<input type="hidden" name="action" value="pisl_seo_save_redirect" />';
        echo '<input type="text" name="source" value="' . esc_attr($prefill_src) . '" placeholder="/old-url" class="regular-text" required />';
        echo '<input type="text" name="target" placeholder="/new-url or https://pislinfra.com/..." class="regular-text" required />';
        echo '<select name="code"><option value="301">301 Permanent</option><option value="302">302 Temporary</option><option value="307">307 Redirect</option></select>';
        echo '<label><input type="checkbox" name="active" value="1" checked /> Active</label>';
        submit_button('Add Redirect', 'primary', 'submit', false);
        echo '</form></div>';

        $redirects = self::get_redirects();
        echo '<table class="widefat striped"><thead><tr><th>Source Path</th><th>Target URL</th><th>Type</th><th>Hits</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
        if (empty($redirects)) {
            echo '<tr><td colspan="6">No redirects configured yet.</td></tr>';
        } else {
            foreach ($redirects as $r) {
                $rid = (string) ($r['id'] ?? '');
                $toggleUrl = wp_nonce_url(admin_url('admin-post.php?action=pisl_seo_redirect_action&ract=toggle&rid=' . $rid), 'pisl_seo_redir_' . $rid);
                $delUrl    = wp_nonce_url(admin_url('admin-post.php?action=pisl_seo_redirect_action&ract=delete&rid=' . $rid), 'pisl_seo_redir_' . $rid);
                echo '<tr>';
                echo '<td><code>' . esc_html((string) ($r['source'] ?? '')) . '</code></td>';
                echo '<td><code>' . esc_html((string) ($r['target'] ?? '')) . '</code></td>';
                echo '<td>' . esc_html((string) ($r['code'] ?? 301)) . '</td>';
                echo '<td>' . esc_html((string) ($r['hits'] ?? 0)) . '</td>';
                echo '<td>' . (!empty($r['active']) ? '<span class="pisl-badge pisl-badge-pub">Active</span>' : '<span class="pisl-badge pisl-badge-draft">Paused</span>') . '</td>';
                echo '<td><a class="button button-small" href="' . esc_url($toggleUrl) . '">Toggle</a> <a class="button button-small" href="' . esc_url($delUrl) . '" onclick="return confirm(\'Delete redirect?\')">Delete</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    // 17. 404 Monitor
    public static function render_404_page(): void {
        self::render_header_nav('404 Monitor', 'Tracks broken URLs and missing routes reported by WordPress and the React frontend.', 'pisl-seo-404');
        $clearUrl = wp_nonce_url(admin_url('admin-post.php?action=pisl_seo_404_action&act404=clear'), 'pisl_seo_404_nonce');
        $logs = self::get_404_logs();

        echo '<p><a href="' . esc_url($clearUrl) . '" class="button" onclick="return confirm(\'Clear all 404 logs?\')">Clear All 404 Logs (' . count($logs) . ')</a></p>';
        echo '<table class="widefat striped"><thead><tr><th>Missing Path</th><th>Source</th><th>Hits</th><th>Referrer</th><th>Last Hit</th><th>Actions</th></tr></thead><tbody>';
        if (empty($logs)) {
            echo '<tr><td colspan="6">Zero 404 errors logged so far.</td></tr>';
        } else {
            foreach ($logs as $l) {
                $lid = (string) ($l['id'] ?? '');
                $redirUrl = admin_url('admin.php?page=pisl-seo-redirects&from=' . urlencode((string) ($l['path'] ?? '')));
                $delUrl   = wp_nonce_url(admin_url('admin-post.php?action=pisl_seo_404_action&act404=delete&lid=' . $lid), 'pisl_seo_404_nonce');
                echo '<tr>';
                echo '<td><code>' . esc_html((string) ($l['path'] ?? '')) . '</code></td>';
                echo '<td><span class="pisl-badge pisl-badge-info">' . esc_html(strtoupper((string) ($l['source'] ?? 'wp'))) . '</span></td>';
                echo '<td><strong>' . esc_html((string) ($l['hits'] ?? 1)) . '</strong></td>';
                echo '<td>' . esc_html((string) ($l['referrer'] ?? '—')) . '</td>';
                echo '<td>' . esc_html((string) ($l['lastHit'] ?? '')) . '</td>';
                echo '<td><a class="button button-primary button-small" href="' . esc_url($redirUrl) . '">Create Redirect</a> <a class="button button-small" href="' . esc_url($delUrl) . '">Dismiss</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    // 19. SEO Settings
    public static function render_settings_page(): void {
        self::render_header_nav('SEO Settings', 'Global SEO title templates, canonical base URL, search engine verification tags, and IndexNow settings.', 'pisl-seo-settings');
        $cfg = self::get_global_settings();

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px 20px;">';
        wp_nonce_field('pisl_seo_mod_settings', 'pisl_seo_mod_nonce');
        echo '<input type="hidden" name="action" value="pisl_seo_save_module" /><input type="hidden" name="pisl_seo_module" value="settings" /><input type="hidden" name="pisl_return_url" value="' . esc_url(admin_url('admin.php?page=pisl-seo-settings')) . '" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Frontend Canonical Base URL</th><td><input type="url" name="frontendBaseUrl" value="' . esc_attr($cfg['frontendBaseUrl']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Title Separator / Suffix</th><td>Separator: <input type="text" name="titleSeparator" value="' . esc_attr($cfg['titleSeparator']) . '" class="small-text" /> Suffix: <input type="text" name="titleSuffix" value="' . esc_attr($cfg['titleSuffix']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Default SEO Title</th><td><input type="text" name="defaultTitle" value="' . esc_attr($cfg['defaultTitle']) . '" class="large-text" /></td></tr>';
        echo '<tr><th>Default Meta Description</th><td><textarea name="defaultMetaDesc" rows="3" class="large-text">' . esc_textarea($cfg['defaultMetaDesc']) . '</textarea></td></tr>';
        echo '<tr><th>Default Keywords</th><td><textarea name="defaultKeywords" rows="2" class="large-text">' . esc_textarea($cfg['defaultKeywords']) . '</textarea></td></tr>';
        echo '<tr><th>Google Verification Code</th><td><input type="text" name="googleVerify" value="' . esc_attr($cfg['googleVerify']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Bing Verification Code</th><td><input type="text" name="bingVerify" value="' . esc_attr($cfg['bingVerify']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>IndexNow Key</th><td><input type="text" name="indexNowKey" value="' . esc_attr($cfg['indexNowKey']) . '" class="regular-text" /></td></tr>';
        echo '<tr><th>Trailing Slash Policy</th><td><select name="trailingSlash"><option value="no"' . selected($cfg['trailingSlash'], 'no', false) . '>No trailing slash (/path)</option><option value="yes"' . selected($cfg['trailingSlash'], 'yes', false) . '>Trailing slash (/path/)</option></select></td></tr>';
        echo '</tbody></table>';
        submit_button('Save Global SEO Settings');
        echo '</form></div>';
    }
}
