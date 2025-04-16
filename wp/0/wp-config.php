<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'test' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         ';MwWs``y*ue#BQ6pO;ekeL.-g2z]2]bjL5v^%>gLq,%?d 5Pw(#zuL2AqR|{SOsg' );
define( 'SECURE_AUTH_KEY',  'RX<vPL4#ETP(-TS+M)V4%UC:W-|K-C^Bh0emR+22{{>Iv|-_[(^e| {t&>f8h^+`' );
define( 'LOGGED_IN_KEY',    '5J!l0qMmeo}^ |l^GD b~G|dH6d=7W0G,naZ3XtBnTcsE_vfM&oD5FZ.$NBY}j)e' );
define( 'NONCE_KEY',        '-uwv9D;=It;8Y  Ha}Q^eqVUUsRnY#kFvILt!D*xB3#jBrQ5RDM6sJj,<tD8:q;J' );
define( 'AUTH_SALT',        'K0bf4h+y?wUII5e&HXWipN+O&5KIgSD#{z(N+bdB%/^$ac##XwmXNdwUC>?jH~Ai' );
define( 'SECURE_AUTH_SALT', '`LuE(Mp~^E@DQ=b|3$(shE!63M638$i0aY/3@@D8B^[e9lK@8z+t.,j:n>-d4P16' );
define( 'LOGGED_IN_SALT',   'ZCdm-aWUk~.NrDEDh+moK1Nrpjh5~rl/>B<-skT:_<CoRe!2 -s,fDD<d>i$oFJ*' );
define( 'NONCE_SALT',       'A.cBIV9/~G.hd?Efs `a`%9pr*9gd8l!`%}483)m=POR94Or6UWdCLe~Ss<geQrO' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_1';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
