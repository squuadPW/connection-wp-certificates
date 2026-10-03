# Connection WP Certificates

Plugin de WordPress que conecta un sitio con una plataforma **WP Certificates** mediante su API para sistemas conectados (`squuad-cert/v1`) para verificar documentos.

## Requisitos

- WordPress 6.0+ y PHP 7.4+.
- WP Certificates con la API v1 (rama `separacion-certificacion` o posterior) y una clave creada en **WP Certificates › Conexiones API** con el permiso «Verificar documentos».

## Configuración

1. Activa el plugin.
2. Ve a **Ajustes › WP Certificates**, escribe la URL del sitio de WP Certificates (https) y pega la clave `sqc_…`. La clave se guarda cifrada.
3. O, recomendado en producción, define ambos valores en `wp-config.php`:

```php
define( 'CWPC_BASE_URL', 'https://certificados.example.com' );
define( 'CWPC_API_KEY', 'sqc_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
```

## Uso

```php
$result = cwpc_verify_document( 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6' );

if ( is_wp_error( $result ) ) {
	// $result->get_error_code(): cwpc_document_not_found, cwpc_unauthorized, cwpc_rate_limited…
	return;
}

echo esc_html( $result['holder']['full_name'] );
echo esc_html( $result['document']['status'] ); // valid | expired
```

Los hooks disponibles y los códigos de error están documentados en [CLAUDE.md](CLAUDE.md).

## Desarrollo

```sh
composer install
composer lint
```

Auditoría con Claude Code: `/auditar [ruta]`.

## Licencia

GPL-2.0-or-later.
