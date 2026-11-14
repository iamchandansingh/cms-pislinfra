<?php
/**
 * Configures CORS, HTTP security headers, draft REST access controls,
 * Application Password availability in local dev, and REST response caching.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Security_CORS {
    const CACHE_GROUP_OPTION = 'pisl_cache_version';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_cors_headers'], 15);
        add_action('send_headers', [__CLASS__, 'send_security_headers']);
        add_filter('wp_is_application_passwords_available', [__CLASS__, 'allow_app_passwords_in_dev']);

        // Flush REST API transient cache automatically whenever content changes
        add_action('save_post', [__CLASS__, 'bump_cache_version']);
        add_action('deleted_post', [__CLASS__, 'bump_cache_version']);
        add_action('trashed_post', [__CLASS__, 'bump_cache_version']);
        add_action('untrashed_post', [__CLASS__, 'bump_cache_version']);
        add_action('add_attachment', [__CLASS__, 'bump_cache_version']);
        add_action('edit_attachment', [__CLASS__, 'bump_cache_version']);
        add_action('updated_option', [__CLASS__, 'maybe_bump_on_option'], 10, 1);

        // Block unauthenticated user enumeration on /wp/v2/users*
        add_filter('rest_pre_dispatch', [__CLASS__, 'restrict_sensitive_wp_endpoints'], 10, 3);

        // Protect unpublished/draft posts in standard /wp/v2 REST queries from anonymous users
        add_filter('rest_post_query', [__CLASS__, 'enforce_published_only_for_anon'], 10, 2);
        foreach ([
            'ongoing_project', 'completed_project', 'case_study', 'award',
            'client', 'client_appreciation', 'coverage_marker', 'job_opening',
            'solution_page', 'policy_document', 'milestone', 'press_release'
        ] as $pt) {
            add_filter("rest_{$pt}_query", [__CLASS__, 'enforce_published_only_for_anon'], 10, 2);
        }
    }

    public static function allow_app_passwords_in_dev(bool $available): bool {
        if ($available) return true;
        $home = home_url();
        if (str_contains($home, 'localhost') || str_contains($home, '127.0.0.1')) {
            return true;
        }
        return $available;
    }

    public static function get_allowed_origins(): array {
        $raw = defined('PISL_CORS_ORIGINS') ? PISL_CORS_ORIGINS : 'http://localhost:5173,http://127.0.0.1:5173,http://localhost:4173,https://pislinfra.com,https://www.pislinfra.com';
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function register_cors_headers(): void {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function ($value) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN']))) : '';
            $allowed = self::get_allowed_origins();
            if ($origin !== '' && (in_array($origin, $allowed, true) || in_array('*', $allowed, true))) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Vary: Origin');
            }
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, Cache-Control, Pragma');
            return $value;
        });
    }

    public static function send_security_headers(): void {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    public static function restrict_sensitive_wp_endpoints($result, $server, \WP_REST_Request $request) {
        if ($result !== null) {
            return $result;
        }
        $route = $request->get_route();
        if (str_starts_with($route, '/wp/v2/users') && (!is_user_logged_in() || !current_user_can('list_users'))) {
            return new \WP_Error(
                'rest_forbidden',
                'Authentication is required to access user endpoints.',
                ['status' => is_user_logged_in() ? 403 : 401]
            );
        }
        return $result;
    }

    public static function enforce_published_only_for_anon(array $args, \WP_REST_Request $request): array {
        if (!is_user_logged_in() || !current_user_can('edit_posts')) {
            $args['post_status'] = 'publish';
        }
        return $args;
    }

    public static function get_cache_version(): int {
        $v = (int) get_option(self::CACHE_GROUP_OPTION, 1);
        return $v > 0 ? $v : 1;
    }

    public static function bump_cache_version(): void {
        $v = self::get_cache_version() + 1;
        update_option(self::CACHE_GROUP_OPTION, $v, true);
    }

    public static function maybe_bump_on_option(string $option): void {
        if (str_starts_with($option, 'pisl_single_')) {
            self::bump_cache_version();
        }
    }

    public static function get_cached_response(string $key) {
        if (is_user_logged_in() || isset($_GET['fresh'])) {
            return false;
        }
        $tkey = 'pisl_api_' . self::get_cache_version() . '_' . md5($key);
        return get_transient($tkey);
    }

    public static function set_cached_response(string $key, $data, int $ttl = 120): void {
        if (is_user_logged_in() || isset($_GET['fresh'])) {
            return;
        }
        $settings = get_option('pisl_integration_settings', []);
        if (is_array($settings) && isset($settings['apiCacheTtlSeconds'])) {
            $ttl = max(0, (int) $settings['apiCacheTtlSeconds']);
        }
        if ($ttl <= 0) {
            return;
        }
        $tkey = 'pisl_api_' . self::get_cache_version() . '_' . md5($key);
        set_transient($tkey, $data, $ttl);
    }
}
