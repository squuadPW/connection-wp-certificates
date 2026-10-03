<?php
/**
 * Autoloader PSR-4 del namespace Squuad\ConnectionWpCertificates (src/), sin depender de Composer en producción.
 *
 * @package ConnectionWpCertificates
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Squuad\\ConnectionWpCertificates\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = CWPC_PATH . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
