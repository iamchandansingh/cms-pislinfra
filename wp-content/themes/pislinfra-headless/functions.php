<?php
/**
 * Pislinfra Headless Theme Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);

    // Match Strapi responsive image format sizes cleanly
    add_image_size('pisl_small', 500, 500, false);
    add_image_size('pisl_medium', 750, 750, false);
    add_image_size('pisl_large', 1200, 1200, false);
});

add_filter('upload_mimes', function ($mimes) {
    $mimes['webp'] = 'image/webp';
    $mimes['avif'] = 'image/avif';
    $mimes['svg']  = 'image/svg+xml';
    $mimes['pdf']  = 'application/pdf';
    $mimes['mp4']  = 'video/mp4';
    return $mimes;
});

// Any public visit to /cms or any sub-route (e.g. /cms/dfd, /cms/xyz)
// automatically redirects straight to the main frontend website (https://pislinfra.com/).
// Only /cms/wp-admin/ and /cms/wp-login.php remain accessible for admin login.
add_action('template_redirect', function () {
    if (!is_admin() && (!defined('REST_REQUEST') || !REST_REQUEST)) {
        $frontend_url = defined('PISL_FRONTEND_URL') ? PISL_FRONTEND_URL : 'https://pislinfra.com/';
        wp_redirect($frontend_url, 301);
        exit;
    }
});
