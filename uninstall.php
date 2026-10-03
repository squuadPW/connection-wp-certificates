<?php
/**
 * Desinstalación: borra las opciones y la caché del plugin.
 *
 * @package ConnectionWpCertificates
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'cwpc_settings' );
delete_option( 'cwpc_cache_generation' );

// Transients de respuestas en caché (con caché de objetos persistente no están en la tabla y expiran solos).
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_cwpc_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cwpc_' ) . '%'
	)
);
