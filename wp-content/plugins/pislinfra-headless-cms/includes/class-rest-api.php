<?php
/**
 * Custom WordPress REST API Controller (/wp-json/pislinfra/v1/*)
 * Delivers Strapi-compatible JSON envelopes ({ data, meta }) directly from
 * WordPress Posts, Custom Post Types, Taxonomies, Options (Single Types), and Media Library.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_REST_API {
    const NAMESPACE = 'pislinfra/v1';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('rest_api_init', [__CLASS__, 'register_wp_v2_fields']);
        add_filter('rest_pre_serve_request', [__CLASS__, 'serve_raw_seo_responses'], 20, 4);
    }

    public static function serve_raw_seo_responses(bool $served, $result, \WP_REST_Request $request, \WP_REST_Server $server): bool {
        if ($served || !($result instanceof \WP_REST_Response)) {
            return $served;
        }
        $headers = $result->get_headers();
        if (!empty($headers['X-PISL-Raw-Type'])) {
            header_remove('X-PISL-Raw-Type');
            header('Content-Type: ' . $headers['X-PISL-Raw-Type'], true, $result->get_status());
            echo (string) $result->get_data();
            return true;
        }
        return $served;
    }

    public static function register_wp_v2_fields(): void {
        $cpts = array_keys(PISL_Schemas::get_cpt_schemas());
        foreach ($cpts as $pt) {
            register_rest_field($pt, 'pisl_data', [
                'get_callback' => function ($object) use ($pt) {
                    $post = get_post((int) ($object['id'] ?? 0));
                    if (!$post) return null;
                    return self::format_cpt_item($post, PISL_Schemas::get_cpt_schemas()[$pt]);
                },
                'schema' => null,
            ]);
        }
    }

    public static function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/health', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_health'],
            'permission_callback' => '__return_true',
        ]);

        // Public SEO configuration, XML Sitemap, Robots.txt & 404 monitor endpoints
        register_rest_route(self::NAMESPACE, '/seo-config', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_seo_config'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/sitemap.xml', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_sitemap_xml'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/robots', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_robots_txt'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NAMESPACE, '/log-404', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_log_404'],
            'permission_callback' => '__return_true',
        ]);

        // Ongoing -> Completed workflow endpoints (Authenticated editors/admins only)
        register_rest_route(self::NAMESPACE, '/ongoing-projects/move-to-completed', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_move_to_completed'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);
        register_rest_route(self::NAMESPACE, '/ongoing-projects/(?P<id>[a-zA-Z0-9_-]+)/move-to-completed', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_move_to_completed'],
            'permission_callback' => fn() => current_user_can('edit_posts'),
        ]);

        // Collection CPT routes (+ compatibility aliases)
        $cpt_schemas = PISL_Schemas::get_cpt_schemas();
        foreach ($cpt_schemas as $post_type => $schema) {
            $slugs = [$schema['rest_slug']];
            if ($post_type === 'press_release') {
                $slugs[] = 'press-release';
            }
            if ($post_type === 'milestone') {
                $slugs[] = 'timeline-milestones';
            }

            foreach ($slugs as $rest_slug) {
                register_rest_route(self::NAMESPACE, '/' . $rest_slug, [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => function (\WP_REST_Request $req) use ($post_type, $schema) {
                        return self::handle_collection_list($req, $post_type, $schema);
                    },
                    'permission_callback' => '__return_true',
                ]);
                register_rest_route(self::NAMESPACE, '/' . $rest_slug . '/(?P<id>[a-zA-Z0-9_-]+)', [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => function (\WP_REST_Request $req) use ($post_type, $schema) {
                        return self::handle_collection_single($req, $post_type, $schema);
                    },
                    'permission_callback' => '__return_true',
                ]);
            }
        }

        // Single-Type Page & Settings routes (+ compatibility aliases)
        $single_types = PISL_Schemas::get_single_types();
        foreach ($single_types as $slug => $cfg) {
            $route_aliases = [$slug];
            if ($slug === 'governance-page') {
                $route_aliases[] = 'governance';
            }
            foreach ($route_aliases as $rslug) {
                register_rest_route(self::NAMESPACE, '/' . $rslug, [
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => function (\WP_REST_Request $req) use ($slug, $cfg) {
                        return self::handle_single_type($req, $slug, $cfg);
                    },
                    'permission_callback' => '__return_true',
                ]);
            }
        }
    }

    public static function handle_health(): \WP_REST_Response {
        global $wpdb;
        $db_ok = (int) $wpdb->get_var('SELECT 1') === 1;
        $counts = [];
        foreach (PISL_Schemas::get_cpt_schemas() as $pt => $cfg) {
            $c = wp_count_posts($pt);
            $counts[$cfg['rest_slug']] = (int) ($c->publish ?? 0);
        }
        return new \WP_REST_Response([
            'ok'           => $db_ok,
            'status'       => $db_ok ? 'ok' : 'degraded',
            'cms'          => 'wordpress',
            'version'      => PISL_CMS_VERSION,
            'cacheVersion' => PISL_Security_CORS::get_cache_version(),
            'counts'       => $counts,
        ], $db_ok ? 200 : 500);
    }

    public static function handle_move_to_completed(\WP_REST_Request $req): \WP_REST_Response {
        $raw_id = $req->get_param('id');
        if (empty($raw_id)) {
            $body = $req->get_json_params();
            $raw_id = $body['id'] ?? $body['documentId'] ?? '';
        }
        $post = self::resolve_post_by_identifier('ongoing_project', (string) $raw_id, true);
        if (!$post) {
            return new \WP_REST_Response(['error' => 'Ongoing project not found'], 404);
        }

        $res = PISL_Workflows::move_ongoing_to_completed((int) $post->ID);
        if (is_wp_error($res)) {
            return new \WP_REST_Response(['error' => $res->get_error_message()], 400);
        }
        $completed_post = get_post((int) $res);
        $schema = PISL_Schemas::get_cpt_schemas()['completed_project'];
        return new \WP_REST_Response([
            'ok'   => true,
            'data' => $completed_post ? self::format_cpt_item($completed_post, $schema) : ['id' => (int) $res],
        ], 200);
    }

    public static function handle_collection_list(\WP_REST_Request $req, string $post_type, array $schema): \WP_REST_Response {
        $params = $req->get_query_params();
        $cache_key = 'list:' . $post_type . ':' . wp_json_encode($params);
        $cached = PISL_Security_CORS::get_cached_response($cache_key);
        if ($cached !== false) {
            return new \WP_REST_Response($cached, 200);
        }

        $can_view_drafts = is_user_logged_in() && current_user_can('edit_posts');
        $req_status = strtolower((string) ($params['status'] ?? 'published'));
        $statuses = ($can_view_drafts && $req_status === 'draft') ? ['draft', 'pending', 'future', 'publish'] : ['publish'];

        $posts = get_posts([
            'post_type'        => $post_type,
            'post_status'      => $statuses,
            'posts_per_page'   => -1,
            'orderby'          => ['date' => 'DESC', 'ID' => 'DESC'],
            'suppress_filters' => false,
        ]);

        $items = [];
        foreach ($posts as $post) {
            $formatted = self::format_cpt_item($post, $schema);
            if (!$can_view_drafts && isset($formatted['status']) && strtolower((string) $formatted['status']) === 'draft') {
                continue;
            }
            $items[] = $formatted;
        }

        // Apply Strapi-compatible filters
        $filters = isset($params['filters']) && is_array($params['filters']) ? $params['filters'] : [];
        if (!empty($filters)) {
            $items = array_values(array_filter($items, fn($row) => self::matches_filters($row, $filters)));
        }

        // Apply Strapi-compatible sorting (default order:asc where schema has order, else publishDate/createdAt)
        $sort = $params['sort'] ?? null;
        if (!empty($sort)) {
            $sort_clauses = is_array($sort) ? array_values($sort) : [$sort];
            usort($items, function ($a, $b) use ($sort_clauses) {
                foreach ($sort_clauses as $clause) {
                    [$field, $dir] = array_pad(explode(':', (string) $clause, 2), 2, 'asc');
                    $dir = strtolower($dir) === 'desc' ? -1 : 1;
                    $va = $a[$field] ?? '';
                    $vb = $b[$field] ?? '';
                    if (is_numeric($va) && is_numeric($vb)) {
                        $cmp = ((float) $va) <=> ((float) $vb);
                    } else {
                        $cmp = strcmp((string) $va, (string) $vb);
                    }
                    if ($cmp !== 0) return $cmp * $dir;
                }
                return 0;
            });
        }

        $total = count($items);
        $pag = isset($params['pagination']) && is_array($params['pagination']) ? $params['pagination'] : [];
        $limit = isset($pag['limit']) ? (int) $pag['limit'] : 0;
        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
            $page = 1;
            $page_size = $limit;
        } else {
            $page = max(1, (int) ($pag['page'] ?? 1));
            $page_size = max(1, min(500, (int) ($pag['pageSize'] ?? 100)));
            $items = array_slice($items, ($page - 1) * $page_size, $page_size);
        }

        $page_count = $page_size > 0 ? (int) max(1, ceil($total / $page_size)) : 1;
        $payload = [
            'data' => array_values($items),
            'meta' => [
                'pagination' => [
                    'page'      => $page,
                    'pageSize'  => $page_size,
                    'pageCount' => $page_count,
                    'total'     => $total,
                ],
            ],
        ];

        // Special legacy PressRelease envelope compat if single item queried without array expectation
        PISL_Security_CORS::set_cached_response($cache_key, $payload);
        return new \WP_REST_Response($payload, 200);
    }

    public static function handle_collection_single(\WP_REST_Request $req, string $post_type, array $schema): \WP_REST_Response {
        $id = (string) $req->get_param('id');
        $can_view_drafts = is_user_logged_in() && current_user_can('edit_posts');
        $post = self::resolve_post_by_identifier($post_type, $id, $can_view_drafts);
        if (!$post) {
            return new \WP_REST_Response(['data' => null, 'error' => ['status' => 404, 'message' => 'Not Found']], 404);
        }
        $item = self::format_cpt_item($post, $schema);
        if (!$can_view_drafts && isset($item['status']) && strtolower((string) $item['status']) === 'draft') {
            return new \WP_REST_Response(['data' => null, 'error' => ['status' => 404, 'message' => 'Not Found']], 404);
        }
        return new \WP_REST_Response(['data' => $item, 'meta' => (object) []], 200);
    }

    public static function handle_single_type(\WP_REST_Request $req, string $slug, array $cfg): \WP_REST_Response {
        $cache_key = 'single:' . $slug;
        $cached = PISL_Security_CORS::get_cached_response($cache_key);
        if ($cached !== false) {
            return new \WP_REST_Response($cached, 200);
        }

        $stored = get_option('pisl_single_' . $slug, []);
        if (!is_array($stored)) $stored = [];

        $status = $stored['_status'] ?? 'publish';
        if ($status === 'draft' && !(is_user_logged_in() && current_user_can('edit_posts'))) {
            return new \WP_REST_Response(['data' => null, 'meta' => (object) []], 404);
        }

        $out = self::build_single_type_item($slug, $cfg, $stored);
        $payload = ['data' => $out, 'meta' => (object) []];
        PISL_Security_CORS::set_cached_response($cache_key, $payload);
        return new \WP_REST_Response($payload, 200);
    }

    public static function build_single_type_item(string $slug, array $cfg, array $stored): array {
        $status = $stored['_status'] ?? 'publish';
        $out = [
            'id'          => (int) ($stored['_id'] ?? 1),
            'documentId'  => (string) ($stored['_documentId'] ?? ('wp_single_' . $slug)),
            'createdAt'   => (string) ($stored['_createdAt'] ?? gmdate('c')),
            'updatedAt'   => (string) ($stored['_updatedAt'] ?? gmdate('c')),
            'publishedAt' => $status === 'publish' ? (string) ($stored['_publishedAt'] ?? gmdate('c')) : null,
        ];

        foreach ($cfg['fields'] as $field) {
            $k = $field['key'];
            if (array_key_exists($k, $stored)) {
                $raw = $stored[$k];
            } elseif (($field['type'] ?? '') === 'checkbox' && array_key_exists('default', $field)) {
                $raw = (bool) $field['default'];
            } else {
                $raw = null;
            }
            $out[$k] = self::hydrate_field_value($field, $raw);
        }

        if (!empty($cfg['has_seo'])) {
            $out['seo'] = self::hydrate_seo($stored['seo'] ?? []);
        }

        // Single-Type domain enrichments matching Strapi controllers
        if ($slug === 'ehs-page' && !empty($out['resources']) && is_array($out['resources'])) {
            $out['resources'] = PISL_Workflows::normalize_ehs_resources($out['resources']);
        }
        if ($slug === 'governance-page') {
            $out['slug'] = PISL_Workflows::validate_governance_slug((string) ($out['slug'] ?? 'company-policy'));
            if (empty($out['policies'])) {
                $out['policies'] = self::get_embedded_policy_documents();
            }
        }
        if ($slug === 'case-study-page' && empty($out['trustedClients'])) {
            $clients_posts = get_posts([
                'post_type'      => 'client',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ]);
            $client_schema = PISL_Schemas::get_cpt_schemas()['client'];
            $trusted = array_map(fn($p) => self::format_cpt_item($p, $client_schema), $clients_posts);
            usort($trusted, fn($a, $b) => ((int) ($a['order'] ?? 0)) <=> ((int) ($b['order'] ?? 0)));
            $out['trustedClients'] = $trusted;
        }

        return $out;
    }

    private static function get_embedded_policy_documents(): array {
        $posts = get_posts([
            'post_type'      => 'policy_document',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);
        $schema = PISL_Schemas::get_cpt_schemas()['policy_document'];
        $items = array_map(fn($p) => self::format_cpt_item($p, $schema), $posts);
        usort($items, fn($a, $b) => ((int) ($a['displayOrder'] ?? 0)) <=> ((int) ($b['displayOrder'] ?? 0)));
        return $items;
    }

    public static function format_cpt_item(\WP_Post $post, array $schema): array {
        $fields = get_post_meta($post->ID, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];

        $doc_id = (string) get_post_meta($post->ID, '_pisl_document_id', true);
        if ($doc_id === '') {
            $doc_id = 'wp_' . $post->post_type . '_' . $post->ID;
        }

        $out = [
            'id'          => (int) $post->ID,
            'documentId'  => $doc_id,
            'createdAt'   => mysql2date('c', $post->post_date_gmt ?: $post->post_date, false),
            'updatedAt'   => mysql2date('c', $post->post_modified_gmt ?: $post->post_modified, false),
            'publishedAt' => $post->post_status === 'publish' ? mysql2date('c', $post->post_date_gmt ?: $post->post_date, false) : null,
        ];

        if ($post->post_type === 'post') {
            $out['title']   = $post->post_title;
            $out['slug']    = $post->post_name !== '' ? $post->post_name : (string) $post->ID;
            $raw_content    = (string) $post->post_content;
            $raw_content    = str_replace(['https://cms.pislinfra.com', 'http://cms.pislinfra.com'], 'https://pislinfra.com/cms', $raw_content);
            if (strpos($raw_content, '<!-- wp:') !== false) {
                $raw_content = function_exists('do_blocks') ? do_blocks($raw_content) : $raw_content;
                $raw_content = preg_replace('/<!--\s*\/?wp:[\s\S]*?-->/', '', $raw_content);
                $raw_content = preg_replace('/<p(?:\s[^>]*)?>\s*(?:&nbsp;|<br\s*\/?>|\s)*<\/p>/i', '', (string) $raw_content);
                $raw_content = trim((string) $raw_content);
            }
            $out['content'] = $raw_content;
            $out['status']  = !empty($fields['statusField'])
                ? strtolower((string) $fields['statusField'])
                : ($post->post_status === 'publish' ? 'published' : 'draft');
        } else {
            $out['slug'] = !empty($fields['slug']) ? $fields['slug'] : ($post->post_name !== '' ? $post->post_name : (string) $post->ID);
            if (!isset($fields['title']) && !isset($fields['name']) && !isset($fields['projectName']) && !isset($fields['headline'])) {
                $out['title'] = $post->post_title;
            }
        }

        foreach ($schema['fields'] as $field) {
            $k = $field['key'];
            if (array_key_exists($k, $fields)) {
                $raw = $fields[$k];
            } elseif (($field['type'] ?? '') === 'checkbox' && array_key_exists('default', $field)) {
                $raw = (bool) $field['default'];
            } else {
                $raw = null;
            }
            $out[$k] = self::hydrate_field_value($field, $raw);
        }

        // Fallbacks when WP core thumbnail or post taxonomies are set in WP Admin
        if (array_key_exists('featuredImage', $out) && empty($out['featuredImage'])) {
            $fallback_img = ($post->post_type === 'post')
                ? PISL_Media_Helper::resolve_post_featured_image($post)
                : (has_post_thumbnail($post->ID) ? (int) get_post_thumbnail_id($post->ID) : null);
            if (!empty($fallback_img)) {
                $out['featuredImage'] = PISL_Media_Helper::format_media($fallback_img);
            }
        }
        if ($post->post_type === 'post') {
            if (empty($out['publishDate'])) {
                $out['publishDate'] = substr((string) $post->post_date, 0, 10);
            }
            if (empty($out['author'])) {
                $out['author'] = 'PISL Team';
            }
        }
        if ($post->post_type === 'client') {
            if (empty($out['logo']) && has_post_thumbnail($post->ID)) {
                $out['logo'] = PISL_Media_Helper::format_media((int) get_post_thumbnail_id($post->ID));
            }
            // Always synchronize logoUrl with resolved WP Media Library asset so stale URLs never survive logo removal/replacement
            $out['logoUrl'] = !empty($out['logo']['url']) ? $out['logo']['url'] : null;
        }
        if ($post->post_type === 'post') {
            if (empty($out['category'])) {
                $cats = wp_get_post_terms($post->ID, 'category', ['fields' => 'names']);
                if (!is_wp_error($cats) && !empty($cats)) {
                    $out['category'] = $cats[0];
                }
            }
            if (empty($out['tags'])) {
                $tags = wp_get_post_terms($post->ID, 'post_tag', ['fields' => 'names']);
                if (!is_wp_error($tags) && !empty($tags)) {
                    $out['tags'] = implode(', ', $tags);
                }
            }
        }
        if (in_array($post->post_type, ['ongoing_project', 'completed_project', 'case_study'], true) && empty($out['category'])) {
            $terms = wp_get_post_terms($post->ID, 'project_category', ['fields' => 'names']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $out['category'] = $terms[0];
            }
        }
        if ($post->post_type === 'award') {
            if (empty($out['title'])) {
                $out['title'] = $post->post_title;
            }
            if (empty($out['year'])) {
                $out['year'] = substr((string) $post->post_date, 0, 4) ?: gmdate('Y');
            }
            if (empty($out['category'])) {
                $terms = wp_get_post_terms($post->ID, 'award_category', ['fields' => 'names']);
                if (!is_wp_error($terms) && !empty($terms)) {
                    $out['category'] = $terms[0];
                }
            }
            if (empty($out['images'])) {
                $fallback_award_img = PISL_Media_Helper::resolve_post_featured_image($post);
                if (!empty($fallback_award_img)) {
                    $formatted_img = PISL_Media_Helper::format_media($fallback_award_img);
                    if ($formatted_img) {
                        $out['images'] = [$formatted_img];
                    }
                }
            }
            if (!empty($out['images']) && is_array($out['images']) && empty($out['image'])) {
                $out['image'] = $out['images'][0];
            }
        }
        if ($post->post_type === 'case_study') {
            if (empty($out['title'])) {
                $out['title'] = $post->post_title;
            }
            if (empty($out['image'])) {
                $fallback_cs_img = has_post_thumbnail($post->ID)
                    ? (int) get_post_thumbnail_id($post->ID)
                    : ($out['engagementImage'] ?? $out['challengesImage'] ?? $out['achievementsImage'] ?? PISL_Media_Helper::resolve_post_featured_image($post));
                if (!empty($fallback_cs_img)) {
                    $out['image'] = PISL_Media_Helper::format_media($fallback_cs_img);
                }
            }
        }
        if (in_array($post->post_type, ['client', 'client_appreciation'], true) && empty($out['category'])) {
            $terms = wp_get_post_terms($post->ID, 'client_category', ['fields' => 'names']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $out['category'] = $terms[0];
            }
        }
        if ($post->post_type === 'policy_document' && empty($out['category'])) {
            $terms = wp_get_post_terms($post->ID, 'policy_category', ['fields' => 'names']);
            if (!is_wp_error($terms) && !empty($terms)) {
                $out['category'] = $terms[0];
            }
        }
        if ($post->post_type === 'job_opening') {
            if (empty($out['title'])) {
                $out['title'] = $post->post_title;
            }
            if (empty($out['datePosted'])) {
                $out['datePosted'] = substr((string) $post->post_date, 0, 10);
            }
            if (empty($out['description']) && !empty($post->post_content)) {
                $out['description'] = trim(wp_strip_all_tags((string) $post->post_content));
            }
        }

        if (!empty($schema['has_seo'])) {
            $seo = get_post_meta($post->ID, '_pisl_seo', true);
            $out['seo'] = self::hydrate_seo(is_array($seo) ? $seo : []);
        }

        return $out;
    }

    private static function hydrate_field_value(array $field, $raw) {
        $type = $field['type'];
        switch ($type) {
            case 'media':
                return PISL_Media_Helper::format_media($raw);
            case 'gallery':
                return PISL_Media_Helper::format_media_list($raw);
            case 'checkbox':
                return (bool) $raw;
            case 'number':
                return ($raw === null || $raw === '') ? null : (int) $raw;
            case 'number_float':
                return ($raw === null || $raw === '') ? null : (float) $raw;
            case 'resource_single':
                if (!is_array($raw)) return $raw;
                if (isset($raw['file']))       $raw['file'] = PISL_Media_Helper::format_media($raw['file']);
                if (isset($raw['coverImage'])) $raw['coverImage'] = PISL_Media_Helper::format_media($raw['coverImage']);
                return $raw;
            case 'repeater':
                if (!is_array($raw)) return [];
                $subfields = $field['subfields'] ?? [];
                $rows = [];
                foreach ($raw as $row) {
                    if (!is_array($row)) continue;
                    $hydrated_row = $row;
                    foreach ($subfields as $sf) {
                        $sk = $sf['key'];
                        $hydrated_row[$sk] = self::hydrate_field_value($sf, $row[$sk] ?? null);
                    }
                    $rows[] = $hydrated_row;
                }
                return $rows;
            default:
                return $raw;
        }
    }

    private static function hydrate_seo(array $seo): array {
        if (empty($seo)) {
            return [];
        }
        $out = $seo;
        // Keep Strapi/React aliases mutually synchronized
        if (empty($out['seoTitle']) && !empty($out['metaTitle'])) {
            $out['seoTitle'] = $out['metaTitle'];
        } elseif (empty($out['metaTitle']) && !empty($out['seoTitle'])) {
            $out['metaTitle'] = $out['seoTitle'];
        }
        if (empty($out['seoDescription']) && !empty($out['metaDescription'])) {
            $out['seoDescription'] = $out['metaDescription'];
        } elseif (empty($out['metaDescription']) && !empty($out['seoDescription'])) {
            $out['metaDescription'] = $out['seoDescription'];
        }
        if (empty($out['focusKeyword']) && !empty($out['primaryKeyword'])) {
            $out['focusKeyword'] = $out['primaryKeyword'];
        } elseif (empty($out['primaryKeyword']) && !empty($out['focusKeyword'])) {
            $out['primaryKeyword'] = $out['focusKeyword'];
        }
        if (empty($out['seoKeywords']) && !empty($out['secondaryKeywords'])) {
            $out['seoKeywords'] = $out['secondaryKeywords'];
        }
        if (isset($out['ogImage'])) {
            $out['ogImage'] = PISL_Media_Helper::format_media($out['ogImage']);
        }
        if (isset($out['twitterImage'])) {
            $out['twitterImage'] = PISL_Media_Helper::format_media($out['twitterImage']);
        }
        if (isset($out['noIndex']))  $out['noIndex'] = (bool) $out['noIndex'];
        if (isset($out['noFollow'])) $out['noFollow'] = (bool) $out['noFollow'];
        if (isset($out['breadcrumbSchema']))   $out['breadcrumbSchema'] = (bool) $out['breadcrumbSchema'];
        if (isset($out['organizationSchema'])) $out['organizationSchema'] = (bool) $out['organizationSchema'];
        return $out;
    }

    public static function resolve_post_by_identifier(string $post_type, string $identifier, bool $include_drafts): ?\WP_Post {
        $statuses = $include_drafts ? ['publish', 'draft', 'pending', 'future'] : ['publish'];
        if (ctype_digit($identifier)) {
            $p = get_post((int) $identifier);
            if ($p && $p->post_type === $post_type && in_array($p->post_status, $statuses, true)) {
                return $p;
            }
        }
        $by_doc = get_posts([
            'post_type'      => $post_type,
            'post_status'    => $statuses,
            'meta_key'       => '_pisl_document_id',
            'meta_value'     => sanitize_text_field($identifier),
            'posts_per_page' => 1,
        ]);
        if (!empty($by_doc)) {
            return $by_doc[0];
        }
        $by_slug = get_posts([
            'post_type'      => $post_type,
            'post_status'    => $statuses,
            'name'           => sanitize_title($identifier),
            'posts_per_page' => 1,
        ]);
        if (!empty($by_slug)) {
            return $by_slug[0];
        }
        return null;
    }

    public static function handle_seo_config(\WP_REST_Request $req): \WP_REST_Response {
        $global    = PISL_Admin_SEO::get_global_settings();
        $schema    = PISL_Admin_SEO::get_schema_settings();
        $social    = PISL_Admin_SEO::get_social_settings();
        $robots    = PISL_Admin_SEO::get_robots_settings();
        $redirects = array_values(array_filter(
            PISL_Admin_SEO::get_redirects(),
            fn($r) => !empty($r['active']) && !empty($r['source']) && !empty($r['target'])
        ));
        $og_img_id = $social['defaultOgImage'] ?? ($social['default_og_image'] ?? null);
        $og_img = !empty($og_img_id) ? PISL_Media_Helper::format_media((int) $og_img_id) : null;
        $canonical_base = rtrim((string) ($global['frontendBaseUrl'] ?? ($global['canonical_base_url'] ?? 'https://pislinfra.com')), '/');

        return new \WP_REST_Response([
            'data' => [
                'siteName'           => $schema['orgAltName'] ?? ($global['site_name'] ?? 'Pislinfra'),
                'titleSeparator'     => $global['titleSeparator'] ?? ($global['title_separator'] ?? '|'),
                'defaultTitleSuffix' => $global['titleSuffix'] ?? ($global['default_title_suffix'] ?? 'Pislinfra | Industrial Infrastructure & EPC Company India'),
                'defaultTitle'       => $global['defaultTitle'] ?? '',
                'defaultDescription' => $global['defaultMetaDesc'] ?? ($global['default_description'] ?? ''),
                'canonicalBaseUrl'   => $canonical_base,
                'globalNoIndex'      => !empty($robots['globalNoIndex']) || !empty($global['global_noindex']),
                'globalNoFollow'     => !empty($robots['globalNoFollow']) || !empty($global['global_nofollow']),
                'schema'             => array_merge($schema, [
                    'org_name' => $schema['orgName'] ?? 'Pragati Infra Solutions Pvt. Ltd.',
                    'org_url'  => $schema['orgUrl'] ?? $canonical_base,
                    'org_logo' => $schema['orgLogo'] ?? ($canonical_base . '/logo.png'),
                ]),
                'robots'             => $robots,
                'social'             => [
                    'ogSiteName'     => $social['ogSiteName'] ?? ($social['og_site_name'] ?? 'Pislinfra'),
                    'twitterCard'    => $social['twitterCardType'] ?? ($social['twitter_card'] ?? 'summary_large_image'),
                    'twitterSite'    => $social['twitterHandle'] ?? ($social['twitter_site'] ?? '@Pislinfra'),
                    'defaultOgImage' => $og_img['url'] ?? ($canonical_base . '/logo.png'),
                ],
                'redirects'          => $redirects,
                'cacheVersion'       => PISL_Security_CORS::get_cache_version(),
            ],
            'meta' => new \stdClass(),
        ], 200);
    }

    public static function handle_sitemap_xml(\WP_REST_Request $req): \WP_REST_Response {
        $xml = PISL_Admin_SEO::generate_sitemap_xml();
        $res = new \WP_REST_Response($xml, 200);
        $res->header('X-PISL-Raw-Type', 'application/xml; charset=utf-8');
        return $res;
    }

    public static function handle_robots_txt(\WP_REST_Request $req): \WP_REST_Response {
        $robots = PISL_Admin_SEO::get_robots_settings();
        $content = (string) ($robots['robotsTxtContent'] ?? ($robots['robots_txt'] ?? "User-agent: *\nAllow: /\nSitemap: https://pislinfra.com/sitemap.xml\n"));
        $res = new \WP_REST_Response($content, 200);
        $res->header('X-PISL-Raw-Type', 'text/plain; charset=utf-8');
        return $res;
    }

    public static function handle_log_404(\WP_REST_Request $req): \WP_REST_Response {
        $params = $req->get_json_params();
        if (!is_array($params)) {
            $params = $req->get_params();
        }
        $path     = sanitize_text_field((string) ($params['path'] ?? ''));
        $referrer = esc_url_raw((string) ($params['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? '')));
        $ua       = sanitize_text_field((string) ($params['userAgent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
        if ($path !== '') {
            PISL_Admin_SEO::log_404_hit($path, $referrer, $ua, 'react');
        }
        return new \WP_REST_Response(['ok' => true], 200);
    }

    private static function matches_filters(array $row, array $filters): bool {
        foreach ($filters as $field => $cond) {
            $actual = $row[$field] ?? null;
            if (is_array($cond)) {
                if (isset($cond['$eq'])) {
                    $target = $cond['$eq'];
                    if (is_bool($actual)) {
                        $boolTarget = filter_var($target, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                        if ($boolTarget !== null && $actual !== $boolTarget) return false;
                    } elseif ((string) $actual !== (string) $target) {
                        return false;
                    }
                }
                if (isset($cond['$ne']) && (string) $actual === (string) $cond['$ne']) {
                    return false;
                }
                if (isset($cond['$contains']) && !str_contains(strtolower((string) $actual), strtolower((string) $cond['$contains']))) {
                    return false;
                }
            } else {
                if ((string) $actual !== (string) $cond) {
                    return false;
                }
            }
        }
        return true;
    }
}
