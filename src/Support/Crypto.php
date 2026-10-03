<?php
/**
 * Cifrado simétrico de secretos guardados en wp_options (libsodium; WordPress incluye sodium_compat).
 *
 * La llave se deriva de las sales de wp-config.php: si se cambian AUTH_KEY/AUTH_SALT, los secretos guardados dejan
 * de poder descifrarse y hay que volver a introducirlos.
 *
 * @package ConnectionWpCertificates
 */

namespace Squuad\ConnectionWpCertificates\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Cifra y descifra cadenas cortas.
 */
final class Crypto {

	/** Prefijo de versión del formato cifrado. */
	public const PREFIX = 'cwpc1:';

	/**
	 * Cifra un texto. Devuelve cadena vacía si el texto está vacío o el cifrado falla.
	 *
	 * @param string $plain Texto en claro.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		try {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );
		} catch ( \Throwable $e ) {
			return '';
		}

		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Descifra un texto cifrado con encrypt(). Devuelve cadena vacía si no es válido.
	 *
	 * @param string $encoded Texto cifrado.
	 */
	public static function decrypt( string $encoded ): string {
		if ( ! self::is_encrypted( $encoded ) ) {
			return '';
		}

		$raw = base64_decode( substr( $encoded, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		try {
			$plain = sodium_crypto_secretbox_open(
				substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ),
				self::key()
			);
		} catch ( \Throwable $e ) {
			return '';
		}

		return false === $plain ? '' : $plain;
	}

	/**
	 * Indica si el valor tiene el formato de un texto cifrado.
	 *
	 * @param string $value Valor guardado.
	 */
	public static function is_encrypted( string $value ): bool {
		return 0 === strpos( $value, self::PREFIX );
	}

	/**
	 * Llave de cifrado derivada de las sales del sitio.
	 */
	private static function key(): string {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . '|connection-wp-certificates', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
