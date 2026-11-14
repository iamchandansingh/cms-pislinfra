<?php
/**
 * Replicates custom Strapi lifecycles and workflows in WordPress:
 * 1. Ongoing Project -> Completed Project promotion (manual button, REST action, or status change)
 * 2. Project <-> Coverage Marker synchronization + coordinate lookup table
 * 3. Blog auto-slug, default date/author, SEO/OG/Twitter image & meta fallbacks
 * 4. Governance reserved-slug validation and Policy Document PDF validation
 * 5. EHS page resource filtering & canonical ordering
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Workflows {
    private static bool $syncing = false;

    const RESERVED_ABOUT_SLUGS = ['leadership', 'ehs', 'awards', 'csr'];

    const LOCATIONS = [
        'mumbai'      => [272, 608],
        'pune'        => [292, 628],
        'nagpur'      => [455, 512],
        'nashik'      => [282, 560],
        'delhi'       => [350, 275],
        'ncr'         => [360, 282],
        'noida'       => [366, 282],
        'greater noida'=> [370, 286],
        'gurugram'    => [342, 288],
        'gurgaon'     => [342, 288],
        'faridabad'   => [358, 290],
        'manesar'     => [335, 295],
        'bhiwadi'     => [328, 302],
        'pataudi'     => [322, 296],
        'sampka'      => [328, 292],
        'tauru'       => [338, 298],
        'bilaspur'    => [330, 296],
        'billaspur'   => [330, 296],
        'farrukhnagar'=> [332, 285],
        'patli'       => [336, 290],
        'jamalpur'    => [324, 298],
        'dharuhera'   => [318, 305],
        'jhamuwas'    => [340, 308],
        'sohna'       => [350, 296],
        'neemrana'    => [302, 315],
        'khijuri'     => [310, 310],
        'bengaluru'   => [388, 822],
        'bangalore'   => [388, 822],
        'hoskote'     => [402, 818],
        'kolathur'    => [392, 816],
        'chennai'     => [505, 818],
        'mappedu'     => [495, 818],
        'coimbatore'  => [372, 888],
        'thoothukudi' => [405, 962],
        'tuticorin'   => [405, 962],
        'hyderabad'   => [435, 650],
        'ahmedabad'   => [192, 472],
        'vadodara'    => [218, 502],
        'surat'       => [215, 535],
        'hazira'      => [205, 535],
        'mundra'      => [98,  482],
        'jamnagar'    => [118, 505],
        'dahej'       => [198, 518],
        'sanand'      => [178, 472],
        'jaipur'      => [285, 342],
        'bhiwandi'    => [272, 608],
        'kolkata'     => [735, 488],
        'indore'      => [335, 485],
        'ludhiana'    => [292, 210],
        'lucknow'     => [495, 340],
        'najibabad'   => [395, 232],
        'dehradun'    => [375, 200],
        'guwahati'    => [860, 315],
    ];

    const STATE_FALLBACKS = [
        'maharashtra'    => [320, 600],
        'karnataka'      => [360, 780],
        'tamil nadu'     => [430, 880],
        'telangana'      => [440, 640],
        'andhra pradesh' => [480, 720],
        'gujarat'        => [175, 485],
        'rajasthan'      => [240, 360],
        'haryana'        => [320, 270],
        'delhi'          => [350, 275],
        'uttar pradesh'  => [480, 340],
        'madhya pradesh' => [400, 460],
        'west bengal'    => [720, 460],
        'punjab'         => [290, 210],
        'odisha'         => [620, 560],
        'kerala'         => [340, 920],
        'bihar'          => [620, 380],
        'assam'          => [860, 320],
    ];

    public static function init(): void {
        // Run after save_post (priority 10) metabox writes so Gutenberg metabox payloads never erase workflow defaults
        add_action('save_post', [__CLASS__, 'dispatch_save_post_workflows'], 30, 3);
    }

    public static function dispatch_save_post_workflows(int $post_id, \WP_Post $post, bool $update): void {
        switch ($post->post_type) {
            case 'post':
                self::on_save_blog($post_id, $post, $update);
                break;
            case 'ongoing_project':
                self::on_save_ongoing_project($post_id, $post, $update);
                break;
            case 'completed_project':
                self::on_save_completed_project($post_id, $post, $update);
                break;
            case 'coverage_marker':
                self::on_save_coverage_marker($post_id, $post, $update);
                break;
            case 'policy_document':
                self::on_save_policy_document($post_id, $post, $update);
                break;
            case 'award':
                self::on_save_award($post_id, $post, $update);
                break;
            case 'case_study':
                self::on_save_case_study($post_id, $post, $update);
                break;
        }
    }

    public static function lookup_coords(string $city, string $state): array {
        $normCity  = strtolower(trim($city));
        $normState = strtolower(trim($state));

        foreach (self::LOCATIONS as $k => $coords) {
            if ($normCity !== '' && ($normCity === $k || str_contains($normCity, $k))) {
                return ['cx' => $coords[0], 'cy' => $coords[1], 'matched' => true];
            }
        }
        foreach (self::STATE_FALLBACKS as $k => $coords) {
            if ($normState !== '' && ($normState === $k || str_contains($normState, $k))) {
                return ['cx' => $coords[0], 'cy' => $coords[1], 'matched' => false];
            }
        }
        return ['cx' => null, 'cy' => null, 'matched' => false];
    }

    public static function on_save_blog(int $post_id, \WP_Post $post, bool $update): void {
        if (self::$syncing || wp_is_post_revision($post_id) || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ($post->post_status === 'auto-draft' || $post->post_status === 'trash') {
            return;
        }
        self::$syncing = true;

        $title = $post->post_title;
        if (empty($post->post_name) && !empty($title)) {
            wp_update_post([
                'ID'        => $post_id,
                'post_name' => sanitize_title($title),
            ]);
        }

        $fields = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];

        if (empty($fields['publishDate'])) {
            $post_ymd = substr((string) $post->post_date, 0, 10);
            $fields['publishDate'] = ($post_ymd && $post_ymd !== '0000-00-00') ? $post_ymd : gmdate('Y-m-d');
        }
        if (empty($fields['author'])) {
            $fields['author'] = 'PISL Team';
        }
        // Keep headless statusField synchronized with WordPress publish/draft transitions
        $fields['statusField'] = ($post->post_status === 'publish') ? 'published' : 'draft';
        if (has_post_thumbnail($post_id)) {
            $fields['featuredImage'] = (int) get_post_thumbnail_id($post_id);
        } elseif (!empty($fields['featuredImage']) && is_numeric($fields['featuredImage'])) {
            set_post_thumbnail($post_id, (int) $fields['featuredImage']);
        } else {
            $resolved_img = PISL_Media_Helper::resolve_post_featured_image($post);
            if (!empty($resolved_img)) {
                $fields['featuredImage'] = $resolved_img;
                if (is_numeric($resolved_img) && (int) $resolved_img > 0) {
                    set_post_thumbnail($post_id, (int) $resolved_img);
                }
            }
        }
        update_post_meta($post_id, '_pisl_fields', $fields);

        // SEO fallbacks matching Strapi blog lifecycle
        $seo = get_post_meta($post_id, '_pisl_seo', true);
        if (!is_array($seo)) $seo = [];
        $excerpt = !empty($fields['excerpt']) ? $fields['excerpt'] : wp_trim_words(wp_strip_all_tags($post->post_content), 28);

        if (empty($seo['seoTitle']) && !empty($title)) {
            $seo['seoTitle'] = $title . ' | Pislinfra';
        }
        if (empty($seo['seoDescription']) && !empty($excerpt)) {
            $seo['seoDescription'] = substr($excerpt, 0, 160);
        }
        if (empty($seo['ogTitle']) && !empty($seo['seoTitle'])) {
            $seo['ogTitle'] = $seo['seoTitle'];
        }
        if (empty($seo['ogDescription']) && !empty($seo['seoDescription'])) {
            $seo['ogDescription'] = $seo['seoDescription'];
        }
        if (empty($seo['twitterTitle']) && !empty($seo['seoTitle'])) {
            $seo['twitterTitle'] = $seo['seoTitle'];
        }
        if (empty($seo['twitterDescription']) && !empty($seo['seoDescription'])) {
            $seo['twitterDescription'] = $seo['seoDescription'];
        }
        if (!empty($fields['featuredImage']) && is_numeric($fields['featuredImage'])) {
            if (empty($seo['ogImage'])) $seo['ogImage'] = (int) $fields['featuredImage'];
            if (empty($seo['twitterImage'])) $seo['twitterImage'] = (int) $fields['featuredImage'];
        }
        update_post_meta($post_id, '_pisl_seo', $seo);

        self::$syncing = false;
    }

    public static function on_save_award(int $post_id, \WP_Post $post, bool $update): void {
        if (self::$syncing || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return;
        }

        $fields = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];

        if (empty($fields['title']) && !empty($post->post_title)) {
            $fields['title'] = $post->post_title;
        }
        if (empty($fields['year'])) {
            $post_year = substr((string) $post->post_date, 0, 4);
            $fields['year'] = ($post_year && $post_year !== '0000') ? $post_year : gmdate('Y');
        }

        $gallery = is_array($fields['images'] ?? null) ? array_values(array_filter($fields['images'])) : [];
        $first_img_id = 0;
        foreach ($gallery as $g_item) {
            if (is_numeric($g_item) && (int) $g_item > 0) {
                $first_img_id = (int) $g_item;
                break;
            }
        }

        if ($first_img_id > 0 && !has_post_thumbnail($post_id)) {
            set_post_thumbnail($post_id, $first_img_id);
        } elseif (empty($gallery) && has_post_thumbnail($post_id)) {
            $thumb_id = (int) get_post_thumbnail_id($post_id);
            $fields['images'] = [$thumb_id];
            $first_img_id = $thumb_id;
        } elseif (empty($gallery)) {
            $resolved_img = PISL_Media_Helper::resolve_post_featured_image($post);
            if (!empty($resolved_img)) {
                $fields['images'] = [$resolved_img];
                if (is_numeric($resolved_img) && (int) $resolved_img > 0) {
                    set_post_thumbnail($post_id, (int) $resolved_img);
                    $first_img_id = (int) $resolved_img;
                }
            }
        }

        update_post_meta($post_id, '_pisl_fields', $fields);

        $seo = get_post_meta($post_id, '_pisl_seo', true);
        if (!is_array($seo)) $seo = [];
        $award_title = (string) ($fields['title'] ?? $post->post_title);
        $award_desc  = !empty($fields['desc']) ? (string) $fields['desc'] : wp_trim_words(wp_strip_all_tags($post->post_content), 28);

        if (empty($seo['seoTitle']) && $award_title !== '') {
            $seo['seoTitle'] = $award_title . ' | Pislinfra';
        }
        if (empty($seo['seoDescription']) && $award_desc !== '') {
            $seo['seoDescription'] = substr($award_desc, 0, 160);
        }
        if (empty($seo['ogTitle']) && !empty($seo['seoTitle'])) {
            $seo['ogTitle'] = $seo['seoTitle'];
        }
        if (empty($seo['ogDescription']) && !empty($seo['seoDescription'])) {
            $seo['ogDescription'] = $seo['seoDescription'];
        }
        if (empty($seo['twitterTitle']) && !empty($seo['seoTitle'])) {
            $seo['twitterTitle'] = $seo['seoTitle'];
        }
        if (empty($seo['twitterDescription']) && !empty($seo['seoDescription'])) {
            $seo['twitterDescription'] = $seo['seoDescription'];
        }
        if ($first_img_id > 0) {
            if (empty($seo['ogImage'])) $seo['ogImage'] = $first_img_id;
            if (empty($seo['twitterImage'])) $seo['twitterImage'] = $first_img_id;
        }
        update_post_meta($post_id, '_pisl_seo', $seo);
    }

    public static function on_save_case_study(int $post_id, \WP_Post $post, bool $update): void {
        if (self::$syncing || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return;
        }

        $fields = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];

        if (empty($fields['title']) && !empty($post->post_title)) {
            $fields['title'] = $post->post_title;
        }
        if (empty($fields['slug'])) {
            $fields['slug'] = sanitize_title((string) ($fields['title'] ?: $post->post_title ?: $post_id));
        }

        $cover_id = 0;
        if (has_post_thumbnail($post_id)) {
            $cover_id = (int) get_post_thumbnail_id($post_id);
            $fields['image'] = $cover_id;
        } elseif (!empty($fields['image']) && is_numeric($fields['image']) && (int) $fields['image'] > 0) {
            $cover_id = (int) $fields['image'];
            set_post_thumbnail($post_id, $cover_id);
        } else {
            $fallback = null;
            foreach (['engagementImage', 'challengesImage', 'achievementsImage'] as $alt_key) {
                if (!empty($fields[$alt_key])) {
                    $fallback = $fields[$alt_key];
                    break;
                }
            }
            if (empty($fallback)) {
                $fallback = PISL_Media_Helper::resolve_post_featured_image($post);
            }
            if (!empty($fallback)) {
                $fields['image'] = $fallback;
                if (is_numeric($fallback) && (int) $fallback > 0) {
                    $cover_id = (int) $fallback;
                    set_post_thumbnail($post_id, $cover_id);
                }
            }
        }

        update_post_meta($post_id, '_pisl_fields', $fields);

        $seo = get_post_meta($post_id, '_pisl_seo', true);
        if (!is_array($seo)) $seo = [];
        $cs_title = (string) ($fields['title'] ?? $post->post_title);
        $cs_desc  = !empty($fields['introduction']) ? (string) $fields['introduction'] : (string) ($fields['overview'] ?? '');

        if (empty($seo['seoTitle']) && $cs_title !== '') {
            $seo['seoTitle'] = $cs_title . ' Case Study | Pislinfra';
        }
        if (empty($seo['seoDescription']) && $cs_desc !== '') {
            $seo['seoDescription'] = substr(wp_strip_all_tags($cs_desc), 0, 160);
        }
        if (empty($seo['ogTitle']) && !empty($seo['seoTitle'])) {
            $seo['ogTitle'] = $seo['seoTitle'];
        }
        if (empty($seo['ogDescription']) && !empty($seo['seoDescription'])) {
            $seo['ogDescription'] = $seo['seoDescription'];
        }
        if (empty($seo['twitterTitle']) && !empty($seo['seoTitle'])) {
            $seo['twitterTitle'] = $seo['seoTitle'];
        }
        if (empty($seo['twitterDescription']) && !empty($seo['seoDescription'])) {
            $seo['twitterDescription'] = $seo['seoDescription'];
        }
        if ($cover_id > 0) {
            if (empty($seo['ogImage'])) $seo['ogImage'] = $cover_id;
            if (empty($seo['twitterImage'])) $seo['twitterImage'] = $cover_id;
        }
        update_post_meta($post_id, '_pisl_seo', $seo);
    }

    public static function on_save_ongoing_project(int $post_id, \WP_Post $post, bool $update): void {
        if (self::$syncing || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return;
        }

        $fields = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];
        $name = !empty($fields['name']) ? $fields['name'] : $post->post_title;
        if (empty($fields['name']) && !empty($name)) {
            $fields['name'] = $name;
            update_post_meta($post_id, '_pisl_fields', $fields);
        }

        if (isset($fields['projectStatus']) && $fields['projectStatus'] === 'Completed') {
            self::move_ongoing_to_completed($post_id);
            return;
        }

        if ($post->post_status === 'publish') {
            self::sync_marker_from_project($post_id, 'Ongoing', $fields);
        }
    }

    public static function on_save_completed_project(int $post_id, \WP_Post $post, bool $update): void {
        if (self::$syncing || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            return;
        }

        $fields = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];
        $name = !empty($fields['name']) ? $fields['name'] : $post->post_title;
        if (empty($fields['name']) && !empty($name)) {
            $fields['name'] = $name;
            update_post_meta($post_id, '_pisl_fields', $fields);
        }

        if ($post->post_status === 'publish') {
            self::sync_marker_from_project($post_id, 'Completed', $fields);
        }
    }

    public static function sync_marker_from_project(int $project_post_id, string $project_type, array $fields): void {
        $post = get_post($project_post_id);
        if (!$post) return;

        $project_name = trim((string) ($fields['name'] ?? $post->post_title));
        if ($project_name === '') return;

        $city  = trim((string) ($fields['location'] ?? ''));
        $state = trim((string) ($fields['state'] ?? ''));
        $imgs  = $fields['images'] ?? [];

        self::$syncing = true;

        $marker_id = self::find_marker_id_by_name_and_city($project_name, $city);
        if ($marker_id) {
            $mfields = get_post_meta($marker_id, '_pisl_fields', true);
            if (!is_array($mfields)) $mfields = [];
            $mfields['projectName'] = $project_name;
            $mfields['projectType'] = $project_type;
            if ($city !== '')  $mfields['city'] = $city;
            if ($state !== '') $mfields['state'] = $state;
            if (empty($mfields['cx']) || empty($mfields['cy'])) {
                $coords = self::lookup_coords($city, $state);
                if ($coords['cx'] !== null) $mfields['cx'] = $coords['cx'];
                if ($coords['cy'] !== null) $mfields['cy'] = $coords['cy'];
            }
            if (!empty($imgs)) $mfields['images'] = $imgs;
            update_post_meta($marker_id, '_pisl_fields', $mfields);
        } else {
            $coords = self::lookup_coords($city, $state);
            $new_id = wp_insert_post([
                'post_type'   => 'coverage_marker',
                'post_title'  => $project_name,
                'post_status' => 'publish',
            ]);
            if ($new_id && !is_wp_error($new_id)) {
                update_post_meta($new_id, '_pisl_fields', [
                    'projectName'  => $project_name,
                    'state'        => $state,
                    'city'         => $city,
                    'projectType'  => $project_type,
                    'cx'           => $coords['cx'],
                    'cy'           => $coords['cy'],
                    'showOnMap'    => false, // Pending alignment until editor reviews pin
                    'markerStatus' => 'Pending Alignment',
                    'images'       => $imgs,
                ]);
            }
        }

        self::$syncing = false;
    }

    public static function find_marker_id_by_name_and_city(string $name, string $city = ''): int {
        $markers = get_posts([
            'post_type'      => 'coverage_marker',
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $normName = strtolower(trim($name));
        $normCity = strtolower(trim($city));
        foreach ($markers as $mid) {
            $mf = get_post_meta($mid, '_pisl_fields', true);
            $mName = strtolower(trim((string) ($mf['projectName'] ?? get_the_title($mid))));
            $mCity = strtolower(trim((string) ($mf['city'] ?? '')));
            if ($mName === $normName) {
                if ($normCity === '' || $mCity === '' || $mCity === $normCity) {
                    return (int) $mid;
                }
            }
        }
        return 0;
    }

    /**
     * Promotes an ongoing_project CPT post to completed_project and removes the ongoing record.
     */
    public static function move_ongoing_to_completed(int $ongoing_id) {
        $post = get_post($ongoing_id);
        if (!$post || $post->post_type !== 'ongoing_project') {
            return new \WP_Error('not_found', 'Ongoing project not found.', ['status' => 404]);
        }

        self::$syncing = true;

        $fields = get_post_meta($ongoing_id, '_pisl_fields', true);
        if (!is_array($fields)) $fields = [];
        $seo = get_post_meta($ongoing_id, '_pisl_seo', true);
        if (!is_array($seo)) $seo = [];

        $fields['projectStatus'] = 'Completed';
        if (empty($fields['name'])) {
            $fields['name'] = $post->post_title;
        }

        $completed_id = wp_insert_post([
            'post_type'   => 'completed_project',
            'post_title'  => $fields['name'],
            'post_name'   => sanitize_title($fields['name']),
            'post_status' => 'publish',
        ]);
        if (is_wp_error($completed_id)) {
            self::$syncing = false;
            return $completed_id;
        }

        update_post_meta($completed_id, '_pisl_fields', $fields);
        if (!empty($seo)) {
            update_post_meta($completed_id, '_pisl_seo', $seo);
        }
        if (has_post_thumbnail($ongoing_id)) {
            set_post_thumbnail($completed_id, (int) get_post_thumbnail_id($ongoing_id));
        }

        // Copy project categories & states taxonomies
        foreach (['project_category', 'project_state'] as $tax) {
            $terms = wp_get_post_terms($ongoing_id, $tax, ['fields' => 'ids']);
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_post_terms($completed_id, $terms, $tax);
            }
        }

        $marker_id = self::find_marker_id_by_name_and_city(
            (string) $fields['name'],
            (string) ($fields['location'] ?? '')
        );
        if ($marker_id) {
            $mf = get_post_meta($marker_id, '_pisl_fields', true);
            if (!is_array($mf)) $mf = [];
            $mf['projectType'] = 'Completed';
            update_post_meta($marker_id, '_pisl_fields', $mf);
        }

        wp_trash_post($ongoing_id);
        PISL_Security_CORS::bump_cache_version();

        self::$syncing = false;
        return $completed_id;
    }

    public static function on_save_coverage_marker(int $post_id, \WP_Post $post, bool $update): void {
        if (self::$syncing || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        if ($post->post_status !== 'publish') return;

        $mf = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($mf) || empty($mf['projectType']) || $mf['projectType'] !== 'Completed') {
            return;
        }

        $mName = strtolower(trim((string) ($mf['projectName'] ?? $post->post_title)));
        if ($mName === '') return;

        $ongoing = get_posts([
            'post_type'      => 'ongoing_project',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        foreach ($ongoing as $oid) {
            $of = get_post_meta($oid, '_pisl_fields', true);
            $oName = strtolower(trim((string) ($of['name'] ?? get_the_title($oid))));
            if ($oName === $mName) {
                self::move_ongoing_to_completed((int) $oid);
                break;
            }
        }
    }

    public static function on_save_policy_document(int $post_id, \WP_Post $post, bool $update): void {
        if (self::$syncing || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }
        $fields = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($fields)) return;

        // Validate PDF magic header when an attachment is attached as pdfFile
        if (!empty($fields['pdfFile']) && is_numeric($fields['pdfFile'])) {
            $file_path = get_attached_file((int) $fields['pdfFile']);
            if ($file_path && file_exists($file_path)) {
                $fh = @fopen($file_path, 'rb');
                if ($fh) {
                    $header = fread($fh, 5);
                    fclose($fh);
                    if ($header !== '%PDF-') {
                        set_transient('pisl_admin_notice_' . get_current_user_id(), [
                            'type' => 'error',
                            'text' => 'Policy Document validation warning: attached file does not contain a valid %PDF- header.',
                        ], 30);
                    }
                }
            }
        }
    }

    /**
     * Enforces reserved-slug rule on Governance / Company Policy single type.
     */
    public static function validate_governance_slug(string $slug): string {
        $clean = sanitize_title($slug ?: 'company-policy');
        if (in_array($clean, self::RESERVED_ABOUT_SLUGS, true)) {
            return 'company-policy';
        }
        return $clean;
    }

    /**
     * Filters out PISL Profile and orders EHS resources: HSE, Risk, Vigil, CSR.
     */
    public static function normalize_ehs_resources(array $resources): array {
        $filtered = array_values(array_filter($resources, function ($item) {
            $t = strtolower(trim((string) ($item['title'] ?? '')));
            return !str_contains($t, 'pisl profile');
        }));

        $rank = function ($item): int {
            $t = strtolower((string) ($item['title'] ?? ''));
            if (str_contains($t, 'hse') || str_contains($t, 'health')) return 1;
            if (str_contains($t, 'risk')) return 2;
            if (str_contains($t, 'vigil')) return 3;
            if (str_contains($t, 'csr')) return 4;
            return 99;
        };

        usort($filtered, fn($a, $b) => $rank($a) <=> $rank($b));
        return $filtered;
    }
}
