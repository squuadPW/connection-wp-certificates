<?php
/**
 * Pantalla Ajustes > WP Certificates (Settings API: nonce y permiso los gestiona options.php).
 *
 * @package ConnectionWpCertificates
 */

namespace Squuad\ConnectionWpCertificates\Admin;

use Squuad\ConnectionWpCertificates\Settings;
use Squuad\ConnectionWpCertificates\Support\Cache;
use Squuad\ConnectionWpCertificates\Support\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Registro, saneamiento y vista de los ajustes.
 */
final class SettingsPage {

	public const SLUG = 'connection-wp-certificates';

	/** Prefijo que WP Certificates da a sus claves de API. */
	private const API_KEY_PREFIX = 'sqc_';

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
	 * Registra los hooks del admin.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CWPC_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Permiso necesario para ver y guardar los ajustes.
	 */
	public static function capability(): string {
		/**
		 * Filtra el permiso necesario para gestionar la conexión.
		 *
		 * @param string $capability Permiso.
		 */
		return (string) apply_filters( 'cwpc_settings_capability', 'manage_options' );
	}

	/**
	 * Añade la pantalla bajo Ajustes.
	 */
	public function add_menu(): void {
		add_options_page(
			__( 'WP Certificates connection', 'connection-wp-certificates' ),
			__( 'WP Certificates', 'connection-wp-certificates' ),
			self::capability(),
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enlace «Ajustes» en la lista de plugins.
	 *
	 * @param string[] $links Enlaces actuales.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::SLUG ) ),
				esc_html__( 'Settings', 'connection-wp-certificates' )
			)
		);

		return $links;
	}

	/**
	 * Registra la opción, la sección y los campos.
	 */
	public function register_settings(): void {
		register_setting(
			self::SLUG,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);

		// options.php comprueba manage_options salvo que se declare el permiso de esta página.
		add_filter(
			'option_page_capability_' . self::SLUG,
			static function () {
				return self::capability();
			}
		);

		add_settings_section( 'cwpc_connection', __( 'Connection', 'connection-wp-certificates' ), '__return_null', self::SLUG );

		add_settings_field( 'cwpc_base_url', __( 'WP Certificates URL', 'connection-wp-certificates' ), array( $this, 'field_base_url' ), self::SLUG, 'cwpc_connection', array( 'label_for' => 'cwpc_base_url' ) );
		add_settings_field( 'cwpc_api_key', __( 'API key', 'connection-wp-certificates' ), array( $this, 'field_api_key' ), self::SLUG, 'cwpc_connection', array( 'label_for' => 'cwpc_api_key' ) );
		add_settings_field( 'cwpc_timeout', __( 'Timeout (seconds)', 'connection-wp-certificates' ), array( $this, 'field_timeout' ), self::SLUG, 'cwpc_connection', array( 'label_for' => 'cwpc_timeout' ) );
		add_settings_field( 'cwpc_cache_ttl', __( 'Cache (minutes)', 'connection-wp-certificates' ), array( $this, 'field_cache_ttl' ), self::SLUG, 'cwpc_connection', array( 'label_for' => 'cwpc_cache_ttl' ) );
	}

	/**
	 * Sanea los ajustes enviados.
	 *
	 * La clave en claro llega en api_key_new y se guarda cifrada en api_key; si llega vacía se conserva la anterior.
	 * WordPress puede llamar a este callback dos veces al crear la opción: la segunda vez recibe el resultado de la
	 * primera (con api_key ya cifrada), y por eso un valor cifrado se acepta tal cual.
	 *
	 * @param mixed $input Valores enviados.
	 * @return array
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$previous = $this->settings->all();
		$output   = $previous;

		// URL base.
		$url = isset( $input['base_url'] ) ? esc_url_raw( trim( (string) wp_unslash( $input['base_url'] ) ), array( 'https', 'http' ) ) : '';
		if ( '' === $url ) {
			$output['base_url'] = '';
		} elseif ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) || $this->settings->allows_insecure_url() ) {
			$output['base_url'] = untrailingslashit( $url );
		} else {
			add_settings_error( Settings::OPTION, 'cwpc_base_url', __( 'The URL must start with https:// because the API key is sent with every request. http:// is only allowed when WP_ENVIRONMENT_TYPE is "local" or "development".', 'connection-wp-certificates' ) );
		}

		// Clave de API.
		$new_key = isset( $input['api_key_new'] ) ? trim( sanitize_text_field( (string) wp_unslash( $input['api_key_new'] ) ) ) : '';
		if ( ! empty( $input['api_key_clear'] ) ) {
			$output['api_key']      = '';
			$output['api_key_hint'] = '';
		} elseif ( '' !== $new_key ) {
			if ( 0 !== strpos( $new_key, self::API_KEY_PREFIX ) || strlen( $new_key ) > 128 ) {
				add_settings_error( Settings::OPTION, 'cwpc_api_key', __( 'The API key is not valid. Copy it from WP Certificates > API connections (it starts with sqc_).', 'connection-wp-certificates' ) );
			} else {
				$encrypted = Crypto::encrypt( $new_key );
				if ( '' === $encrypted ) {
					add_settings_error( Settings::OPTION, 'cwpc_api_key', __( 'The API key could not be encrypted and was not saved.', 'connection-wp-certificates' ) );
				} else {
					$output['api_key']      = $encrypted;
					$output['api_key_hint'] = substr( $new_key, 0, 12 );
				}
			}
		} elseif ( isset( $input['api_key'] ) && Crypto::is_encrypted( (string) $input['api_key'] ) ) {
			$output['api_key']      = (string) $input['api_key'];
			$output['api_key_hint'] = isset( $input['api_key_hint'] ) ? sanitize_text_field( (string) $input['api_key_hint'] ) : '';
		}

		// Tiempo de espera y caché.
		if ( isset( $input['timeout'] ) ) {
			$output['timeout'] = max( Settings::MIN_TIMEOUT, min( Settings::MAX_TIMEOUT, absint( $input['timeout'] ) ) );
		}
		if ( isset( $input['cache_ttl_minutes'] ) ) {
			$output['cache_ttl'] = min( DAY_IN_SECONDS, absint( $input['cache_ttl_minutes'] ) * MINUTE_IN_SECONDS );
		} elseif ( isset( $input['cache_ttl'] ) ) {
			$output['cache_ttl'] = min( DAY_IN_SECONDS, absint( $input['cache_ttl'] ) );
		}

		$output = array_intersect_key( $output, Settings::defaults() );

		// Otra URL u otra clave: las respuestas en caché ya no valen.
		if ( $output['base_url'] !== $previous['base_url'] || $output['api_key'] !== $previous['api_key'] ) {
			Cache::flush();
		}

		return $output;
	}

	/**
	 * Campo: URL base.
	 */
	public function field_base_url(): void {
		if ( $this->settings->base_url_is_constant() ) {
			printf(
				'<code>%s</code><p class="description">%s</p>',
				esc_html( $this->settings->base_url() ),
				esc_html__( 'Defined in wp-config.php (CWPC_BASE_URL).', 'connection-wp-certificates' )
			);
		} else {
			printf(
				'<input type="url" class="regular-text code" id="cwpc_base_url" name="%1$s[base_url]" value="%2$s" placeholder="https://certificates.example.com" />'
				. '<p class="description">%3$s</p>',
				esc_attr( Settings::OPTION ),
				esc_attr( $this->settings->all()['base_url'] ),
				esc_html__( 'Address of the site where WP Certificates is installed.', 'connection-wp-certificates' )
			);
		}

		if ( '' !== $this->settings->base_url() && ! $this->settings->base_url_is_secure() ) {
			$message = $this->settings->allows_insecure_url()
				? __( 'Testing mode: this URL uses http://, allowed only because this site is a local or development environment. The API key travels unencrypted; use https:// in production.', 'connection-wp-certificates' )
				: __( 'This URL uses http:// and requests are blocked in this environment. Change it to https://.', 'connection-wp-certificates' );

			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * Campo: clave de API (nunca se muestra la guardada).
	 */
	public function field_api_key(): void {
		if ( $this->settings->api_key_is_constant() ) {
			echo '<p class="description">' . esc_html__( 'Defined in wp-config.php (CWPC_API_KEY).', 'connection-wp-certificates' ) . '</p>';
			return;
		}

		$all  = $this->settings->all();
		$hint = (string) $all['api_key_hint'];

		printf(
			'<input type="password" class="regular-text code" id="cwpc_api_key" name="%1$s[api_key_new]" value="" autocomplete="new-password" spellcheck="false" placeholder="%2$s" />',
			esc_attr( Settings::OPTION ),
			esc_attr( '' !== $all['api_key'] ? __( 'Leave empty to keep the saved key', 'connection-wp-certificates' ) : 'sqc_…' )
		);

		if ( '' !== $all['api_key'] ) {
			$status = '' !== $this->settings->api_key()
				/* translators: %s: first characters of the saved API key. */
				? sprintf( __( 'A key is saved (%s…).', 'connection-wp-certificates' ), $hint )
				: __( 'The saved key can no longer be decrypted (the site salts changed). Enter it again.', 'connection-wp-certificates' );

			printf(
				'<p class="description">%1$s</p><p><label><input type="checkbox" name="%2$s[api_key_clear]" value="1" /> %3$s</label></p>',
				esc_html( $status ),
				esc_attr( Settings::OPTION ),
				esc_html__( 'Remove the saved key', 'connection-wp-certificates' )
			);
		}

		echo '<p class="description">' . esc_html__( 'Create it in WP Certificates > API connections with the "Verify documents" permission. It is stored encrypted.', 'connection-wp-certificates' ) . '</p>';
	}

	/**
	 * Campo: tiempo de espera.
	 */
	public function field_timeout(): void {
		printf(
			'<input type="number" class="small-text" id="cwpc_timeout" name="%1$s[timeout]" value="%2$d" min="%3$d" max="%4$d" step="1" />',
			esc_attr( Settings::OPTION ),
			absint( $this->settings->timeout() ),
			absint( Settings::MIN_TIMEOUT ),
			absint( Settings::MAX_TIMEOUT )
		);
	}

	/**
	 * Campo: duración de la caché.
	 */
	public function field_cache_ttl(): void {
		printf(
			'<input type="number" class="small-text" id="cwpc_cache_ttl" name="%1$s[cache_ttl_minutes]" value="%2$d" min="0" max="1440" step="1" /><p class="description">%3$s</p>',
			esc_attr( Settings::OPTION ),
			absint( intdiv( $this->settings->cache_ttl(), MINUTE_IN_SECONDS ) ),
			esc_html__( 'How long a verified document is kept in cache. 0 disables the cache.', 'connection-wp-certificates' )
		);
	}

	/**
	 * Vista de la pantalla.
	 */
	public function render(): void {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::SLUG );
				do_settings_sections( self::SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
