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
define( 'DB_NAME', 'wordpress_test' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'mysql' );

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
define( 'AUTH_KEY',          'TOrg5h=m=sQptUT@k$:*xYzZUTU.YGu0/:Y61Y*Of-D@W,C9&MAR/EOJ-57YEPi^' );
define( 'SECURE_AUTH_KEY',   'LcuC!?Mahw3RWq^_Pn9sj7eEz)dKi3GN>dgDR_g>nvC^34-*W!N~u3B/Bjk1NL3L' );
define( 'LOGGED_IN_KEY',     '#mf?!]5^]g%POL/ oIdG,YBIC<hK*/L].en)g k1UO)fn.6o+UG%yJ,cE5TqR(2P' );
define( 'NONCE_KEY',         'r~i7ni&!J<c1c%8XF)]_.f:Mi*P?!B#J.+|X>_;X+$UM0KYAGsD?Qu+yuC$[6w%4' );
define( 'AUTH_SALT',         'lrn3qiQwD{/1KB];kk}#7UG*2Y#z1*?1R<C?_.Y8<u&fLnx`:8m6e+)HqcHR#mei' );
define( 'SECURE_AUTH_SALT',  'ZZ,&y^C5;^~zj!reOh0|a*1%>sV^2<Xgdr|ol>Kh;`64[BiJt=8A,YKr#IgVHD8t' );
define( 'LOGGED_IN_SALT',    'i~fYl<~%I$>{V@x,U|VjR)MD/_d?GL|Bm1a>l|FFQD@0xv~zt|oHVZRA_y~]1(tV' );
define( 'NONCE_SALT',        ';A|4>QN1LA]wU,Dg1mPj)]s!+*P_y]ZEuWEs`bF;Q6!/v7j5C~RQhyKNEY||_;q`' );
define( 'WP_CACHE_KEY_SALT', ']gdtZRn@OtJtf,N?m4hkfccqn/&KZp82_.dk8otI/$3G$lb6M|{J^O;U=Jgk!vvw' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
