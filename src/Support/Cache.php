<?php
/**
 * Caché de respuestas de la API con transients.
 *
 * Las claves llevan un número de generación: flush() lo incrementa y deja inservibles todas las entradas anteriores
 * de una vez (expiran solas), sin recorrer la tabla de opciones.
 *
 * @package ConnectionWpCertificates
 */

namespace Squuad\ConnectionWpCertificates\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Claves e invalidación de la caché.
 */
final class Cache {

	public const GENERATION_OPTION = 'cwpc_cache_generation';
	public const TRANSIENT_PREFIX  = 'cwpc_';

	/**
	 * Nombre del transient para un recurso.
	 *
	 * @param string $type  Tipo de recurso (p. ej. «doc»).
	 * @param string ...$parts Partes que identifican el recurso (URL base, código…).
	 */
	public static function key( string $type, string ...$parts ): string {
		$generation = (int) get_option( self::GENERATION_OPTION, 0 );

		return self::TRANSIENT_PREFIX . $type . '_' . md5( $generation . '|' . implode( '|', $parts ) );
	}

	/**
	 * Invalida todas las entradas en caché.
	 */
	public static function flush(): void {
		update_option( self::GENERATION_OPTION, (int) get_option( self::GENERATION_OPTION, 0 ) + 1, false );
	}
}
