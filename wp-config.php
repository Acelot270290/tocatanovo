<?php
define( 'WP_CACHE', true );
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u635961131_tocata' );

/** MySQL database username */
define( 'DB_USER', 'u635961131_tocata' );

/** MySQL database password */
define( 'DB_PASSWORD', 'Acelot270290' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

define('FS_METHOD','direct');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'kof51xl5hk97mace65vc2y6bskd54owkyipyzkqagekfvrojie8iv1rsl1siqbo4' );
define( 'SECURE_AUTH_KEY',  'pvkatd4agqpovfglqusp1zeqpvzs7nuje7zc0l6wuxzksulkno7xgikhgo6zdzrn' );
define( 'LOGGED_IN_KEY',    'v7btlaf5ey9wwniae1xwfxyqnwfsqrgrg3xexvqgsqmzz23phuzrqgsmeazxysro' );
define( 'NONCE_KEY',        'txrl4ixcrngygvuggpd36t6kh85xejqgxkbyav8rqxjrmoirwdwsfo8is5trxsu1' );
define( 'AUTH_SALT',        'm3uhqa9ahjmm2v06cagcn7wf8wfzvetkvm3bvi30wje44ywunzuqrllssoohch8t' );
define( 'SECURE_AUTH_SALT', 'jkhsa8mwve2rvtaescp8ygelq8xmnrb7hlskzwa3rclcuoazzj8hgvzh4fsz65cv' );
define( 'LOGGED_IN_SALT',   'wy78kyslhwjzrhikkt30nfdls5lv4yzwaue2um7mxf1ulxgcvsxpbpjeve33stgx' );
define( 'NONCE_SALT',       'zcdstd5e8nryt0hjp4qsvw4ceop3a0p5l4kh2fzw00kvdm3sgcnnar1vkpe9oaqg' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wpzy_';

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
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
