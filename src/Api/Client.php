<?php
/**
 * Cliente de la API para sistemas conectados de WP Certificates (squuad-cert/v1).
 *
 * Contrato (wp-certificates, rama separacion-certificacion, includes/rest-v1.php):
 *   GET {base}/wp-json/squuad-cert/v1/documents/{code}   cabecera X-API-Key: sqc_…
 *   200 {success: true, document: {...}, holder: {...}, url}
 *   404 {success: false, message}   documento no encontrado
 *   401 WP_Error squuad_cert_api_unauthorized   clave ausente, falsa, revocada o sin el permiso «verify»
 *
 * @package ConnectionWpCertificates
 */

namespace Squuad\ConnectionWpCertificates\Api;

use Squuad\ConnectionWpCertificates\Settings;
use Squuad\ConnectionWpCertificates\Support\Cache;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Consultas a la plataforma WP Certificates.
 */
final class Client {

	public const REST_BASE = '/wp-json/squuad-cert/v1';

	/** Mismo patrón que valida la ruta en el servidor. */
	public const CODE_PATTERN = '/^[A-Za-z0-9]{1,64}$/';

	/**
	 * Ajustes de la conexión.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Ajustes de la conexión.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Verifica un documento por su código.
	 *
	 * @param string $code Código de verificación.
	 * @return array{document: array<string, string>, holder: array<string, string>, url: string}|WP_Error
	 */
	public function verify_document( string $code ) {
		$code = trim( $code );
		if ( ! preg_match( self::CODE_PATTERN, $code ) ) {
			return $this->fail(
				new WP_Error( 'cwpc_invalid_code', __( 'The document code is not valid.', 'connection-wp-certificates' ), array( 'status' => 400 ) ),
				$code
			);
		}

		if ( ! $this->settings->is_configured() ) {
			return $this->fail(
				new WP_Error( 'cwpc_not_configured', __( 'The connection with WP Certificates is not configured.', 'connection-wp-certificates' ), array( 'status' => 503 ) ),
				$code
			);
		}

		// La URL pudo guardarse en local y llegar a producción (copia de la BD) o venir de wp-config: se revisa aquí.
		if ( ! $this->settings->base_url_is_secure() && ! $this->settings->allows_insecure_url() ) {
			return $this->fail(
				new WP_Error( 'cwpc_insecure_url', __( 'The WP Certificates URL must use https:// in this environment. The request was not sent.', 'connection-wp-certificates' ), array( 'status' => 503 ) ),
				$code
			);
		}

		$base      = $this->settings->base_url();
		$cache_key = Cache::key( 'doc', $base, $code );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = $this->request( '/documents/' . rawurlencode( $code ), $code );
		if ( is_wp_error( $response ) ) {
			return $this->fail(
				new WP_Error(
					'cwpc_connection_failed',
					/* translators: %s: error message from the HTTP layer. */
					sprintf( __( 'Could not connect to WP Certificates: %s', 'connection-wp-certificates' ), $response->get_error_message() ),
					array(
						'status'         => 503,
						'original_error' => $response->get_error_code(),
					)
				),
				$code
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $status ) {
			return $this->fail( $this->error_from_status( $status, $response, is_array( $body ) ? $body : array() ), $code );
		}

		if ( ! is_array( $body ) || empty( $body['success'] ) || ! isset( $body['document'] ) || ! is_array( $body['document'] ) ) {
			return $this->fail(
				new WP_Error( 'cwpc_invalid_response', __( 'WP Certificates returned an unexpected response.', 'connection-wp-certificates' ), array( 'status' => 502 ) ),
				$code
			);
		}

		$result = array(
			'document' => self::string_map( $body['document'] ),
			'holder'   => self::string_map( isset( $body['holder'] ) && is_array( $body['holder'] ) ? $body['holder'] : array() ),
			'url'      => esc_url_raw( isset( $body['url'] ) && is_string( $body['url'] ) ? $body['url'] : '' ),
		);

		/**
		 * Filtra el documento verificado antes de guardarlo en caché y devolverlo.
		 *
		 * @param array  $result Datos normalizados (document, holder, url).
		 * @param string $code   Código consultado.
		 * @param array  $body   Cuerpo original de la respuesta.
		 */
		$result = (array) apply_filters( 'cwpc_verified_document', $result, $code, $body );

		/**
		 * Filtra cuántos segundos se guarda en caché un documento verificado (0 = sin caché).
		 *
		 * @param int    $ttl    Segundos.
		 * @param string $code   Código consultado.
		 * @param array  $result Datos del documento.
		 */
		$ttl = (int) apply_filters( 'cwpc_cache_ttl', $this->settings->cache_ttl(), $code, $result );
		if ( $ttl > 0 ) {
			set_transient( $cache_key, $result, $ttl );
		}

		return $result;
	}

	/**
	 * Hace una petición GET autenticada a la API.
	 *
	 * @param string $path Ruta relativa a REST_BASE.
	 * @param string $code Código consultado (contexto para los filtros).
	 * @return array|WP_Error Respuesta de wp_remote_get().
	 */
	private function request( string $path, string $code ) {
		$args = array(
			'timeout'     => $this->settings->timeout(),
			// Sin redirecciones: la clave no debe viajar a otro destino.
			'redirection' => 0,
			'headers'     => array(
				'X-API-Key' => $this->settings->api_key(),
				'Accept'    => 'application/json',
			),
			'user-agent'  => 'ConnectionWpCertificates/' . CWPC_VERSION . '; ' . home_url( '/' ),
		);

		/**
		 * Filtra los argumentos de la petición HTTP a WP Certificates.
		 *
		 * @param array  $args Argumentos para wp_remote_get().
		 * @param string $path Ruta relativa a la API.
		 * @param string $code Código consultado.
		 */
		$args = (array) apply_filters( 'cwpc_request_args', $args, $path, $code );

		return wp_remote_get( $this->settings->base_url() . self::REST_BASE . $path, $args );
	}

	/**
	 * Traduce un código HTTP distinto de 200 a un WP_Error.
	 *
	 * @param int   $status   Código HTTP.
	 * @param array $response Respuesta de wp_remote_get().
	 * @param array $body     Cuerpo JSON decodificado (vacío si no era JSON).
	 */
	private function error_from_status( int $status, array $response, array $body ): WP_Error {
		switch ( $status ) {
			case 404:
				// WordPress responde 404 rest_no_route si la URL base es incorrecta o WP Certificates no tiene la API v1.
				if ( isset( $body['code'] ) && 'rest_no_route' === $body['code'] ) {
					return new WP_Error( 'cwpc_endpoint_not_found', __( 'The WP Certificates API was not found at the configured URL. Check the URL and that WP Certificates is up to date.', 'connection-wp-certificates' ), array( 'status' => 502 ) );
				}


				return new WP_Error( 'cwpc_document_not_found', __( 'Document not found.', 'connection-wp-certificates' ), array( 'status' => 404 ) );
			case 401:
			case 403:
				return new WP_Error( 'cwpc_unauthorized', __( 'WP Certificates rejected the API key. Check that it is valid, not revoked and has the "Verify documents" permission.', 'connection-wp-certificates' ), array( 'status' => $status ) );
			case 429:
				return new WP_Error(
					'cwpc_rate_limited',
					__( 'Too many requests to WP Certificates. Please try again later.', 'connection-wp-certificates' ),
					array(
						'status'      => 429,
						'retry_after' => (int) wp_remote_retrieve_header( $response, 'retry-after' ),
					)
				);
			default:
				return new WP_Error(
					'cwpc_http_error',
					/* translators: %d: HTTP status code. */
					sprintf( __( 'WP Certificates responded with HTTP status %d.', 'connection-wp-certificates' ), $status ),
					array( 'status' => $status >= 500 ? 502 : $status )
				);
		}
	}

	/**
	 * Notifica un fallo y lo devuelve.
	 *
	 * @param WP_Error $error Error.
	 * @param string   $code  Código consultado.
	 */
	private function fail( WP_Error $error, string $code ): WP_Error {
		/**
		 * Una verificación falló (validación, configuración, red o respuesta del servidor).
		 *
		 * @param WP_Error $error Error.
		 * @param string   $code  Código consultado.
		 */
		do_action( 'cwpc_request_failed', $error, $code );

		return $error;
	}

	/**
	 * Deja solo los valores escalares de un arreglo, como texto plano.
	 *
	 * @param array $data Datos recibidos.
	 * @return array<string, string>
	 */
	private static function string_map( array $data ): array {
		$clean = array();
		foreach ( $data as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$clean[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}
}
