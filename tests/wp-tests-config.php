<?php
/**
 * WordPress test suite configuration.
 *
 * Values are read from environment variables so the same file works
 * in Docker, CI, and local setups.
 */

define( 'ABSPATH', getenv( 'WP_CORE_DIR' ) ? rtrim( getenv( 'WP_CORE_DIR' ), '/' ) . '/' : '/tmp/wordpress/' );

define( 'DB_NAME', getenv( 'DB_NAME' ) ?: 'wordpress_test' );
define( 'DB_USER', getenv( 'DB_USER' ) ?: 'root' );
define( 'DB_PASSWORD', getenv( 'DB_PASS' ) ?: 'root' );
define( 'DB_HOST', getenv( 'DB_HOST' ) ?: 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
