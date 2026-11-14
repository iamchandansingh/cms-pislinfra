<?php
/**
 * Shared form field renderer and sanitizer for CPT metaboxes and Single-Type Admin pages.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PISL_Field_Renderer {
    private static $icon_datalist_printed = false;

    private static function maybe_print_icon_datalist(): void {
        if (self::$icon_datalist_printed) return;
        self::$icon_datalist_printed = true;
        $icons = [
            'FaWarehouse', 'FaCalendar', 'FaBuilding', 'FaUsers', 'FaTeam',
            'FaHardHat', 'FaIndustry', 'FaHandshake', 'FaStar', 'FaLightbulb',
            'FaShieldAlt', 'FaCogs', 'FaChartLine', 'FaCheckCircle',
            'FaGraduationCap', 'FaBriefcase', 'FaNetworkWired',
            'Building2', 'TrendingUp', 'MapPin',
        ];
        echo '<datalist id="pisl-fa-icons">';
        foreach ($icons as $ic) {
            echo '<option value="' . esc_attr($ic) . '"></option>';
        }
        echo '</datalist>';
    }

    public static function render_fields(array $fields, array $values, string $prefix = 'pisl_fields'): void {
        self::maybe_print_icon_datalist();
        echo '<div class="pisl-form-grid">';
        foreach ($fields as $f) {
            $key   = $f['key'];
            $label = $f['label'];
            $type  = $f['type'];
            $has_key = array_key_exists($key, $values);
            $val   = $has_key ? $values[$key] : ($f['default'] ?? '');
            $name  = $prefix . '[' . $key . ']';
            $is_wide = in_array($type, ['textarea', 'wysiwyg', 'json', 'gallery', 'repeater', 'resource_single', 'nav_menu_builder'], true);

            echo '<div class="pisl-field-row ' . ($is_wide ? 'pisl-wide' : '') . '">';
            echo '<label class="pisl-label" for="' . esc_attr($prefix . '_' . $key) . '">' . esc_html($label) . ' <code>' . esc_html($key) . '</code></label>';

            switch ($type) {
                case 'text':
                case 'date':
                case 'number':
                case 'number_float':
                    $input_type = $type === 'date' ? 'date' : ($type === 'number' || $type === 'number_float' ? 'number' : 'text');
                    $step = $type === 'number_float' ? ' step="any"' : '';
                    $list_attr = ($key === 'iconName') ? ' list="pisl-fa-icons"' : '';
                    echo '<input type="' . esc_attr($input_type) . '"' . $step . $list_attr . ' id="' . esc_attr($prefix . '_' . $key) . '" name="' . esc_attr($name) . '" value="' . esc_attr(is_scalar($val) ? (string) $val : '') . '" class="regular-text pisl-input" />';
                    break;

                case 'textarea':
                    echo '<textarea id="' . esc_attr($prefix . '_' . $key) . '" name="' . esc_attr($name) . '" rows="3" class="large-text pisl-textarea">' . esc_textarea(is_scalar($val) ? (string) $val : '') . '</textarea>';
                    break;

                case 'wysiwyg':
                    wp_editor(is_string($val) ? $val : '', $prefix . '_' . $key, [
                        'textarea_name' => $name,
                        'textarea_rows' => 6,
                        'media_buttons' => true,
                        'teeny'         => true,
                    ]);
                    break;

                case 'json':
                    $str = is_string($val) ? $val : (empty($val) ? '' : wp_json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    echo '<textarea id="' . esc_attr($prefix . '_' . $key) . '" name="' . esc_attr($name) . '" rows="5" class="large-text code pisl-json">' . esc_textarea($str) . '</textarea>';
                    echo '<p class="description">Valid JSON structure preserved cleanly through the REST API.</p>';
                    break;

                case 'checkbox':
                    $checked = !empty($val) ? ' checked' : '';
                    echo '<label class="pisl-toggle"><input type="checkbox" name="' . esc_attr($name) . '" value="1"' . $checked . ' /> Enabled</label>';
                    break;

                case 'select':
                    echo '<select id="' . esc_attr($prefix . '_' . $key) . '" name="' . esc_attr($name) . '" class="pisl-select">';
                    foreach (($f['options'] ?? []) as $opt_val => $opt_label) {
                        $sel = ((string) $val === (string) $opt_val) ? ' selected' : '';
                        echo '<option value="' . esc_attr($opt_val) . '"' . $sel . '>' . esc_html($opt_label) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'media':
                    self::render_single_media_picker($name, $val);
                    break;

                case 'gallery':
                    self::render_gallery_picker($name, $val);
                    break;

                case 'resource_single':
                    $rs = is_array($val) ? $val : (is_string($val) && $val !== '' ? (json_decode($val, true) ?: []) : []);
                    if (!is_array($rs)) $rs = [];
                    echo '<div class="pisl-resource-single-box" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;background:#fff;border:1px solid #cbd5e1;border-radius:6px;padding:12px;">';
                    if (!empty($rs['id'])) {
                        echo '<input type="hidden" name="' . esc_attr($name . '[id]') . '" value="' . esc_attr((string) $rs['id']) . '" />';
                    }
                    echo '<div class="pisl-sub-field" style="grid-column:1 / -1;"><label>Title</label><input type="text" class="regular-text pisl-input" name="' . esc_attr($name . '[title]') . '" value="' . esc_attr((string) ($rs['title'] ?? '')) . '" /></div>';
                    echo '<div class="pisl-sub-field" style="grid-column:1 / -1;"><label>Description</label><textarea class="large-text pisl-textarea" rows="3" name="' . esc_attr($name . '[desc]') . '">' . esc_textarea((string) ($rs['desc'] ?? '')) . '</textarea></div>';
                    echo '<div class="pisl-sub-field"><label>PDF / Document File</label>';
                    self::render_single_media_picker($name . '[file]', $rs['file'] ?? null);
                    echo '</div>';
                    echo '<div class="pisl-sub-field"><label>Cover Image</label>';
                    self::render_single_media_picker($name . '[coverImage]', $rs['coverImage'] ?? null);
                    echo '</div>';
                    echo '<div class="pisl-sub-field" style="grid-column:1 / -1;"><label>Fallback File URL (Optional)</label><input type="text" class="regular-text pisl-input" name="' . esc_attr($name . '[fileUrl]') . '" value="' . esc_attr((string) ($rs['fileUrl'] ?? '')) . '" placeholder="/reports/example.pdf" /></div>';
                    echo '</div>';
                    break;

                case 'repeater':
                    self::render_repeater($name, is_array($val) ? $val : [], $f['subfields'] ?? []);
                    break;

                case 'nav_menu_builder':
                    self::render_nav_menu_builder($name, $val);
                    break;
            }
            echo '</div>';
        }
        echo '</div>';
    }

    private static function extract_media_preview($val): array {
        $id  = 0;
        $url = '';
        $filename = '';
        if (is_numeric($val)) {
            $id  = (int) $val;
            $url = (string) wp_get_attachment_url($id);
            $filename = $url ? basename(parse_url($url, PHP_URL_PATH)) : '';
        } elseif (is_array($val)) {
            $id  = (int) ($val['id'] ?? $val['wp_attachment_id'] ?? 0);
            $url = (string) ($val['url'] ?? ($id ? wp_get_attachment_url($id) : ''));
            $filename = (string) ($val['name'] ?? ($url ? basename(parse_url($url, PHP_URL_PATH)) : ''));
        } elseif (is_string($val) && $val !== '') {
            $url = $val;
            $filename = basename(parse_url($url, PHP_URL_PATH));
        }
        return ['id' => $id, 'url' => $url, 'name' => $filename];
    }

    public static function render_single_media_picker(string $name, $val): void {
        $info = self::extract_media_preview($val);
        $stored = $info['id'] > 0 ? (string) $info['id'] : ($info['url'] !== '' ? $info['url'] : '');
        $is_img = $info['url'] && preg_match('/\.(png|jpe?g|webp|avif|gif|svg)(\?|$)/i', $info['url']);
        echo '<div class="pisl-media-picker">';
        echo '<input type="hidden" class="pisl-media-val" name="' . esc_attr($name) . '" value="' . esc_attr($stored) . '" />';
        echo '<div class="pisl-media-preview">';
        if ($is_img) {
            echo '<img src="' . esc_url($info['url']) . '" alt="" />';
        } elseif ($info['url']) {
            echo '<span class="pisl-file-pill">' . esc_html($info['name'] ?: $info['url']) . '</span>';
        } else {
            echo '<span class="pisl-empty-pill">No file selected</span>';
        }
        echo '</div>';
        echo '<div class="pisl-media-actions">';
        echo '<button type="button" class="button pisl-media-select">Select / Upload</button> ';
        echo '<button type="button" class="button-link-delete pisl-media-clear">Remove</button>';
        echo '</div></div>';
    }

    public static function render_gallery_picker(string $name, $val): void {
        $items = is_array($val) ? $val : (is_string($val) && $val !== '' ? (json_decode($val, true) ?: []) : []);
        $serialized = [];
        echo '<div class="pisl-gallery-picker" data-name="' . esc_attr($name) . '">';
        echo '<ul class="pisl-gallery-list">';
        foreach ($items as $item) {
            $info = self::extract_media_preview($item);
            if (!$info['url'] && !$info['id']) continue;
            $token = $info['id'] > 0 ? $info['id'] : $info['url'];
            $serialized[] = $token;
            echo '<li class="pisl-gallery-item" data-val="' . esc_attr((string) $token) . '">';
            if ($info['url']) {
                echo '<img src="' . esc_url($info['url']) . '" alt="" />';
            }
            echo '<button type="button" class="pisl-gallery-remove" title="Remove">&times;</button>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<input type="hidden" class="pisl-gallery-val" name="' . esc_attr($name) . '" value="' . esc_attr(wp_json_encode($serialized)) . '" />';
        echo '<button type="button" class="button pisl-gallery-add">Add Images</button>';
        echo '</div>';
    }

    public static function render_repeater(string $name, array $rows, array $subfields): void {
        echo '<div class="pisl-repeater" data-name="' . esc_attr($name) . '">';
        echo '<div class="pisl-repeater-rows">';
        foreach ($rows as $idx => $row) {
            self::render_repeater_row($name, (string) $idx, is_array($row) ? $row : [], $subfields);
        }
        echo '</div>';
        echo '<script type="text/html" class="pisl-repeater-template">';
        self::render_repeater_row($name, '__INDEX__', [], $subfields);
        echo '</script>';
        echo '<button type="button" class="button pisl-repeater-add">+ Add Item</button>';
        echo '</div>';
    }

    private static function render_repeater_row(string $base_name, string $idx, array $row, array $subfields): void {
        echo '<div class="pisl-repeater-row">';
        echo '<div class="pisl-repeater-header">';
        echo '<span class="pisl-repeater-handle" style="cursor:grab;" title="Drag to reorder">&#9776; Item</span>';
        echo '<span style="display:inline-flex;gap:6px;align-items:center;">';
        echo '<button type="button" class="button button-small pisl-repeater-up" title="Move Up">&uarr;</button>';
        echo '<button type="button" class="button button-small pisl-repeater-down" title="Move Down">&darr;</button>';
        echo '<button type="button" class="button-link-delete pisl-repeater-remove">Remove</button>';
        echo '</span>';
        echo '</div>';
        echo '<div class="pisl-repeater-fields">';
        foreach ($subfields as $sf) {
            $sk = $sf['key'];
            $sv = $row[$sk] ?? '';
            $fn = $base_name . '[' . $idx . '][' . $sk . ']';
            echo '<div class="pisl-sub-field"><label>' . esc_html($sf['label']) . '</label>';
            if ($sf['type'] === 'media') {
                self::render_single_media_picker($fn, $sv);
            } elseif ($sf['type'] === 'textarea') {
                echo '<textarea name="' . esc_attr($fn) . '" rows="2" class="large-text">' . esc_textarea(is_scalar($sv) ? (string) $sv : '') . '</textarea>';
            } elseif ($sf['type'] === 'checkbox') {
                echo '<input type="checkbox" name="' . esc_attr($fn) . '" value="1"' . (!empty($sv) ? ' checked' : '') . ' />';
            } elseif ($sf['type'] === 'number') {
                echo '<input type="number" name="' . esc_attr($fn) . '" value="' . esc_attr(is_scalar($sv) ? (string) $sv : '') . '" class="small-text" />';
            } else {
                $list_attr = ($sk === 'iconName') ? ' list="pisl-fa-icons"' : '';
                echo '<input type="text"' . $list_attr . ' name="' . esc_attr($fn) . '" value="' . esc_attr(is_scalar($sv) ? (string) $sv : '') . '" class="regular-text" />';
            }
            echo '</div>';
        }
        echo '</div></div>';
    }

    public static function render_nav_menu_builder(string $name, $val): void {
        self::maybe_print_icon_datalist();
        $items = is_array($val) ? $val : (is_string($val) && $val !== '' ? (json_decode($val, true) ?: []) : []);
        if (empty($items)) {
            $items = [
                ['id' => 127, 'label' => 'Home', 'href' => '/', 'subLinks' => []],
                ['id' => 130, 'label' => 'About Us', 'href' => '/about', 'subLinks' => [
                    ['id' => 253, 'label' => 'Overview / Profile', 'href' => '/about', 'icon' => 'FaBuilding'],
                    ['id' => 254, 'label' => 'Leadership', 'href' => '/about/leadership', 'icon' => 'FaUsers'],
                    ['id' => 255, 'label' => 'EHS', 'href' => '/about/ehs', 'icon' => 'FaShieldAlt'],
                    ['id' => 256, 'label' => 'Awards & Certification', 'href' => '/about/awards', 'icon' => 'FaAward'],
                    ['id' => 257, 'label' => 'CSR', 'href' => '/about/csr', 'icon' => 'FaHeart'],
                ]],
                ['id' => 131, 'label' => 'Projects', 'href' => '/projects', 'subLinks' => [
                    ['id' => 258, 'label' => 'Ongoing Projects', 'href' => '/projects/ongoing', 'icon' => 'FaBuilding'],
                    ['id' => 259, 'label' => 'Completed Projects', 'href' => '/projects/completed', 'icon' => 'FaBuilding'],
                    ['id' => 260, 'label' => 'Case Study', 'href' => '/projects/case-study', 'icon' => 'FaBuilding'],
                ]],
                ['id' => 132, 'label' => 'Our Solutions', 'href' => '/solutions', 'subLinks' => [
                    ['id' => 261, 'label' => 'Industrial Development', 'href' => '/solutions/industrial', 'icon' => 'FaBuilding'],
                    ['id' => 262, 'label' => 'Infrastructure Development', 'href' => '/solutions/infrastructure', 'icon' => 'FaBuilding'],
                    ['id' => 263, 'label' => 'Logistic Park Development', 'href' => '/solutions/logistic', 'icon' => 'FaBuilding'],
                    ['id' => 264, 'label' => 'Warehouse Contractors', 'href' => '/solutions/warehouse', 'icon' => 'FaBuilding'],
                ]],
                ['id' => 128, 'label' => 'Blog', 'href' => '/blog', 'subLinks' => []],
                ['id' => 129, 'label' => 'Careers', 'href' => '/careers', 'subLinks' => []],
            ];
        }

        echo '<div class="pisl-nav-builder" data-name="' . esc_attr($name) . '">';
        echo '<p class="description" style="margin:0 0 14px;color:#475569;">Visual navigation manager: Add, edit, or reorder top-level links and hover dropdowns without editing raw JSON.</p>';
        echo '<div class="pisl-nav-items">';
        foreach ($items as $idx => $item) {
            self::render_nav_item_card($name, (string) $idx, is_array($item) ? $item : []);
        }
        echo '</div>';

        // Template for new main menu item
        echo '<script type="text/html" class="pisl-nav-item-template">';
        self::render_nav_item_card($name, '__MAIN_IDX__', [
            'id'       => '',
            'label'    => '',
            'href'     => '',
            'subLinks' => [],
        ]);
        echo '</script>';

        // Template for new sublink row
        echo '<script type="text/html" class="pisl-nav-sublink-template">';
        self::render_nav_sublink_row($name, '__MAIN_IDX__', '__SUB_IDX__', [
            'id'    => '',
            'label' => '',
            'href'  => '',
            'icon'  => 'FaBuilding',
        ]);
        echo '</script>';

        echo '<div style="margin-top:14px;">';
        echo '<button type="button" class="button button-primary pisl-add-nav-item-btn" style="font-weight:600;">+ Add Top-Level Menu Item</button>';
        echo '</div>';
        echo '</div>';
    }

    private static function render_nav_item_card(string $base_name, string $idx, array $item): void {
        $label = (string) ($item['label'] ?? '');
        $href  = (string) ($item['href'] ?? '');
        $id    = (string) ($item['id'] ?? '');
        $subs  = is_array($item['subLinks'] ?? null) ? $item['subLinks'] : [];
        $display_title = $label !== '' ? $label : 'New Menu Link';

        echo '<div class="pisl-nav-item" data-idx="' . esc_attr($idx) . '">';
        echo '<div class="pisl-nav-item-header">';
        echo '<div class="pisl-nav-item-header-left">';
        echo '<span class="pisl-nav-handle" title="Drag to reorder">&#9776;</span> ';
        echo '<strong class="pisl-nav-title-text">' . esc_html($display_title) . '</strong>';
        if (!empty($href)) {
            echo ' <code class="pisl-nav-route-badge">' . esc_html($href) . '</code>';
        }
        echo '</div>';
        echo '<div class="pisl-nav-item-header-actions">';
        echo '<button type="button" class="button button-small pisl-nav-move-up" title="Move Up">&uarr;</button> ';
        echo '<button type="button" class="button button-small pisl-nav-move-down" title="Move Down">&darr;</button> ';
        echo '<button type="button" class="button-link-delete pisl-nav-remove-item">Remove</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="pisl-nav-item-body">';
        echo '<input type="hidden" name="' . esc_attr($base_name . '[' . $idx . '][id]') . '" value="' . esc_attr($id) . '" />';
        echo '<div class="pisl-nav-main-grid">';
        echo '<div class="pisl-sub-field"><label>Item Label <span style="color:#ef4444;">*</span></label><input type="text" class="regular-text pisl-nav-label-input" name="' . esc_attr($base_name . '[' . $idx . '][label]') . '" value="' . esc_attr($label) . '" placeholder="e.g. About Us" style="width:100%;" /></div>';
        echo '<div class="pisl-sub-field"><label>Link / URL Route <span style="color:#ef4444;">*</span></label><input type="text" class="regular-text pisl-nav-href-input" name="' . esc_attr($base_name . '[' . $idx . '][href]') . '" value="' . esc_attr($href) . '" placeholder="e.g. /about or #" style="width:100%;" /></div>';
        echo '</div>';

        echo '<div class="pisl-nav-sublinks-wrapper">';
        echo '<div class="pisl-nav-sublinks-head"><strong>Dropdown Sublinks (<span class="pisl-sublink-count">' . count($subs) . '</span>)</strong><span class="description" style="margin-left:8px;">Shown in desktop hover dropdown and mobile accordion.</span></div>';
        echo '<div class="pisl-nav-sublinks-list">';
        foreach ($subs as $s_idx => $sub) {
            self::render_nav_sublink_row($base_name, $idx, (string) $s_idx, is_array($sub) ? $sub : []);
        }
        echo '</div>';
        echo '<button type="button" class="button button-secondary button-small pisl-add-sublink-btn" style="margin-top:6px;">+ Add Submenu Link</button>';
        echo '</div>';

        echo '</div></div>';
    }

    private static function render_nav_sublink_row(string $base_name, string $main_idx, string $sub_idx, array $sub): void {
        $s_label = (string) ($sub['label'] ?? '');
        $s_href  = (string) ($sub['href'] ?? '');
        $s_icon  = (string) ($sub['icon'] ?? 'FaBuilding');
        $s_id    = (string) ($sub['id'] ?? '');

        $prefix = $base_name . '[' . $main_idx . '][subLinks][' . $sub_idx . ']';

        echo '<div class="pisl-nav-sublink-row" data-sub-idx="' . esc_attr($sub_idx) . '">';
        echo '<input type="hidden" name="' . esc_attr($prefix . '[id]') . '" value="' . esc_attr($s_id) . '" />';
        echo '<span class="pisl-sublink-handle" title="Drag to reorder">&#8942;&#8942;</span>';
        echo '<div class="pisl-sublink-field" style="flex:2;"><label>Submenu Title</label><input type="text" name="' . esc_attr($prefix . '[label]') . '" value="' . esc_attr($s_label) . '" placeholder="e.g. Leadership" style="width:100%;" /></div>';
        echo '<div class="pisl-sublink-field" style="flex:2;"><label>Submenu Route</label><input type="text" name="' . esc_attr($prefix . '[href]') . '" value="' . esc_attr($s_href) . '" placeholder="e.g. /about/leadership" style="width:100%;" /></div>';
        echo '<div class="pisl-sublink-field" style="flex:1;"><label>Icon</label><input type="text" list="pisl-fa-icons" name="' . esc_attr($prefix . '[icon]') . '" value="' . esc_attr($s_icon) . '" placeholder="FaBuilding" style="width:100%;" /></div>';
        echo '<div class="pisl-sublink-actions">';
        echo '<button type="button" class="button button-small pisl-sublink-up" title="Move Up">&uarr;</button>';
        echo '<button type="button" class="button button-small pisl-sublink-down" title="Move Down">&darr;</button>';
        echo '<button type="button" class="button-link-delete pisl-sublink-remove" title="Remove Sublink">&times;</button>';
        echo '</div>';
        echo '</div>';
    }

    public static function sanitize_nav_menu($raw): array {
        if (!is_array($raw)) {
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                } else {
                    return [];
                }
            } else {
                return [];
            }
        }

        $clean = [];
        $id_counter = 100;
        foreach ($raw as $item) {
            if (!is_array($item)) continue;
            $label = sanitize_text_field(wp_unslash((string) ($item['label'] ?? '')));
            $href  = sanitize_text_field(wp_unslash((string) ($item['href'] ?? '')));
            if ($label === '' && $href === '') {
                continue;
            }
            if ($href === '') {
                $href = '#';
            }

            $item_id = !empty($item['id']) ? (is_numeric($item['id']) ? (int) $item['id'] : sanitize_key($item['id'])) : ++$id_counter;

            $sub_clean = [];
            if (!empty($item['subLinks']) && is_array($item['subLinks'])) {
                $sub_counter = 200;
                foreach ($item['subLinks'] as $sub) {
                    if (!is_array($sub)) continue;
                    $s_label = sanitize_text_field(wp_unslash((string) ($sub['label'] ?? '')));
                    $s_href  = sanitize_text_field(wp_unslash((string) ($sub['href'] ?? '')));
                    $s_icon  = sanitize_text_field(wp_unslash((string) ($sub['icon'] ?? '')));
                    if ($s_label === '' && $s_href === '') {
                        continue;
                    }
                    if ($s_href === '') {
                        $s_href = '#';
                    }
                    $s_id = !empty($sub['id']) ? (is_numeric($sub['id']) ? (int) $sub['id'] : sanitize_key($sub['id'])) : ++$sub_counter;

                    $sub_clean[] = [
                        'id'    => $s_id,
                        'label' => $s_label,
                        'href'  => $s_href,
                        'icon'  => $s_icon ?: 'FaBuilding',
                    ];
                }
            }

            $clean[] = [
                'id'       => $item_id,
                'label'    => $label,
                'href'     => $href,
                'subLinks' => $sub_clean,
            ];
        }

        return $clean;
    }

    /**
     * Sanitizes submitted form data cleanly according to schema field types.
     */
    public static function sanitize_fields(array $fields, array $raw_input): array {
        $clean = [];
        foreach ($fields as $f) {
            $key  = $f['key'];
            $type = $f['type'];
            $val  = $raw_input[$key] ?? null;

            switch ($type) {
                case 'checkbox':
                    $clean[$key] = !empty($val);
                    break;
                case 'number':
                    $clean[$key] = ($val === null || $val === '') ? null : (int) $val;
                    break;
                case 'number_float':
                    $clean[$key] = ($val === null || $val === '') ? null : (float) $val;
                    break;
                case 'textarea':
                    $clean[$key] = $val !== null ? sanitize_textarea_field(wp_unslash((string) $val)) : '';
                    break;
                case 'wysiwyg':
                    $clean[$key] = $val !== null ? wp_kses_post(wp_unslash((string) $val)) : '';
                    break;
                case 'json':
                    if (is_array($val)) {
                        $clean[$key] = $val;
                    } else {
                        $raw_str = trim((string) wp_unslash((string) ($val ?? '')));
                        $decoded = $raw_str !== '' ? json_decode($raw_str, true) : null;
                        $clean[$key] = is_array($decoded) ? $decoded : null;
                    }
                    break;
                case 'resource_single':
                    if (is_array($val)) {
                        $rs_clean = [];
                        if (!empty($val['id']) && is_numeric($val['id'])) {
                            $rs_clean['id'] = (int) $val['id'];
                        }
                        $rs_clean['title']   = sanitize_text_field(wp_unslash((string) ($val['title'] ?? '')));
                        $rs_clean['desc']    = sanitize_textarea_field(wp_unslash((string) ($val['desc'] ?? '')));
                        $rs_url              = trim((string) wp_unslash((string) ($val['fileUrl'] ?? '')));
                        $rs_clean['fileUrl'] = $rs_url !== '' ? sanitize_text_field($rs_url) : null;
                        foreach (['file', 'coverImage'] as $mf) {
                            $mv = $val[$mf] ?? null;
                            if (is_numeric($mv) && (int) $mv > 0) {
                                $rs_clean[$mf] = (int) $mv;
                            } elseif (is_string($mv) && trim($mv) !== '') {
                                $rs_clean[$mf] = sanitize_text_field(wp_unslash($mv));
                            } else {
                                $rs_clean[$mf] = null;
                            }
                        }
                        $clean[$key] = $rs_clean;
                    } else {
                        $raw_str = trim((string) wp_unslash((string) ($val ?? '')));
                        $decoded = $raw_str !== '' ? json_decode($raw_str, true) : null;
                        $clean[$key] = is_array($decoded) ? $decoded : null;
                    }
                    break;
                case 'gallery':
                    if (is_array($val)) {
                        $arr = $val;
                    } else {
                        $arr = json_decode(wp_unslash((string) ($val ?? '[]')), true);
                    }
                    $out = [];
                    if (is_array($arr)) {
                        foreach ($arr as $item) {
                            if (is_numeric($item) && (int) $item > 0) $out[] = (int) $item;
                            elseif (is_string($item) && trim($item) !== '') $out[] = esc_url_raw(trim($item));
                        }
                    }
                    $clean[$key] = $out;
                    break;
                case 'media':
                    if (is_numeric($val) && (int) $val > 0) {
                        $clean[$key] = (int) $val;
                    } elseif (is_string($val) && trim($val) !== '') {
                        $clean[$key] = sanitize_text_field(wp_unslash($val));
                    } else {
                        $clean[$key] = null;
                    }
                    break;
                case 'repeater':
                    $subfields = $f['subfields'] ?? [];
                    $rows = is_array($val) ? array_values($val) : [];
                    $sanitized_rows = [];
                    foreach ($rows as $r) {
                        if (is_array($r)) {
                            $sanitized_rows[] = self::sanitize_fields($subfields, $r);
                        }
                    }
                    $clean[$key] = $sanitized_rows;
                    break;
                case 'nav_menu_builder':
                    $clean[$key] = self::sanitize_nav_menu($val);
                    break;
                default:
                    $clean[$key] = $val !== null ? sanitize_text_field(wp_unslash((string) $val)) : '';
                    break;
            }
        }
        return $clean;
    }
}
