<?php
/**
 * Plugin Name:       Connection WP Certificates
 * Plugin URI:        https://github.com/squuadPW/connection-wp-certificates
 * Description:       Connects this site to a WP Certificates platform through its API (squuad-cert/v1) to verify documents.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Squuad
 * Author URI:        https://squuad.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       connection-wp-certificates
 * Domain Path:       /languages
 *
 * @package ConnectionWpCertificates
 */

defined( 'ABSPATH' ) || exit;

define( 'CWPC_VERSION', '0.1.0' );
define( 'CWPC_FILE', __FILE__ );
define( 'CWPC_PATH', plugin_dir_path( __FILE__ ) );

require_once CWPC_PATH . 'includes/autoload.php';
require_once CWPC_PATH . 'includes/functions.php';

register_activation_hook( __FILE__, array( \Squuad\ConnectionWpCertificates\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Squuad\ConnectionWpCertificates\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \Squuad\ConnectionWpCertificates\Plugin::class, 'boot' ) );
