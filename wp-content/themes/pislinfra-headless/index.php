<?php
/**
 * Pislinfra Headless Theme Entrypoint
 * Public frontend traffic to /cms or any sub-route is redirected to the main website.
 * Only /cms/wp-admin/ and /cms/wp-login.php are accessible for admin login.
 */
if (!defined('ABSPATH')) {
    exit;
}

$frontend_url = defined('PISL_FRONTEND_URL') ? PISL_FRONTEND_URL : 'https://pislinfra.com/';
wp_redirect($frontend_url, 301);
exit;
