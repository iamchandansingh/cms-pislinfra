<?php
/**
 * Advanced Custom Fields (ACF) interoperability layer.
 * If ACF is active, registers mirror field group metadata cleanly for headless schema
 * discoverability and keeps ACF-saved values synchronized with `_pisl_fields`.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_ACF_Integration {
    public static function init(): void {
        add_action('acf/init', [__CLASS__, 'register_acf_groups']);
        add_action('acf/save_post', [__CLASS__, 'sync_acf_to_pisl_meta'], 20);
    }

    public static function register_acf_groups(): void {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        // Expose lightweight ACF REST Schema metadata when ACF is present,
        // while keeping native Pislinfra metabox UI active (no duplicate inputs).
        foreach (PISL_Schemas::get_cpt_schemas() as $post_type => $cfg) {
            acf_add_local_field_group([
                'key'      => 'group_pisl_' . $post_type,
                'title'    => 'Pislinfra Schema (' . $cfg['rest_slug'] . ')',
                'fields'   => [],
                'location' => [
                    [
                        [
                            'param'    => 'post_type',
                            'operator' => '==',
                            'value'    => $post_type,
                        ],
                    ],
                ],
                'show_in_rest' => true,
            ]);
        }
    }

    public static function sync_acf_to_pisl_meta($post_id): void {
        if (!function_exists('get_fields') || !is_numeric($post_id)) {
            return;
        }
        $post_id = (int) $post_id;
        $acf_vals = get_fields($post_id);
        if (!is_array($acf_vals) || empty($acf_vals)) {
            return;
        }
        $existing = get_post_meta($post_id, '_pisl_fields', true);
        if (!is_array($existing)) $existing = [];
        update_post_meta($post_id, '_pisl_fields', array_merge($existing, $acf_vals));
    }
}
