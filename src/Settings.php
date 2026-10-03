<?php
/**
 * Ajustes de la conexión con WP Certificates.
 *
 * La URL y la clave pueden fijarse en wp-config.php (CWPC_BASE_URL, CWPC_API_KEY), que tienen prioridad sobre lo
 * guardado en la base de datos. La clave guardada en la base de datos va cifrada (Support\Crypto).
 *
 * @package ConnectionWpCertificates
 */

namespace Squuad\ConnectionWpCertificates;

use Squuad\ConnectionWpCertificates\Support\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Lectura de los ajustes.
 */
final class Settings {

	public const OPTION = 'cwpc_settings';

	public const MIN_TIMEOUT = 1;
	public const MAX_TIMEOUT = 30;

	/**
	 * Valores por defecto.
	 *
	 * @return array{base_url: string, api_key: string, api_key_hint: string, timeout: int, cache_ttl: int}
	 */
	public static function defaults(): array {
		return array(
			'base_url'     => '',
			'api_key'      => '',
			'api_key_hint' => '',
			'timeout'      => 10,
			'cache_ttl'    => 15 * MINUTE_IN_SECONDS,
		);
	}

	/**
	 * Ajustes guardados, completados con los valores por defecto.
	 *
	 * @return array{base_url: string, api_key: string, api_key_hint: string, timeout: int, cache_ttl: int}
	 */
	public function all(): array {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * URL base del sitio WP Certificates, sin barra final.
	 */
	public function base_url(): string {
		$url = $this->base_url_is_constant() ? (string) CWPC_BASE_URL : (string) $this->all()['base_url'];

		return untrailingslashit( trim( $url ) );
	}

	/**
	 * Clave de API en claro (sqc_…), o cadena vacía si no hay o no se puede descifrar.
	 */
	public function api_key(): string {
		if ( $this->api_key_is_constant() ) {
			return trim( (string) CWPC_API_KEY );
		}

		return Crypto::decrypt( (string) $this->all()['api_key'] );
	}

	/**
	 * Indica si la URL viene de wp-config.php.
	 */
	public function base_url_is_constant(): bool {
		return defined( 'CWPC_BASE_URL' ) && '' !== (string) CWPC_BASE_URL;
	}

	/**
	 * Indica si la clave viene de wp-config.php.
	 */
	public function api_key_is_constant(): bool {
		return defined( 'CWPC_API_KEY' ) && '' !== (string) CWPC_API_KEY;
	}

	/**
	 * Indica si hay URL y clave utilizables.
	 */
	public function is_configured(): bool {
		return '' !== $this->base_url() && '' !== $this->api_key();
	}

	/**
	 * Indica si se permite una URL http://. Por defecto solo en entornos local y development (WP_ENVIRONMENT_TYPE),
	 * porque la clave viaja en cada petición.
	 */
	public function allows_insecure_url(): bool {
		$allow = in_array( wp_get_environment_type(), array( 'local', 'development' ), true );

		/**
		 * Filtra si se permite una URL http:// para WP Certificates.
		 *
		 * @param bool $allow Permitir http://. Por defecto true solo en entornos local y development.
		 */
		return (bool) apply_filters( 'cwpc_allow_insecure_url', $allow );
	}

	/**
	 * Indica si la URL base configurada usa https://.
	 */
	public function base_url_is_secure(): bool {
		return 'https' === wp_parse_url( $this->base_url(), PHP_URL_SCHEME );
	}

	/**
	 * Tiempo máximo de espera de cada petición, en segundos.
	 */
	public function timeout(): int {
		return max( self::MIN_TIMEOUT, min( self::MAX_TIMEOUT, (int) $this->all()['timeout'] ) );
	}

	/**
	 * Duración de la caché de respuestas correctas, en segundos (0 = sin caché).
	 */
	public function cache_ttl(): int {
		return max( 0, (int) $this->all()['cache_ttl'] );
	}
}
