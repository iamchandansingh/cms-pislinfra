<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'pislinfra_wp' );

/** Database username */
define( 'DB_USER', 'pisl_wp_user' );

/** Database password */
define( 'DB_PASSWORD', 'PislWp_Dev2026_SecureDbPass!' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '.,T^%N][:S+4gNEO}1qE^:n}d^S^_tUb1v|lMN.G5yTq,+g_X6NGio}=3x.|h*62' );
define( 'SECURE_AUTH_KEY',   '6>SA[q7q~|w%2bDhowAIMC-VP({4GxBg~?+>@jrNXRC_6XD Q`,Rr2BsVYb e/$4' );
define( 'LOGGED_IN_KEY',     'S&4d)v*?U,;z%-_gx3/^>[A/f=R,9{Hf0?q9v;njBCY)p7S5uqi,}N`rM{$U$q_u' );
define( 'NONCE_KEY',         'TB[.~a?9[`&C:FAbf`ACs;[o`tkF&RD=`1ZMW=`X&~?|:0!h:|l)Ru]KQC[Glslo' );
define( 'AUTH_SALT',         'N..;UbDsBEsJY.MnAvdDP}.yXLNmtrtpA`<K#d{u](^xY=O7,yLS.?uZ=& F=n#3' );
define( 'SECURE_AUTH_SALT',  'm7f[:B;0F/}HhHH1!7)-HrR%g$~8s<=WJ2)vFy|P#iKTv[LLqa{ZZ4*^+>zKXC35' );
define( 'LOGGED_IN_SALT',    'v8+A;UI]3y.fV.]<TW,6E-J+MS%sG,72cC`p+N4 dbC9x7?Dqm;8i7T|nQ!JXTwb' );
define( 'NONCE_SALT',        '7ZSk(UKRDDh++Q;Z$mwkp8+bYg^P,T(%>f2<aZ]l|}RGc#PYaosQf*!<(pyc1xYu' );
define( 'WP_CACHE_KEY_SALT', 'TD=X8iQqBh>a>2?IcQK v7-sG3^u@y6U4!^>H?Ee6KXW,l%EFvuv_YV^r]esihK9' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'pisl_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_HOME', (defined('WP_CLI') && WP_CLI) ? 'http://127.0.0.1:8080' : (isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : 'http://127.0.0.1:8080'));
define('WP_SITEURL', WP_HOME);
define('PISL_FRONTEND_URL', getenv('FRONTEND_URL') ?: ((isset($_SERVER['HTTP_HOST']) && !in_array($_SERVER['HTTP_HOST'], ['127.0.0.1:8080', 'localhost:8080'], true)) ? 'https://pislinfra.com' : 'http://127.0.0.1:5173'));
define('PISL_CORS_ORIGINS', getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost:5173,http://127.0.0.1:5173,http://localhost:4173,https://pislinfra.com,https://www.pislinfra.com');
define('WP_ENVIRONMENT_TYPE', 'local');
define('FS_METHOD', 'direct');
define('DISALLOW_FILE_EDIT', true);
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
	define('FORCE_SSL_ADMIN', true);
}


/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
