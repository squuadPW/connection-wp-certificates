# connection-wp-certificates

Plugin de WordPress que conecta un sitio (p. ej. el sitio principal de la institución, en otro dominio) con una plataforma **WP Certificates** a través de su API para sistemas conectados, de servidor a servidor. Hoy verifica documentos por su código.

- Repo: https://github.com/squuadPW/connection-wp-certificates (rama `main`)
- Servidor: https://github.com/squuadPW/wp-certificates, rama **`separacion-certificacion`**

## Contrato de la API (lado servidor)

Fuente: `includes/rest-v1.php`, `includes/api-keys.php` y `public/endpoint.php` de wp-certificates. Si el servidor cambia, actualiza esta sección y `src/Api/Client.php`.

```
GET {base}/wp-json/squuad-cert/v1/documents/{code}
X-API-Key: sqc_<48 hex>          ← solo en la cabecera, nunca en la URL
```

- `code`: `^[A-Za-z0-9]{1,64}$`. Los códigos viejos tienen 6 caracteres y los nuevos 32 hex.
- **200** `{success: true, document: {simple_uuid, type, name_document, program_document, user, emission_date, expiration_date, tomo, folio, status: valid|expired}, holder: {name, middle_name, last_name, middle_last_name, first_name, full_name}, url}`
- **404** `{success: false, message}`: documento no encontrado. Distinto del 404 `{code: "rest_no_route"}`, que significa URL base mal configurada o servidor sin la API v1.
- **401** `{code: "squuad_cert_api_unauthorized"}`: clave ausente, falsa, revocada o sin el permiso `verify`. No revela si el código existe.
- Con clave válida no aplica el límite por IP de la API pública (`/wp-json/api/get-certificate`, 20 fallos por hora → 429). Aun así, el cliente trata 429.
- Las claves se crean en WP Certificates › Conexiones API. Se muestran una sola vez y el servidor solo guarda su SHA-256. Permisos actuales: `verify` (preparado para más vía `squuad_cert_api_scopes`).

## Arquitectura

```
connection-wp-certificates.php   cabecera, constantes CWPC_*, hooks de (des)activación, boot en plugins_loaded
includes/autoload.php            autoloader PSR-4 propio (sin Composer en producción)
includes/functions.php           API pública: cwpc_verify_document( $code )
src/Plugin.php                   contenedor de servicios (singleton), textdomain, activate/deactivate
src/Settings.php                 lectura de la opción cwpc_settings; constantes de wp-config tienen prioridad
src/Api/Client.php               cliente HTTP de squuad-cert/v1 → array | WP_Error
src/Admin/SettingsPage.php       Ajustes › WP Certificates (Settings API + sanitize)
src/Support/Crypto.php           cifrado libsodium de la clave guardada (llave derivada de wp_salt('auth'))
src/Support/Cache.php            claves de transients con número de generación; flush() invalida todo
uninstall.php                    borra opciones y transients cwpc_
```

- Namespace `Squuad\ConnectionWpCertificates\` ↔ `src/`. Prefijo global `cwpc_` / `CWPC_`. Text domain `connection-wp-certificates`.
- Opciones: `cwpc_settings` (sin autoload) y `cwpc_cache_generation`.
- Constantes opcionales en `wp-config.php`: `CWPC_BASE_URL`, `CWPC_API_KEY`. Se recomiendan en producción.
- La clave guardada en BD va cifrada. Si cambian las sales del sitio, deja de descifrarse y la pantalla pide introducirla de nuevo.

## Hooks públicos (no romper su firma)

| Hook | Tipo | Argumentos |
|---|---|---|
| `cwpc_loaded` | action | `Plugin $plugin` |
| `cwpc_request_args` | filter | `array $args, string $path, string $code` |
| `cwpc_verified_document` | filter | `array $result, string $code, array $body` |
| `cwpc_cache_ttl` | filter | `int $ttl, string $code, array $result` |
| `cwpc_request_failed` | action | `WP_Error $error, string $code` |
| `cwpc_settings_capability` | filter | `string $capability` (por defecto `manage_options`) |
| `cwpc_allow_insecure_url` | filter | `bool $allow` (por defecto `false`; solo para desarrollo local) |

Códigos de error `WP_Error` estables: `cwpc_invalid_code`, `cwpc_not_configured`, `cwpc_document_not_found`, `cwpc_endpoint_not_found`, `cwpc_unauthorized`, `cwpc_rate_limited`, `cwpc_connection_failed`, `cwpc_http_error`, `cwpc_invalid_response`, `cwpc_not_loaded`.

## Convenciones

- PHP ≥ 7.4 (compatible hasta 8.4), WordPress ≥ 6.0. Sin sintaxis exclusiva de PHP 8 mientras `Requires PHP` sea 7.4.
- WordPress Coding Standards (`phpcs.xml.dist`) con archivos PSR-4 en `src/`. Tabs, `array()`, Yoda, `snake_case`.
- Cadenas visibles en inglés y traducibles. Comentarios y documentación en español.
- Todo dato remoto es no confiable: se normaliza en el cliente y se escapa al imprimir.
- La clave de API nunca se imprime, nunca se registra en logs y nunca va en la URL. Las peticiones salen con `redirection => 0`.
- Todo fallo devuelve `WP_Error` y dispara `cwpc_request_failed`.

## Comandos

```sh
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;   # sintaxis
composer install && composer lint                                  # WPCS + PHPCompatibility (requiere composer)
```

## Auditoría

Todo cambio relevante pasa por `/auditar [ruta]`. Ese comando lanza en paralelo los 5 agentes de `.claude/agents/` (security-officer, performance-engineer, wpcs-core-evaluator, integration-specialist, qa-resilience-auditor) y entrega el reporte unificado: 🚨 Críticos, ⚠️ Advertencias, 💡 Integración, 📋 Veredicto.

## Hoja de ruta

- Botón «Probar conexión» en los ajustes.
- Shortcode o bloque de verificación pública, con nonce, límite de intentos por IP y sin exponer la clave.
- Endpoint REST propio `cwpc/v1` para el front.
- Nuevos permisos del servidor (p. ej. PDF) cuando wp-certificates los publique.
