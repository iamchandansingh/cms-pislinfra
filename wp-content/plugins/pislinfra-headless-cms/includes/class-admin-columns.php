<?php
/**
 * Custom WordPress Admin list table columns and taxonomy filter dropdowns
 * for all PISL Headless CMS post types.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Admin_Columns {
    public static function init(): void {
        add_action('restrict_manage_posts', [__CLASS__, 'render_taxonomy_filters']);

        add_filter('manage_post_posts_columns', [__CLASS__, 'blog_columns']);
        add_action('manage_post_posts_custom_column', [__CLASS__, 'render_blog_column'], 10, 2);

        foreach (['ongoing_project', 'completed_project'] as $pt) {
            add_filter("manage_{$pt}_posts_columns", [__CLASS__, 'project_columns']);
            add_action("manage_{$pt}_posts_custom_column", [__CLASS__, 'render_project_column'], 10, 2);
        }

        add_filter('manage_case_study_posts_columns', [__CLASS__, 'case_study_columns']);
        add_action('manage_case_study_posts_custom_column', [__CLASS__, 'render_case_study_column'], 10, 2);

        add_filter('manage_award_posts_columns', [__CLASS__, 'award_columns']);
        add_action('manage_award_posts_custom_column', [__CLASS__, 'render_award_column'], 10, 2);

        add_filter('manage_client_posts_columns', [__CLASS__, 'client_columns']);
        add_action('manage_client_posts_custom_column', [__CLASS__, 'render_client_column'], 10, 2);

        add_filter('manage_client_appreciation_posts_columns', [__CLASS__, 'appreciation_columns']);
        add_action('manage_client_appreciation_posts_custom_column', [__CLASS__, 'render_appreciation_column'], 10, 2);

        add_filter('manage_coverage_marker_posts_columns', [__CLASS__, 'coverage_columns']);
        add_action('manage_coverage_marker_posts_custom_column', [__CLASS__, 'render_coverage_column'], 10, 2);

        add_filter('manage_job_opening_posts_columns', [__CLASS__, 'job_columns']);
        add_action('manage_job_opening_posts_custom_column', [__CLASS__, 'render_job_column'], 10, 2);

        add_filter('manage_solution_page_posts_columns', [__CLASS__, 'solution_columns']);
        add_action('manage_solution_page_posts_custom_column', [__CLASS__, 'render_solution_column'], 10, 2);

        add_filter('manage_policy_document_posts_columns', [__CLASS__, 'policy_columns']);
        add_action('manage_policy_document_posts_custom_column', [__CLASS__, 'render_policy_column'], 10, 2);

        add_filter('manage_milestone_posts_columns', [__CLASS__, 'milestone_columns']);
        add_action('manage_milestone_posts_custom_column', [__CLASS__, 'render_milestone_column'], 10, 2);
    }

    public static function render_taxonomy_filters(string $post_type): void {
        $map = [
            'ongoing_project'     => ['project_category', 'project_state'],
            'completed_project'   => ['project_category', 'project_state'],
            'case_study'          => ['project_category'],
            'coverage_marker'     => ['project_state'],
            'award'               => ['award_category'],
            'client'              => ['client_category'],
            'client_appreciation' => ['client_category'],
            'job_opening'         => ['job_department'],
            'policy_document'     => ['policy_category'],
        ];
        if (!isset($map[$post_type])) {
            return;
        }
        foreach ($map[$post_type] as $tax) {
            $obj = get_taxonomy($tax);
            if (!$obj) continue;
            $selected = isset($_GET[$tax]) ? sanitize_text_field(wp_unslash($_GET[$tax])) : '';
            wp_dropdown_categories([
                'show_option_all' => 'All ' . $obj->labels->name,
                'taxonomy'        => $tax,
                'name'            => $tax,
                'orderby'         => 'name',
                'selected'        => $selected,
                'value_field'     => 'slug',
                'hierarchical'    => (bool) $obj->hierarchical,
                'show_count'      => true,
                'hide_empty'      => false,
            ]);
        }
    }

    public static function get_fields(int $post_id): array {
        $f = get_post_meta($post_id, '_pisl_fields', true);
        return is_array($f) ? $f : [];
    }

    public static function render_thumb_html($media_val, int $post_id = 0, bool $as_logo = false): string {
        $url = '';
        $size = $as_logo ? 'full' : 'thumbnail';
        if (is_numeric($media_val) && (int) $media_val > 0) {
            $url = (string) ($as_logo ? wp_get_attachment_url((int) $media_val) : wp_get_attachment_image_url((int) $media_val, $size));
        } elseif (is_string($media_val) && str_starts_with($media_val, 'http')) {
            $url = $media_val;
        }
        if ($url === '' && $post_id > 0 && has_post_thumbnail($post_id)) {
            $url = (string) ($as_logo ? wp_get_attachment_url((int) get_post_thumbnail_id($post_id)) : get_the_post_thumbnail_url($post_id, $size));
        }
        if ($url === '') {
            return $as_logo ? '<span class="pisl-badge pisl-badge-warn">Missing Logo</span>' : '<span class="pisl-empty-pill">—</span>';
        }
        $cls = $as_logo ? 'pisl-col-logo' : 'pisl-col-thumb';
        return '<img src="' . esc_url($url) . '" alt="" class="' . esc_attr($cls) . '" />';
    }

    public static function render_seo_badge(int $post_id, array $fields = []): string {
        $seo = get_post_meta($post_id, '_pisl_seo', true);
        if (!is_array($seo)) $seo = [];
        $title = trim((string) ($seo['seoTitle'] ?? $seo['metaTitle'] ?? $fields['seoTitle'] ?? $fields['metaTitle'] ?? ''));
        $desc  = trim((string) ($seo['seoDescription'] ?? $seo['metaDescription'] ?? $fields['seoDescription'] ?? $fields['metaDescription'] ?? ''));
        if ($title !== '' && $desc !== '') {
            return '<span class="pisl-badge pisl-badge-pub">SEO Ready</span>';
        }
        if ($title !== '' || $desc !== '') {
            return '<span class="pisl-badge pisl-badge-warn">Partial SEO</span>';
        }
        return '<span class="pisl-badge pisl-badge-draft">Missing SEO</span>';
    }

    private static function term_names(int $post_id, string $tax, string $fallback = ''): string {
        $terms = wp_get_post_terms($post_id, $tax, ['fields' => 'names']);
        if (!is_wp_error($terms) && !empty($terms)) {
            return implode(', ', $terms);
        }
        return $fallback !== '' ? $fallback : '—';
    }

    public static function blog_columns(array $cols): array {
        return [
            'cb'         => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb' => 'Image',
            'title'      => 'Post Title',
            'categories' => 'Categories',
            'tags'       => 'Tags',
            'pisl_seo'   => 'SEO Status',
            'date'       => 'Published / Date',
        ];
    }

    public static function render_blog_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        if ($col === 'pisl_thumb') echo self::render_thumb_html($f['featuredImage'] ?? 0, $post_id);
        if ($col === 'pisl_seo')   echo self::render_seo_badge($post_id, $f);
    }

    public static function project_columns(array $cols): array {
        return [
            'cb'            => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb'    => 'Image',
            'title'         => 'Project Name',
            'pisl_category' => 'Category',
            'pisl_client'   => 'Client',
            'pisl_location' => 'Location / State',
            'pisl_gallery'  => 'Gallery',
            'pisl_seo'      => 'SEO',
            'date'          => 'Date',
        ];
    }

    public static function render_project_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        $imgs = is_array($f['images'] ?? null) ? $f['images'] : [];
        switch ($col) {
            case 'pisl_thumb':
                echo self::render_thumb_html($imgs[0] ?? 0, $post_id);
                break;
            case 'pisl_category':
                echo esc_html(self::term_names($post_id, 'project_category', (string) ($f['category'] ?? '')));
                break;
            case 'pisl_client':
                echo esc_html((string) ($f['client'] ?? '—'));
                break;
            case 'pisl_location':
                $loc = trim((string) ($f['location'] ?? ''));
                $st  = self::term_names($post_id, 'project_state', (string) ($f['state'] ?? ''));
                echo esc_html($loc ?: $st);
                break;
            case 'pisl_gallery':
                echo '<span class="pisl-badge pisl-badge-info">' . count($imgs) . ' photos</span>';
                break;
            case 'pisl_seo':
                echo self::render_seo_badge($post_id, $f);
                break;
        }
    }

    public static function case_study_columns(array $cols): array {
        return [
            'cb'             => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb'     => 'Image',
            'title'          => 'Case Study Title',
            'pisl_developer' => 'Developer / Client',
            'pisl_location'  => 'Location',
            'pisl_category'  => 'Category',
            'pisl_seo'       => 'SEO',
            'date'           => 'Date',
        ];
    }

    public static function render_case_study_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        if ($col === 'pisl_thumb')     echo self::render_thumb_html($f['image'] ?? 0, $post_id);
        if ($col === 'pisl_developer') echo esc_html((string) ($f['developer'] ?? '—'));
        if ($col === 'pisl_location')  echo esc_html((string) ($f['location'] ?? '—'));
        if ($col === 'pisl_category')  echo esc_html(self::term_names($post_id, 'project_category'));
        if ($col === 'pisl_seo')       echo self::render_seo_badge($post_id, $f);
    }

    public static function award_columns(array $cols): array {
        return [
            'cb'            => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb'    => 'Image',
            'title'         => 'Award Title',
            'pisl_company'  => 'Issuing Body / Client',
            'pisl_category' => 'Category',
            'pisl_year'     => 'Year',
            'pisl_order'    => 'Order',
        ];
    }

    public static function render_award_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        $imgs = is_array($f['images'] ?? null) ? $f['images'] : [];
        if ($col === 'pisl_thumb')    echo self::render_thumb_html($f['logo'] ?? ($imgs[0] ?? 0), $post_id);
        if ($col === 'pisl_company')  echo esc_html((string) ($f['company'] ?? '—'));
        if ($col === 'pisl_category') echo esc_html(self::term_names($post_id, 'award_category', (string) ($f['category'] ?? '')));
        if ($col === 'pisl_year')     echo esc_html((string) ($f['year'] ?? '—'));
        if ($col === 'pisl_order')    echo esc_html((string) ($f['order'] ?? '0'));
    }

    public static function client_columns(array $cols): array {
        return [
            'cb'               => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb'       => 'Logo',
            'title'            => 'Client Name',
            'pisl_logo_status' => 'Logo Status',
            'pisl_category'    => 'Category',
            'pisl_website'     => 'Website',
            'pisl_order'       => 'Order',
            'pisl_seo'         => 'SEO',
            'date'             => 'Date',
        ];
    }

    public static function render_client_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        $logo_src = !empty($f['logo']) ? $f['logo'] : ($f['logoUrl'] ?? 0);
        if ($col === 'pisl_thumb') {
            echo self::render_thumb_html($logo_src, $post_id, true);
        }
        if ($col === 'pisl_logo_status') {
            $has_logo = (!empty($f['logo']) || !empty($f['logoUrl']) || has_post_thumbnail($post_id));
            echo $has_logo
                ? '<span class="pisl-badge pisl-badge-pub">Logo Active</span>'
                : '<span class="pisl-badge pisl-badge-warn">Missing Logo</span>';
        }
        if ($col === 'pisl_category') echo esc_html(self::term_names($post_id, 'client_category', (string) ($f['category'] ?? '')));
        if ($col === 'pisl_website') {
            $url = trim((string) ($f['website'] ?? ''));
            echo $url !== '' ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($url) . '</a>' : '—';
        }
        if ($col === 'pisl_order') echo esc_html((string) ($f['order'] ?? '0'));
        if ($col === 'pisl_seo')   echo self::render_seo_badge($post_id, $f);
    }

    public static function appreciation_columns(array $cols): array {
        return [
            'cb'            => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb'    => 'Photo',
            'title'         => 'Appreciation Title',
            'pisl_company'  => 'Client Company',
            'pisl_category' => 'Category',
            'pisl_year'     => 'Year / Location',
        ];
    }

    public static function render_appreciation_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        $imgs = is_array($f['images'] ?? null) ? $f['images'] : [];
        if ($col === 'pisl_thumb')    echo self::render_thumb_html($imgs[0] ?? 0, $post_id);
        if ($col === 'pisl_company')  echo esc_html((string) ($f['company'] ?? '—'));
        if ($col === 'pisl_category') echo esc_html(self::term_names($post_id, 'client_category', (string) ($f['category'] ?? '')));
        if ($col === 'pisl_year')     echo esc_html(trim((string) ($f['year'] ?? '') . ' ' . (string) ($f['location'] ?? '')) ?: '—');
    }

    public static function coverage_columns(array $cols): array {
        return [
            'cb'          => $cols['cb'] ?? '<input type="checkbox" />',
            'title'       => 'Location / Project Name',
            'pisl_state'  => 'State / City',
            'pisl_status' => 'Project Type / Marker',
            'pisl_coords' => 'Map Coords (cx, cy)',
            'pisl_map'    => 'Map Visibility',
        ];
    }

    public static function render_coverage_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        if ($col === 'pisl_state')  echo esc_html(trim((string) ($f['city'] ?? '') . ', ' . (string) ($f['state'] ?? ''), ', ') ?: '—');
        if ($col === 'pisl_status') echo esc_html(ucfirst((string) ($f['projectType'] ?? '—')) . ' / ' . ucfirst((string) ($f['markerStatus'] ?? 'active')));
        if ($col === 'pisl_coords') echo '<code>' . esc_html(($f['cx'] ?? '-') . ', ' . ($f['cy'] ?? '-')) . '</code>';
        if ($col === 'pisl_map')    echo !empty($f['showOnMap']) ? '<span class="pisl-badge pisl-badge-pub">Visible</span>' : '<span class="pisl-badge pisl-badge-draft">Hidden</span>';
    }

    public static function job_columns(array $cols): array {
        return [
            'cb'         => $cols['cb'] ?? '<input type="checkbox" />',
            'title'      => 'Job Role',
            'pisl_dept'  => 'Department',
            'pisl_exp'   => 'Experience',
            'pisl_qual'  => 'Qualification',
            'pisl_loc'   => 'Location',
            'date'       => 'Posted',
        ];
    }

    public static function render_job_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        if ($col === 'pisl_dept') echo esc_html(self::term_names($post_id, 'job_department', (string) ($f['department'] ?? '')));
        if ($col === 'pisl_exp')  echo esc_html((string) ($f['experience'] ?? '—'));
        if ($col === 'pisl_qual') echo esc_html((string) ($f['qualification'] ?? '—'));
        if ($col === 'pisl_loc')  echo esc_html((string) ($f['location'] ?? '—'));
    }

    public static function solution_columns(array $cols): array {
        return [
            'cb'         => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb' => 'Hero',
            'title'      => 'Solution Page Title',
            'pisl_slug'  => 'Route Slug',
            'pisl_seo'   => 'SEO',
            'date'       => 'Updated',
        ];
    }

    public static function render_solution_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        if ($col === 'pisl_thumb') echo self::render_thumb_html($f['heroImage'] ?? 0, $post_id);
        if ($col === 'pisl_slug')  echo '<code>/solutions' . (($f['slug'] ?? '') === 'solutions' ? '' : '/' . esc_html((string) ($f['slug'] ?? ''))) . '</code>';
        if ($col === 'pisl_seo')   echo self::render_seo_badge($post_id, $f);
    }

    public static function policy_columns(array $cols): array {
        return [
            'cb'            => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb'    => 'Cover',
            'title'         => 'Document Title',
            'pisl_category' => 'Category',
            'pisl_pdf'      => 'PDF File',
            'pisl_order'    => 'Order',
            'pisl_updated'  => 'Updated Label',
        ];
    }

    public static function render_policy_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        if ($col === 'pisl_thumb')    echo self::render_thumb_html($f['coverImage'] ?? 0, $post_id);
        if ($col === 'pisl_category') echo esc_html(self::term_names($post_id, 'policy_category'));
        if ($col === 'pisl_pdf') {
            $pdf = $f['pdfFile'] ?? 0;
            $url = is_numeric($pdf) ? wp_get_attachment_url((int) $pdf) : (is_string($pdf) ? $pdf : '');
            echo $url ? '<a class="pisl-badge pisl-badge-pub" href="' . esc_url($url) . '" target="_blank" rel="noopener">PDF Ready</a>' : '<span class="pisl-badge pisl-badge-draft">No PDF</span>';
        }
        if ($col === 'pisl_order')   echo esc_html((string) ($f['displayOrder'] ?? '0'));
        if ($col === 'pisl_updated') echo esc_html((string) ($f['updatedLabel'] ?? '—'));
    }

    public static function milestone_columns(array $cols): array {
        return [
            'cb'          => $cols['cb'] ?? '<input type="checkbox" />',
            'pisl_thumb'  => 'Image',
            'title'       => 'Milestone Title',
            'pisl_year'   => 'Year',
            'pisl_tag'    => 'Tag',
            'pisl_awards' => 'Awards Count',
        ];
    }

    public static function render_milestone_column(string $col, int $post_id): void {
        $f = self::get_fields($post_id);
        if ($col === 'pisl_thumb')  echo self::render_thumb_html($f['image'] ?? 0, $post_id);
        if ($col === 'pisl_year')   echo '<strong>' . esc_html((string) ($f['year'] ?? '—')) . '</strong>';
        if ($col === 'pisl_tag')    echo esc_html((string) ($f['tag'] ?? '—'));
        if ($col === 'pisl_awards') echo esc_html((string) ($f['awardsCount'] ?? ($f['totalAwards'] ?? '0')));
    }
}
