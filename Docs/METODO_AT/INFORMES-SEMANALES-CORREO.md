# Informes semanales por el SMTP del servidor

Pedido de Luis del 31-oct-2026. La reunión semanal de los lunes (rutina «Eje · Reunión semanal AT») manda tres informes con el PDF adjunto desde la cuenta de AutomatizaTech:

- **Luis:** el informe completo.
- **Adriana (jefa de Diseño, Redes y Marketing):** solo su área.
- **Julio (colaborador):** sus dos proyectos y un resumen general de los demás.

Luis va con copia en los dos últimos.

## Cómo funciona

1. La rutina arma cada correo y su PDF en el computador de Luis con `Shared-Tools/at-agent-company/oficina/correos/armar_correo.py`. Usa la plantilla corporativa: logo real, azul `#063f76` y turquesa `#17b7b1`. El armador rechaza contenido con correos, teléfonos o montos.
2. `enviar_correo.py` entrega el correo a `POST /automatiza-tech/v1/informes/enviar`, con la cabecera `X-AT-Secret` (`AT_REST_SECRET`, la misma clave de Finanzas).
3. El servidor lo manda con `wp_mail`, o sea con el SMTP de AT, con el PDF adjunto y Reply-To al buzón que lee la rutina horaria.

Código: `wp-content/mu-plugins/at-informes-correo.php` (cargador) e `inc/informes-correo/` (`puras.php` valida y `cargar.php` registra la ruta y envía). Pruebas: `php tests/informes-correo/puras-test.php`.

## Resguardos

- Solo envía a la lista de la opción `at_informes_destinos`. El Reply-To solo puede ser el de `at_informes_responder_a`. Las direcciones viven en la base, no en el código, porque este repositorio es público.
- Asunto de una línea (sin inyección de cabeceras), HTML de hasta 300 KB y un PDF real (`%PDF-`) de hasta 3 MB, con nombre limpio.
- Tope de 30 envíos por hora. El PDF temporal se borra después de enviar.

## Apagar y volver atrás

Borrar `wp-content/mu-plugins/at-informes-correo.php` apaga la ruta. Para retirarlo del todo, borrar también `inc/informes-correo/` y las opciones `at_informes_destinos` y `at_informes_responder_a`. Si la ruta no existe, el script termina con código 3 y la rutina manda el correo por el conector de Gmail, sin PDF.

## Estado

EN PROD desde el 31-oct-2026, con OK de Luis. Verificado desde afuera: sin clave da 401, un destinatario fuera de la lista da 422, y el correo de prueba a Luis salió con el PDF. Las opiniones de Adriana y Julio (botón «Opinar y aportar» o respuesta al correo) las lee cada hora la rutina «Eje · Oficina AT al día».
