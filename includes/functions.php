<?php
/**
 * API pública del plugin para temas y otros plugins.
 *
 * @package ConnectionWpCertificates
 */

defined( 'ABSPATH' ) || exit;

use Squuad\ConnectionWpCertificates\Plugin;

if ( ! function_exists( 'cwpc_verify_document' ) ) {
	/**
	 * Verifica un documento en la plataforma WP Certificates conectada.
	 *
	 * @param string $code Código de verificación del documento (letras y números, máx. 64).
	 * @return array{document: array<string, string>, holder: array<string, string>, url: string}|WP_Error
	 */
	function cwpc_verify_document( string $code ) {
		$plugin = Plugin::instance();
		if ( null === $plugin ) {
			return new WP_Error( 'cwpc_not_loaded', __( 'Connection WP Certificates is not loaded yet.', 'connection-wp-certificates' ) );
		}

		return $plugin->client()->verify_document( $code );
	}
}
