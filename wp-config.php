<?php
/**
 * Basic WordPress configuration file (sanitized template)
 * Update salts below and ensure DB_* constants are set by your environment/host.
 */

// Database settings may be provided by your host.
// define('DB_NAME',     'your_db');
// define('DB_USER',     'your_user');
// define('DB_PASSWORD', 'your_pass');
// define('DB_HOST',     'localhost');
// define('DB_CHARSET',  'utf8');
// define('DB_COLLATE',  '');

/**#@+
 * Authentication Unique Keys and Salts.
 * Generate from https://api.wordpress.org/secret-key/1.1/salt/
 */
define('AUTH_KEY',         'w{6(vGa|]2U/_~fjevTpJJ=h@+-pO.**p|1(i;]5U8o:4A0`U%%+VHIOs&7:m6SC');
define('SECURE_AUTH_KEY',  '9LZ8MqW4+qt#:VT^u>UN%{s.=4`w9zAT,7@i!R5+Udvx9V[5[h=7_O9Jvh6MeD_0');
define('LOGGED_IN_KEY',    'P!#p|.C$|=N=FmFkN5ph^ <<MC)4v X][o-O;N#qQBGRx4L{7dg(6*^<`Y1X::0|');
define('NONCE_KEY',        'wSqcOK/1mvX+)j( bXlAM5fs*,T&EWZ70JYG32I1;>bA?,,!*^&&=u,-OGcp`i|i');
define('AUTH_SALT',        '] +=[zOHMb8;|ALs(.8jLfR3A=F+K-ealW_UbRH1a_%<Fig3BmYYG|t^?aLwg0cH');
define('SECURE_AUTH_SALT', '?y*[ZB-/R8+*J0;*8D:,EdB<p^qMxQ1 %?FZmKnsFVx_Ur2J:`_8J_:f)d.m?xi?');
define('LOGGED_IN_SALT',   '9S6&<qEP/(F*Gc%d4VV#jGV|UtSpD*&%0yt_j1+8 UmDP0kD-LsJt:hR1]()<@nn');
define('NONCE_SALT',       '<s*V1 :ad!8KEp2XrI>7|dR|fhQ8V>]RV74kaqd[5g{HL*+K7 c =2*<yWQx2I`c');
/**#@-*/

/**
 * WordPress Database Table prefix.
 */
$table_prefix  = 'wp_';

/**
 * Debugging: off in production (log to file)
 */
if ( ! defined( 'WP_DEBUG' ) ) {
    define('WP_DEBUG', false);
}

if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
    define('WP_DEBUG_DISPLAY', false);
}

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
    define('WP_DEBUG_LOG', true);
}

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') ) define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */

// Enable query logging so Query Monitor doesn't hit null
if ( ! defined('SAVEQUERIES') ) {
    define('SAVEQUERIES', false);
}

require_once ABSPATH . 'wp-settings.php';
