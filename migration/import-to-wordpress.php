<?php
/**
 * WordPress Headless CMS Local Data & Media Importer.
 * Executed via WP-CLI: `wp eval-file wp-cms/migration/import-to-wordpress.php`
 * Imports strictly from wp-cms/migration/local-dataset.json (zero external network calls).
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this script via WP-CLI: wp eval-file import-to-wordpress.php\n");
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$datasetPath = __DIR__ . '/local-dataset.json';
if (!file_exists($datasetPath)) {
    fwrite(STDERR, "Missing local-dataset.json. Run build-local-dataset.mjs first.\n");
    exit(1);
}

$dataset = json_decode(file_get_contents($datasetPath), true);
if (!is_array($dataset)) {
    fwrite(STDERR, "Invalid JSON in local-dataset.json\n");
    exit(1);
}

// Disable auto-sync workflow hooks during exact historical snapshot seeding
remove_action('save_post_ongoing_project', ['PISL_Workflows', 'on_save_ongoing_project'], 25);
remove_action('save_post_completed_project', ['PISL_Workflows', 'on_save_completed_project'], 25);
remove_action('save_post_coverage_marker', ['PISL_Workflows', 'on_save_coverage_marker'], 25);
remove_action('save_post_post', ['PISL_Workflows', 'on_save_blog'], 25);

// Remove WordPress default "Hello world!" post (ID 1) and "Sample Page" (ID 2)
foreach ([1, 2] as $defaultId) {
    $dp = get_post($defaultId);
    if ($dp && in_array($dp->post_name, ['hello-world', 'sample-page'], true)) {
        wp_delete_post($defaultId, true);
    }
}

$uploadDir = wp_upload_dir();
$uploadBaseDir = $uploadDir['basedir'];
$uploadBaseUrl = $uploadDir['baseurl'];
wp_mkdir_p($uploadBaseDir);

// 1. Import all local media into WordPress Media Library
$mediaMapByUrl = [];
$mediaMapByFile = [];
$importedMediaCount = 0;
$reusedMediaCount = 0;

$existingAtts = get_posts([
    'post_type'      => 'attachment',
    'post_status'    => 'inherit',
    'posts_per_page' => -1,
]);
$existingByOriginFile = [];
foreach ($existingAtts as $att) {
    $orig = get_post_meta($att->ID, '_pisl_original_filename', true);
    if ($orig) {
        $existingByOriginFile[strtolower($orig)] = (int) $att->ID;
    }
    $file = get_attached_file($att->ID);
    if ($file) {
        $existingByOriginFile[strtolower(basename($file))] = (int) $att->ID;
    }
}

foreach (($dataset['media'] ?? []) as $m) {
    $localPath = $m['localPath'] ?? '';
    $filename  = $m['filename'] ?? basename($localPath);
    if (!$localPath || !file_exists($localPath)) {
        fwrite(STDERR, "WARN: Local file missing on disk: $localPath\n");
        continue;
    }

    $lowerFile = strtolower($filename);
    $attId = $existingByOriginFile[$lowerFile] ?? 0;

    if (!$attId) {
        $destPath = $uploadBaseDir . '/' . $filename;
        if (!file_exists($destPath)) {
            copy($localPath, $destPath);
        }
        $filetype = wp_check_filetype($filename, null);
        $mime = $filetype['type'] ?: (str_ends_with($lowerFile, '.avif') ? 'image/avif' : (str_ends_with($lowerFile, '.webp') ? 'image/webp' : 'application/octet-stream'));

        $attId = wp_insert_attachment([
            'guid'           => $uploadBaseUrl . '/' . $filename,
            'post_mime_type' => $mime,
            'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ], $destPath);

        if (is_wp_error($attId) || !$attId) {
            fwrite(STDERR, "ERR: Failed to insert attachment $filename\n");
            continue;
        }

        if (str_starts_with($mime, 'image/') && !str_ends_with($lowerFile, '.svg')) {
            $meta = @wp_generate_attachment_metadata($attId, $destPath);
            if (is_array($meta) && !empty($meta)) {
                wp_update_attachment_metadata($attId, $meta);
            }
        }
        update_post_meta($attId, '_pisl_original_filename', $filename);
        if (!empty($m['alternativeText'])) {
            update_post_meta($attId, '_wp_attachment_image_alt', sanitize_text_field($m['alternativeText']));
        }
        $existingByOriginFile[$lowerFile] = (int) $attId;
        $importedMediaCount++;
    } else {
        $reusedMediaCount++;
    }

    $mediaMapByFile[$lowerFile] = (int) $attId;
    foreach (($m['sourceUrls'] ?? []) as $srcUrl) {
        $mediaMapByUrl[$srcUrl] = (int) $attId;
    }
}

echo "Media Library ready: $importedMediaCount imported, $reusedMediaCount reused.\n";

function resolve_attachment_id($val, array $mediaMapByUrl, array $mediaMapByFile): ?int {
    if (empty($val)) return null;
    $url = '';
    if (is_array($val) && !empty($val['url'])) {
        $url = (string) $val['url'];
    } elseif (is_string($val)) {
        $url = trim($val);
    }
    if ($url === '') return null;
    if (isset($mediaMapByUrl[$url])) {
        return $mediaMapByUrl[$url];
    }
    $path = parse_url($url, PHP_URL_PATH) ?: $url;
    $base = strtolower(basename(urldecode($path)));
    if (isset($mediaMapByFile[$base])) {
        return $mediaMapByFile[$base];
    }
    $unprefixed = preg_replace('/^(thumbnail|small|medium|large)_/', '', $base);
    if (isset($mediaMapByFile[$unprefixed])) {
        return $mediaMapByFile[$unprefixed];
    }
    $norm = preg_replace('/[^a-z0-9._-]/', '_', $unprefixed);
    if (isset($mediaMapByFile[$norm])) {
        return $mediaMapByFile[$norm];
    }
    return null;
}

function rewrite_urls_in_text_or_json($val, array $mediaMapByUrl, array $mediaMapByFile) {
    if (is_string($val)) {
        return preg_replace_callback(
            '/(https?:\/\/[^\s\)"\']+r2\.dev\/[^\s\)"\']+|\/(?:images|reports|videos)\/[^\s\)"\']+)/i',
            function ($m) use ($mediaMapByUrl, $mediaMapByFile) {
                $id = resolve_attachment_id($m[1], $mediaMapByUrl, $mediaMapByFile);
                if ($id) {
                    $wpUrl = wp_get_attachment_url($id);
                    if ($wpUrl) return $wpUrl;
                }
                return $m[1];
            },
            $val
        );
    }
    if (is_array($val)) {
        if (isset($val['url']) && is_string($val['url'])) {
            $id = resolve_attachment_id($val, $mediaMapByUrl, $mediaMapByFile);
            if ($id) {
                return PISL_Media_Helper::format_attachment($id) ?: $val;
            }
        }
        $out = [];
        foreach ($val as $k => $v) {
            $out[$k] = rewrite_urls_in_text_or_json($v, $mediaMapByUrl, $mediaMapByFile);
        }
        return $out;
    }
    return $val;
}

function map_field_value_for_storage(array $fieldDef, $rawVal, array $mediaMapByUrl, array $mediaMapByFile) {
    $type = $fieldDef['type'];
    switch ($type) {
        case 'media':
            $attId = resolve_attachment_id($rawVal, $mediaMapByUrl, $mediaMapByFile);
            return $attId ?: (is_string($rawVal) ? $rawVal : null);

        case 'gallery':
            if (!is_array($rawVal)) return [];
            $ids = [];
            foreach ($rawVal as $item) {
                $attId = resolve_attachment_id($item, $mediaMapByUrl, $mediaMapByFile);
                if ($attId) $ids[] = $attId;
            }
            return $ids;

        case 'resource_single':
            if (!is_array($rawVal)) return $rawVal;
            if (isset($rawVal['file'])) {
                $fid = resolve_attachment_id($rawVal['file'], $mediaMapByUrl, $mediaMapByFile);
                if ($fid) $rawVal['file'] = $fid;
            }
            if (isset($rawVal['coverImage'])) {
                $cid = resolve_attachment_id($rawVal['coverImage'], $mediaMapByUrl, $mediaMapByFile);
                if ($cid) $rawVal['coverImage'] = $cid;
            }
            return $rawVal;

        case 'repeater':
            if (!is_array($rawVal)) return [];
            $subfields = $fieldDef['subfields'] ?? [];
            $outRows = [];
            foreach ($rawVal as $row) {
                if (!is_array($row)) continue;
                $newRow = $row;
                foreach ($subfields as $sf) {
                    $sk = $sf['key'];
                    if (array_key_exists($sk, $row)) {
                        $newRow[$sk] = map_field_value_for_storage($sf, $row[$sk], $mediaMapByUrl, $mediaMapByFile);
                    }
                }
                $outRows[] = $newRow;
            }
            return $outRows;

        case 'json':
        case 'wysiwyg':
            return rewrite_urls_in_text_or_json($rawVal, $mediaMapByUrl, $mediaMapByFile);

        case 'checkbox':
            return !empty($rawVal);

        case 'number':
            return ($rawVal === null || $rawVal === '') ? null : (int) $rawVal;

        case 'number_float':
            return ($rawVal === null || $rawVal === '') ? null : (float) $rawVal;

        default:
            return $rawVal;
    }
}

function map_seo_for_storage(array $item, array $mediaMapByUrl, array $mediaMapByFile): array {
    $seo = isset($item['seo']) && is_array($item['seo']) ? $item['seo'] : [];
    // Normalize top-level SEO keys too when component didn't nest them under seo
    $topKeys = [
        'seoTitle', 'seoDescription', 'seoKeywords',
        'metaTitle' => 'seoTitle',
        'metaDescription' => 'seoDescription',
        'focusKeywords' => 'seoKeywords',
        'primaryKeyword' => 'tags',
    ];
    foreach ($topKeys as $srcKey => $destKey) {
        if (is_int($srcKey)) $srcKey = $destKey;
        if (empty($seo[$destKey]) && !empty($item[$srcKey])) {
            $seo[$destKey] = $item[$srcKey];
        }
    }
    foreach (['ogImage', 'twitterImage'] as $imgKey) {
        if (!empty($seo[$imgKey])) {
            $attId = resolve_attachment_id($seo[$imgKey], $mediaMapByUrl, $mediaMapByFile);
            if ($attId) $seo[$imgKey] = $attId;
        }
    }
    return $seo;
}

// 2. Import all Collections into WordPress CPTs & Blogs (post)
$cptSchemas = PISL_Schemas::get_cpt_schemas();
$restSlugToPostType = [];
foreach ($cptSchemas as $pt => $cfg) {
    $restSlugToPostType[$cfg['rest_slug']] = $pt;
}

$importCounts = [];
foreach (($dataset['collections'] ?? []) as $restSlug => $items) {
    $pt = $restSlugToPostType[$restSlug] ?? null;
    if (!$pt || !is_array($items)) continue;
    $schema = $cptSchemas[$pt];
    $count = 0;

    foreach ($items as $idx => $item) {
        if (!is_array($item)) continue;
        $docId = (string) ($item['documentId'] ?? ($pt . '_' . ($item['id'] ?? ($idx + 1))));

        // Determine post_title & post_name
        $title = (string) ($item['title'] ?? $item['name'] ?? $item['projectName'] ?? $item['headline'] ?? $item['company'] ?? ($pt . ' ' . ($idx + 1)));
        $slug  = sanitize_title((string) ($item['slug'] ?? $title));

        // Status mapping (preserve published vs draft)
        $rawStatus = strtolower((string) ($item['status'] ?? $item['statusField'] ?? ''));
        $postStatus = ($rawStatus === 'draft') ? 'draft' : 'publish';

        // Date mapping
        $dateIso = $item['publishDate'] ?? $item['publishedAt'] ?? $item['createdAt'] ?? '';
        $postDate = '';
        if (is_string($dateIso) && $dateIso !== '') {
            $ts = strtotime($dateIso);
            if ($ts && $ts > 86400) {
                $postDate = gmdate('Y-m-d H:i:s', $ts);
            }
        }

        // Content mapping (for blogs, rewrite inline markdown image URLs to WP uploads)
        $postContent = '';
        if ($pt === 'post' && !empty($item['content'])) {
            $postContent = rewrite_urls_in_text_or_json((string) $item['content'], $mediaMapByUrl, $mediaMapByFile);
        } elseif ($pt === 'press_release' && !empty($item['summary'])) {
            $postContent = (string) $item['summary'];
        }

        // Find existing post strictly by _pisl_document_id (never collide on duplicate titles across cities/years)
        $existing = get_posts([
            'post_type'      => $pt,
            'post_status'    => ['publish', 'draft', 'pending', 'future'],
            'meta_key'       => '_pisl_document_id',
            'meta_value'     => $docId,
            'posts_per_page' => 1,
        ]);
        if (empty($existing) && !empty($item['slug']) && $slug !== '') {
            $existing = get_posts([
                'post_type'      => $pt,
                'post_status'    => ['publish', 'draft', 'pending', 'future'],
                'name'           => $slug,
                'posts_per_page' => 1,
            ]);
        }

        $postArgs = [
            'post_type'    => $pt,
            'post_title'   => $title,
            'post_name'    => $slug,
            'post_content' => $postContent,
            'post_status'  => $postStatus,
        ];
        if ($postDate !== '') {
            $postArgs['post_date']     = $postDate;
            $postArgs['post_date_gmt'] = $postDate;
        }
        if (!empty($existing)) {
            $postArgs['ID'] = $existing[0]->ID;
            $postId = wp_update_post($postArgs, true);
        } else {
            $postId = wp_insert_post($postArgs, true);
        }

        if (is_wp_error($postId) || !$postId) {
            fwrite(STDERR, "ERR: Failed saving $pt [$docId]: " . (is_wp_error($postId) ? $postId->get_error_message() : 'unknown') . "\n");
            continue;
        }

        update_post_meta($postId, '_pisl_document_id', $docId);
        update_post_meta($postId, '_pisl_strapi_id', (string) ($item['id'] ?? ''));

        // Build clean custom fields array matching schema
        $fieldsToStore = [];
        foreach ($schema['fields'] as $fieldDef) {
            $fk = $fieldDef['key'];
            $rawVal = $item[$fk] ?? null;
            // Fallback aliases present across historical backups
            if ($rawVal === null && $fk === 'featuredImage' && !empty($item['image'])) {
                $rawVal = $item['image'];
            }
            if ($rawVal === null && $fk === 'statusField' && !empty($item['status'])) {
                $rawVal = $item['status'];
            }
            if ($rawVal === null && $fk === 'projectStatus' && !empty($item['status'])) {
                $rawVal = $item['status'];
            }
            if ($rawVal === null && $fk === 'logoUrl' && !empty($item['logo']) && is_string($item['logo'])) {
                $rawVal = $item['logo'];
            }
            $fieldsToStore[$fk] = map_field_value_for_storage($fieldDef, $rawVal, $mediaMapByUrl, $mediaMapByFile);
        }
        update_post_meta($postId, '_pisl_fields', $fieldsToStore);

        // Set WP Featured Image thumbnail for WP Admin visual preview
        $thumbId = 0;
        foreach (['featuredImage', 'image', 'coverImage', 'logo', 'heroImage'] as $tk) {
            if (!empty($fieldsToStore[$tk]) && is_numeric($fieldsToStore[$tk])) {
                $thumbId = (int) $fieldsToStore[$tk];
                break;
            }
        }
        if (!$thumbId && !empty($fieldsToStore['images']) && is_array($fieldsToStore['images']) && is_numeric($fieldsToStore['images'][0] ?? null)) {
            $thumbId = (int) $fieldsToStore['images'][0];
        }
        if ($thumbId > 0) {
            set_post_thumbnail($postId, $thumbId);
        }

        // SEO metadata
        if (!empty($schema['has_seo'])) {
            $seo = map_seo_for_storage($item, $mediaMapByUrl, $mediaMapByFile);
            update_post_meta($postId, '_pisl_seo', $seo);
        }

        // Taxonomies
        if ($pt === 'post') {
            if (!empty($item['category'])) {
                wp_set_object_terms($postId, [(string) $item['category']], 'category');
            }
            if (!empty($item['tags'])) {
                $tagsArr = is_array($item['tags']) ? $item['tags'] : array_filter(array_map('trim', explode(',', (string) $item['tags'])));
                if (!empty($tagsArr)) {
                    wp_set_object_terms($postId, $tagsArr, 'post_tag');
                }
            }
        }
        if (in_array($pt, ['ongoing_project', 'completed_project', 'case_study'], true) && !empty($item['category'])) {
            wp_set_object_terms($postId, [(string) $item['category']], 'project_category');
        }
        if (in_array($pt, ['ongoing_project', 'completed_project', 'coverage_marker'], true) && !empty($item['state'])) {
            wp_set_object_terms($postId, [(string) $item['state']], 'project_state');
        }
        if ($pt === 'award' && !empty($item['category'])) {
            wp_set_object_terms($postId, [(string) $item['category']], 'award_category');
        }
        if ($pt === 'job_opening' && !empty($item['department'])) {
            wp_set_object_terms($postId, [(string) $item['department']], 'job_department');
        }

        $count++;
    }
    $importCounts[$restSlug] = $count;
}

// 3. Import all 19 Single Types into WordPress options (`pisl_single_<slug>`)
$singleSchemas = PISL_Schemas::get_single_types();
$singleCount = 0;

foreach ($singleSchemas as $slug => $cfg) {
    $rawSingle = $dataset['singleTypes'][$slug] ?? null;
    if (!is_array($rawSingle)) continue;

    $stored = [
        '_status'      => 'publish',
        '_id'          => (int) ($rawSingle['id'] ?? 1),
        '_documentId'  => (string) ($rawSingle['documentId'] ?? ('wp_single_' . $slug)),
        '_createdAt'   => (string) ($rawSingle['createdAt'] ?? gmdate('c')),
        '_updatedAt'   => (string) ($rawSingle['updatedAt'] ?? gmdate('c')),
        '_publishedAt' => (string) ($rawSingle['publishedAt'] ?? gmdate('c')),
    ];

    foreach ($cfg['fields'] as $fieldDef) {
        $fk = $fieldDef['key'];
        if (array_key_exists($fk, $rawSingle)) {
            $stored[$fk] = map_field_value_for_storage($fieldDef, $rawSingle[$fk], $mediaMapByUrl, $mediaMapByFile);
        }
    }

    if (!empty($cfg['has_seo'])) {
        $stored['seo'] = map_seo_for_storage($rawSingle, $mediaMapByUrl, $mediaMapByFile);
    }

    update_option('pisl_single_' . $slug, $stored, false);
    $singleCount++;
}

PISL_Security_CORS::bump_cache_version();

echo "Collections imported:\n";
foreach ($importCounts as $slug => $cnt) {
    echo "  $slug: $cnt\n";
}
echo "Single Types imported: $singleCount\n";
echo "Total attachments in Media Library: " . count($existingByOriginFile) . "\n";
