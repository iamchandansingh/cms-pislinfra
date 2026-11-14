<?php
/**
 * Formats WordPress Media Attachments and Upload URLs into headless JSON objects
 * compatible with React components (url, formats, alternativeText, mime, etc.).
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Media_Helper {
    public static function init(): void {
        add_filter('upload_mimes', [__CLASS__, 'allow_modern_mimes']);
    }

    public static function allow_modern_mimes(array $mimes): array {
        $mimes['webp'] = 'image/webp';
        $mimes['avif'] = 'image/avif';
        $mimes['svg']  = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Format a WP attachment ID, numeric array, or existing media array/URL into a unified media object.
     */
    public static function format_media($val) {
        if (empty($val)) {
            return null;
        }
        if (is_numeric($val)) {
            return self::format_attachment((int) $val);
        }
        if (is_array($val)) {
            if (isset($val['id']) && is_numeric($val['id']) && empty($val['url'])) {
                $att = self::format_attachment((int) $val['id']);
                if ($att) return $att;
            }
            if (!empty($val['url'])) {
                if (!empty($val['wp_attachment_id']) && is_numeric($val['wp_attachment_id'])) {
                    $att = self::format_attachment((int) $val['wp_attachment_id']);
                    if ($att) return $att;
                }
                return $val;
            }
        }
        if (is_string($val)) {
            $trimmed = trim($val);
            if ($trimmed === '') return null;
            return [
                'id'   => 0,
                'name' => basename(parse_url($trimmed, PHP_URL_PATH) ?: 'media'),
                'url'  => $trimmed,
            ];
        }
        return null;
    }

    public static function format_media_list($val): array {
        if (empty($val)) {
            return [];
        }
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) {
                $val = $decoded;
            } else {
                $ids = array_filter(array_map('trim', explode(',', $val)));
                $val = $ids;
            }
        }
        if (!is_array($val)) {
            return [];
        }
        // Single media assoc array accidentally passed as list
        if (isset($val['url']) || isset($val['id'])) {
            $one = self::format_media($val);
            return $one ? [$one] : [];
        }
        $out = [];
        foreach ($val as $item) {
            $formatted = self::format_media($item);
            if ($formatted) {
                $out[] = $formatted;
            }
        }
        return $out;
    }

    public static function format_attachment(int $attachment_id): ?array {
        if ($attachment_id <= 0) {
            return null;
        }
        $post = get_post($attachment_id);
        if (!$post || $post->post_type !== 'attachment') {
            return null;
        }

        $url = wp_get_attachment_url($attachment_id);
        if (!$url) {
            return null;
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        $mime = get_post_mime_type($attachment_id) ?: '';
        $alt  = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        $file = get_attached_file($attachment_id);
        $name = $file ? basename($file) : basename((string) parse_url($url, PHP_URL_PATH));

        $formats = null;
        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            $formats = [];
            $size_map = [
                'thumbnail' => 'thumbnail',
                'small'     => 'pisl_small',
                'medium'    => 'pisl_medium',
                'large'     => 'pisl_large',
            ];
            foreach ($size_map as $key => $wp_size) {
                $img = wp_get_attachment_image_src($attachment_id, $wp_size);
                if ($img && !empty($img[0])) {
                    $formats[$key] = [
                        'url'    => $img[0],
                        'width'  => (int) $img[1],
                        'height' => (int) $img[2],
                    ];
                }
            }
        }

        return [
            'id'              => $attachment_id,
            'documentId'      => 'wp_media_' . $attachment_id,
            'name'            => $name,
            'alternativeText' => $alt ?: $post->post_title,
            'caption'         => $post->post_excerpt ?: '',
            'width'           => isset($meta['width']) ? (int) $meta['width'] : null,
            'height'          => isset($meta['height']) ? (int) $meta['height'] : null,
            'formats'         => $formats ?: null,
            'mime'            => $mime,
            'url'             => $url,
        ];
    }

    /**
     * Find an attachment ID by filename or stored pisl legacy filename meta.
     */
    public static function find_attachment_by_filename(string $filename): int {
        $filename = sanitize_file_name(basename($filename));
        if ($filename === '') return 0;
        global $wpdb;
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_pisl_original_filename' AND meta_value = %s LIMIT 1",
            $filename
        ));
        if ($id) return (int) $id;

        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($stem) . '%'
        ));
        return $id ? (int) $id : 0;
    }

    /**
     * Resolve the featured image for a blog post cleanly:
     * 1. WP _thumbnail_id
     * 2. Custom field featuredImage
     * 3. First uploaded/embedded image inside post_content or attached media
     *
     * @return int|string|null Attachment ID, URL string, or null
     */
    public static function resolve_post_featured_image(\WP_Post $post) {
        $thumb_id = (int) get_post_thumbnail_id($post->ID);
        if ($thumb_id > 0) {
            return $thumb_id;
        }

        $fields = get_post_meta($post->ID, '_pisl_fields', true);
        if (is_array($fields) && !empty($fields['featuredImage'])) {
            $fi = $fields['featuredImage'];
            if (is_numeric($fi) && (int) $fi > 0) {
                return (int) $fi;
            }
            if (is_array($fi) && !empty($fi['id']) && is_numeric($fi['id'])) {
                return (int) $fi['id'];
            }
            if (is_string($fi) && trim($fi) !== '') {
                return trim($fi);
            }
        }

        $content = (string) $post->post_content;
        if ($content !== '') {
            if (preg_match('/<!--\s*wp:image\s*\{[^}]*"id"\s*:\s*(\d+)/', $content, $m) && (int) $m[1] > 0) {
                return (int) $m[1];
            }
            if (preg_match('/class=["\'][^"\']*\bwp-image-(\d+)\b/', $content, $m) && (int) $m[1] > 0) {
                return (int) $m[1];
            }
            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m)) {
                $src = trim($m[1]);
                if ($src !== '') {
                    $att_id = attachment_url_to_postid($src);
                    if ($att_id > 0) {
                        return (int) $att_id;
                    }
                    return $src;
                }
            }
        }

        $attached = get_attached_media('image', $post->ID);
        if (!empty($attached)) {
            $first = reset($attached);
            if ($first instanceof \WP_Post && $first->ID > 0) {
                return (int) $first->ID;
            }
        }

        return null;
    }
}
