<?php
/**
 * Arranque del plugin: crea los servicios y registra los hooks.
 *
 * @package ConnectionWpCertificates
 */

namespace Squuad\ConnectionWpCertificates;

use Squuad\ConnectionWpCertificates\Admin\SettingsPage;
use Squuad\ConnectionWpCertificates\Api\Client;
use Squuad\ConnectionWpCertificates\Support\Cache;

defined( 'ABSPATH' ) || exit;

/**
 * Contenedor mínimo de servicios del plugin.
 */
final class Plugin {

	/**
	 * Instancia única.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Ajustes de la conexión.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Cliente de la API de WP Certificates.
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Construye los servicios.
	 */
	private function __construct() {
		$this->settings = new Settings();
		$this->client   = new Client( $this->settings );
	}

	/**
	 * Punto de entrada (plugins_loaded).
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self();
		self::$instance->register_hooks();
	}

	/**
	 * Instancia en ejecución, o null si aún no arrancó.
	 */
	public static function instance(): ?self {
		return self::$instance;
	}

	/**
	 * Ajustes de la conexión.
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Cliente de la API.
	 */
	public function client(): Client {
		return $this->client;
	}

	/**
	 * Registra los hooks del plugin.
	 */
	private function register_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			( new SettingsPage( $this->settings ) )->register();
		}

		/**
		 * El plugin terminó de arrancar.
		 *
		 * @param Plugin $plugin Instancia del plugin.
		 */
		do_action( 'cwpc_loaded', $this );
	}

	/**
	 * Carga las traducciones.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'connection-wp-certificates', false, dirname( plugin_basename( CWPC_FILE ) ) . '/languages' );
	}

	/**
	 * Activación: crea la opción sin autoload (solo se lee al consultar la API o en el admin).
	 */
	public static function activate(): void {
		add_option( Settings::OPTION, Settings::defaults(), '', false );
	}

	/**
	 * Desactivación: invalida la caché de respuestas.
	 */
	public static function deactivate(): void {
		Cache::flush();
	}
}
