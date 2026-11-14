<?php
/**
 * Signed Draft Preview & WordPress Admin Session Bridge
 *
 * Security model:
 * - Zero draft leakage for anonymous/unauthenticated requests.
 * - Preview links use HMAC-SHA256 signatures tied to wp_salt('auth') and short TTL (1 hour).
 * - /admin-session validates WP logged_in auth cookie and returns edit deep-links + wp_rest nonce
 *   ONLY to allowlisted CORS origins for authenticated editors/admins.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Preview_Auth {
    const PREVIEW_TTL_SECONDS = 3600;

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(PISL_REST_API::NAMESPACE, '/admin-session', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_admin_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(PISL_REST_API::NAMESPACE, '/preview', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_preview'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(PISL_REST_API::NAMESPACE, '/cache/bump', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_cache_bump'],
            'permission_callback' => function (\WP_REST_Request $req) {
                $nonce = (string) ($req->get_header('x_wp_nonce') ?: ($_SERVER['HTTP_X_WP_NONCE'] ?? ''));
                if (is_user_logged_in() && current_user_can('edit_posts')) {
                    if (!empty($_COOKIE[LOGGED_IN_COOKIE]) && !wp_verify_nonce($nonce, 'wp_rest')) {
                        return false;
                    }
                    return true;
                }
                if (self::authenticate_cookie_user() > 0 && current_user_can('edit_posts')) {
                    return (bool) wp_verify_nonce($nonce, 'wp_rest');
                }
                return false;
            },
        ]);
    }

    /**
     * Allows read-only cookie authentication on explicit session/preview endpoints
     * when Origin is either empty (same-origin) or in PISL_CORS_ORIGINS.
     */
    public static function authenticate_cookie_user(): int {
        if (is_user_logged_in() && current_user_can('edit_posts')) {
            return get_current_user_id();
        }
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN']))) : '';
        if ($origin !== '') {
            $allowed = PISL_Security_CORS::get_allowed_origins();
            if (!in_array($origin, $allowed, true) && !in_array('*', $allowed, true)) {
                return 0;
            }
        }
        $uid = wp_validate_auth_cookie('', 'logged_in');
        if ($uid && user_can((int) $uid, 'edit_posts')) {
            wp_set_current_user((int) $uid);
            return (int) $uid;
        }
        return 0;
    }

    public static function sign_payload(string $type, string $identifier, int $exp): string {
        $payload = $type . '|' . $identifier . '|' . $exp;
        return hash_hmac('sha256', $payload, wp_salt('auth'));
    }

    public static function verify_token(string $type, string $identifier, int $exp, string $sig): bool {
        if ($type === '' || $identifier === '' || $exp <= 0 || $sig === '') {
            return false;
        }
        if ($exp < time()) {
            return false;
        }
        $expected = self::sign_payload($type, $identifier, $exp);
        return hash_equals($expected, $sig);
    }

    public static function build_preview_url(string $type, string $identifier, string $route = ''): string {
        $frontend = defined('PISL_FRONTEND_URL') ? rtrim(PISL_FRONTEND_URL, '/') : 'http://localhost:5173';
        $target_route = $route !== '' ? $route : '/';
        if (!str_starts_with($target_route, '/')) {
            $target_route = '/' . $target_route;
        }
        $exp = time() + self::PREVIEW_TTL_SECONDS;
        $sig = self::sign_payload($type, $identifier, $exp);
        $sep = str_contains($target_route, '?') ? '&' : '?';
        return $frontend . $target_route . $sep . http_build_query([
            'pisl_preview' => '1',
            'pisl_type'    => $type,
            'pisl_id'      => $identifier,
            'pisl_exp'     => $exp,
            'pisl_sig'     => $sig,
        ]);
    }

    public static function get_frontend_route_for_single_type(string $slug): string {
        $map = [
            'home-page'          => '/',
            'about-page'         => '/about',
            'leadership-page'    => '/about/leadership',
            'ehs-page'           => '/about/ehs',
            'csr-page'           => '/about/csr',
            'awards-page'        => '/about/awards',
            'ongoing-page'       => '/projects/ongoing',
            'completed-page'     => '/projects/completed',
            'case-study-page'    => '/projects/case-study',
            'warehouse-page'     => '/solutions/warehouse',
            'blog-page'          => '/blog',
            'career-page'        => '/careers',
            'annual-report-page' => '/annual-reports',
            'contact-page'       => '/contact-us',
            'policy-page'        => '/privacy-policy',
            'top-header'         => '/',
            'header'             => '/',
            'footer'             => '/',
        ];
        if (isset($map[$slug])) {
            return $map[$slug];
        }
        if ($slug === 'governance-page') {
            $stored = get_option('pisl_single_governance-page', []);
            $gov_slug = is_array($stored) ? ($stored['slug'] ?? 'policies-governance') : 'policies-governance';
            $gov_slug = PISL_Workflows::validate_governance_slug((string) $gov_slug);
            return '/about/' . ($gov_slug ?: 'policies-governance');
        }
        return '/';
    }

    public static function get_frontend_route_for_post(\WP_Post $post): string {
        $pt = $post->post_type;
        $fields = get_post_meta($post->ID, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];
        $doc_id = (string) (get_post_meta($post->ID, '_pisl_document_id', true) ?: ('wp_' . $post->ID));

        if ($pt === 'post') {
            $slug = (string) ($fields['slug'] ?? $post->post_name ?: $post->ID);
            return '/blog/' . rawurlencode($slug);
        }
        if ($pt === 'ongoing_project' || $pt === 'completed_project') {
            $slug = (string) ($fields['slug'] ?? '');
            if ($slug === '' && !empty($post->post_name)) {
                $slug = (string) $post->post_name;
            }
            $segment = $pt === 'completed_project' ? 'completed' : 'ongoing';
            return '/project/' . $segment . '/' . rawurlencode($slug !== '' ? $slug : $doc_id);
        }
        if ($pt === 'case_study') {
            $slug = (string) ($fields['slug'] ?? $post->post_name ?: '');
            return '/projects/case-study/' . rawurlencode($slug !== '' ? $slug : $doc_id);
        }
        if ($pt === 'solution_page') {
            $slug = (string) ($fields['slug'] ?? $post->post_name ?: '');
            if ($slug === '' || $slug === 'solutions') {
                return '/solutions';
            }
            return '/solutions/' . rawurlencode($slug);
        }
        $pt_map = [
            'case_study'          => '/projects/case-study',
            'award'               => '/about/awards',
            'milestone'           => '/about/awards',
            'client_appreciation' => '/about/awards',
            'client'              => '/',
            'coverage_marker'     => '/',
            'job_opening'         => '/careers',
            'policy_document'     => '/about/policies-governance',
            'press_release'       => '/',
        ];
        return $pt_map[$pt] ?? '/';
    }

    public static function get_rest_type_for_post_type(string $post_type): string {
        if ($post_type === 'post') return 'blogs';
        $cpts = PISL_Schemas::get_cpt_schemas();
        if (isset($cpts[$post_type]['rest_slug'])) {
            return $cpts[$post_type]['rest_slug'];
        }
        return $post_type;
    }

    public static function handle_admin_session(\WP_REST_Request $req): \WP_REST_Response {
        self::send_no_cache_headers();
        $uid = self::authenticate_cookie_user();
        if (!$uid || !current_user_can('edit_posts')) {
            return new \WP_REST_Response(['authenticated' => false], 200);
        }

        $user = get_userdata($uid);
        $route = (string) ($req->get_param('route') ?? '/');
        $resolved = self::resolve_edit_target_for_route($route);

        return new \WP_REST_Response([
            'authenticated' => true,
            'user'          => [
                'id'    => $uid,
                'name'  => $user ? $user->display_name : 'Editor',
                'roles' => $user ? array_values($user->roles) : [],
            ],
            'adminBaseUrl'  => admin_url(),
            'wpRestNonce'   => wp_create_nonce('wp_rest'),
            'cacheVersion'  => PISL_Security_CORS::get_cache_version(),
            'editTarget'    => $resolved,
        ], 200);
    }

    public static function handle_preview(\WP_REST_Request $req): \WP_REST_Response {
        self::send_no_cache_headers();
        $type = sanitize_key((string) ($req->get_param('type') ?? ''));
        $id   = sanitize_text_field((string) ($req->get_param('id') ?? ''));
        $exp  = (int) $req->get_param('exp');
        $sig  = sanitize_text_field((string) ($req->get_param('sig') ?? ''));

        $uid = self::authenticate_cookie_user();
        $has_cookie_auth = ($uid > 0 && current_user_can('edit_posts'));
        $has_hmac_auth   = self::verify_token($type, $id, $exp, $sig);

        if (!$has_hmac_auth && !$has_cookie_auth) {
            return new \WP_REST_Response([
                'data'  => null,
                'error' => ['status' => 403, 'message' => 'Invalid or expired preview token'],
            ], 403);
        }

        // Single Types
        $single_types = PISL_Schemas::get_single_types();
        if (isset($single_types[$type])) {
            $stored = get_option('pisl_single_' . $type, []);
            if (!is_array($stored)) $stored = [];
            $preview_source = $stored;
            if (!empty($stored['_has_draft']) && !empty($stored['_draft_data']) && is_array($stored['_draft_data'])) {
                $preview_source = array_merge($stored, $stored['_draft_data']);
            }
            $out = PISL_REST_API::build_single_type_item($type, $single_types[$type], $preview_source);
            return new \WP_REST_Response([
                'data'    => $out,
                'preview' => true,
                'type'    => $type,
                'status'  => !empty($stored['_has_draft']) ? 'draft' : (string) ($stored['_status'] ?? 'publish'),
                'editUrl' => admin_url('admin.php?page=pisl-single-' . $type),
                'meta'    => (object) [],
            ], 200);
        }

        // Collections (match by rest_slug OR WP post_type)
        $cpts = PISL_Schemas::get_cpt_schemas();
        $target_pt = null;
        $target_schema = null;
        foreach ($cpts as $pt => $cfg) {
            if ($pt === $type || ($cfg['rest_slug'] ?? '') === $type || str_replace('-', '_', $type) === $pt) {
                $target_pt = $pt;
                $target_schema = $cfg;
                break;
            }
        }
        if ($target_pt === null) {
            return new \WP_REST_Response([
                'data'  => null,
                'error' => ['status' => 404, 'message' => 'Unknown preview content type'],
            ], 404);
        }

        $post = PISL_REST_API::resolve_post_by_identifier($target_pt, $id, true);
        if (!$post || $post->post_status === 'trash') {
            return new \WP_REST_Response([
                'data'  => null,
                'error' => ['status' => 404, 'message' => 'Preview entry not found'],
            ], 404);
        }

        $formatted = PISL_REST_API::format_cpt_item($post, $target_schema);
        return new \WP_REST_Response([
            'data'    => $formatted,
            'preview' => true,
            'type'    => $type,
            'status'  => $post->post_status,
            'editUrl' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
            'meta'    => (object) [],
        ], 200);
    }

    public static function handle_cache_bump(): \WP_REST_Response {
        self::send_no_cache_headers();
        PISL_Security_CORS::bump_cache_version();
        return new \WP_REST_Response([
            'ok'           => true,
            'cacheVersion' => PISL_Security_CORS::get_cache_version(),
        ], 200);
    }

    private static function resolve_edit_target_for_route(string $raw_route): array {
        $path = parse_url($raw_route, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        // Dynamic blog detail route: /blog/:slug
        if (preg_match('#^/blog/([^/]+)$#', $path, $m)) {
            $slug = rawurldecode($m[1]);
            $post = PISL_REST_API::resolve_post_by_identifier('post', $slug, true);
            if ($post) {
                return [
                    'label'     => 'Edit Blog Post',
                    'editUrl'   => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                    'listUrl'   => admin_url('edit.php'),
                    'postType'  => 'post',
                ];
            }
            return [
                'label'   => 'Manage Blog Posts',
                'editUrl' => admin_url('edit.php'),
                'listUrl' => admin_url('edit.php'),
            ];
        }

        // Dynamic project detail route: /project/:type/:id (plus legacy /projects/:id fallback)
        if (preg_match('#^/project/(ongoing|completed)/([^/]+)$#', $path, $pm)) {
            $pt = $pm[1] === 'completed' ? 'completed_project' : 'ongoing_project';
            $ident = rawurldecode($pm[2]);
            $post = PISL_REST_API::resolve_post_by_identifier($pt, $ident, true);
            if ($post) {
                return [
                    'label'    => $pt === 'ongoing_project' ? 'Edit Ongoing Project' : 'Edit Completed Project',
                    'editUrl'  => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                    'listUrl'  => admin_url('edit.php?post_type=' . $pt),
                    'postType' => $pt,
                ];
            }
        }
        if (preg_match('#^/projects/([^/]+)$#', $path, $m) && !in_array($m[1], ['ongoing', 'completed'], true)) {
            $ident = rawurldecode($m[1]);
            foreach (['ongoing_project', 'completed_project'] as $pt) {
                $post = PISL_REST_API::resolve_post_by_identifier($pt, $ident, true);
                if ($post) {
                    return [
                        'label'   => $pt === 'ongoing_project' ? 'Edit Ongoing Project' : 'Edit Completed Project',
                        'editUrl' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                        'listUrl' => admin_url('edit.php?post_type=' . $pt),
                        'postType'=> $pt,
                    ];
                }
            }
        }

        // Dynamic case study detail route: /projects/case-study/:slug
        if (preg_match('#^/projects/case-study/([^/]+)$#', $path, $cm)) {
            $ident = rawurldecode($cm[1]);
            $post = PISL_REST_API::resolve_post_by_identifier('case_study', $ident, true);
            if ($post) {
                return [
                    'label'    => 'Edit Case Study',
                    'editUrl'  => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                    'listUrl'  => admin_url('edit.php?post_type=case_study'),
                    'postType' => 'case_study',
                ];
            }
        }

        // Solution CPT routes: /solutions, /solutions/industrial, /solutions/infrastructure, /solutions/logistic, /solutions/warehouse
        $sol_slugs = [
            '/solutions'                => 'solutions',
            '/solutions/industrial'     => 'industrial',
            '/solutions/infrastructure' => 'infrastructure',
            '/solutions/logistic'       => 'logistic',
            '/solutions/warehouse'      => 'warehouse',
        ];
        if (isset($sol_slugs[$path])) {
            $post = PISL_REST_API::resolve_post_by_identifier('solution_page', $sol_slugs[$path], true);
            if ($post) {
                return [
                    'label'   => 'Edit Solution Page',
                    'editUrl' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                    'listUrl' => admin_url('edit.php?post_type=solution_page'),
                ];
            }
        }

        // Static Single Type routes
        $single_map = [
            '/'                          => ['slug' => 'home-page',          'label' => 'Edit Home Page'],
            '/about'                     => ['slug' => 'about-page',         'label' => 'Edit About Overview'],
            '/about/overview'            => ['slug' => 'about-page',         'label' => 'Edit About Overview'],
            '/about/leadership'          => ['slug' => 'leadership-page',    'label' => 'Edit Leadership Page'],
            '/about/ehs'                 => ['slug' => 'ehs-page',           'label' => 'Edit EHS Page'],
            '/about/csr'                 => ['slug' => 'csr-page',           'label' => 'Edit CSR Page'],
            '/about/awards'              => ['slug' => 'awards-page',        'label' => 'Edit Awards Page'],
            '/about/policies-governance' => ['slug' => 'governance-page',    'label' => 'Edit Governance Page'],
            '/about/company-policy'      => ['slug' => 'governance-page',    'label' => 'Edit Governance Page'],
            '/company-policy'            => ['slug' => 'governance-page',    'label' => 'Edit Governance Page'],
            '/policies-governance'       => ['slug' => 'governance-page',    'label' => 'Edit Governance Page'],
            '/projects/ongoing'          => ['slug' => 'ongoing-page',       'label' => 'Edit Ongoing Projects Page', 'listUrl' => admin_url('edit.php?post_type=ongoing_project')],
            '/projects/completed'        => ['slug' => 'completed-page',     'label' => 'Edit Completed Projects Page', 'listUrl' => admin_url('edit.php?post_type=completed_project')],
            '/projects/case-study'       => ['slug' => 'case-study-page',    'label' => 'Edit Case Studies Page', 'listUrl' => admin_url('edit.php?post_type=case_study')],
            '/case-studies'              => ['slug' => 'case-study-page',    'label' => 'Edit Case Studies Page', 'listUrl' => admin_url('edit.php?post_type=case_study')],
            '/blog'                      => ['slug' => 'blog-page',          'label' => 'Edit Blog Landing Page', 'listUrl' => admin_url('edit.php')],
            '/careers'                   => ['slug' => 'career-page',        'label' => 'Edit Careers Page', 'listUrl' => admin_url('edit.php?post_type=job_opening')],
            '/annual-reports'            => ['slug' => 'annual-report-page', 'label' => 'Edit Annual Reports Page'],
            '/contact-us'                => ['slug' => 'contact-page',       'label' => 'Edit Contact Us Page'],
            '/privacy-policy'            => ['slug' => 'policy-page',        'label' => 'Edit Privacy Policy Page'],
        ];
        if (isset($single_map[$path])) {
            $entry = $single_map[$path];
            return [
                'label'   => $entry['label'],
                'editUrl' => admin_url('admin.php?page=pisl-single-' . $entry['slug']),
                'listUrl' => $entry['listUrl'] ?? admin_url(),
            ];
        }

        return [
            'label'   => 'Open WordPress Admin',
            'editUrl' => admin_url(),
            'listUrl' => admin_url(),
        ];
    }

    private static function send_no_cache_headers(): void {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }
}
