# CumpleClick — Aceptación de Términos y Condiciones + firma electrónica simple

- Fecha: 2026-09-05
- Rama: `feat/cumpleclick-aceptacion-terminos` (creada desde `docs/estudio-exhaustivo-2026-09-04`)
- Owner: Luis Miguel · Implementó: Claude (orquestador principal)
- Clase/riesgo: C2 (nueva tabla + regla de negocio que bloquea la activación de fiestas; reversible con `013_plan_acceptances.down.php`)
- Estado: **construido y verificado en LOCAL. Nada de esto está en PROD.**

> Los textos legales de `CumpleBooth/public/legal/` son **borradores** preparados por el equipo técnico; deben ser revisados por un abogado o abogada habilitado en Chile antes de usarse con clientes. Este documento no es asesoría legal.

## 1. Hallazgos en el código real (dónde se "cierra" un plan hoy)

| Qué | Dónde | Hallazgo |
|---|---|---|
| Cierre operativo de un plan | `CumpleBooth/public/admin/index.php` acción `guardar` (`$activa`, `service_plan` booth/full) | No existía cotización, contrato ni aceptación. "Cerrar" un plan era marcar la fiesta como **activa** (la tablet solo sirve fiestas activas). Ese es el punto natural de bloqueo. |
| Planes comerciales | `CumpleBooth/sitio/index.html` (sección `#precios`) | Plan Mágico $34.995 (antes $69.990) y Plan Premium $49.995 (antes $99.990), temática a medida +$25.000. Internamente son `service_plan = booth|full` (`cc_parties`). No hay tabla de precios en BD. |
| Leads / solicitudes | `CumpleBooth/sitio/api/contacto.php` → `cb_create_lead()` en `public/lib.leads.php` → tabla `cc_leads` (migración `006_public_leads.php`) | Ya guarda evidencia de consentimiento (`privacy_version`, `consented_at`, `ip_hmac`, `user_agent_hmac`). No envía correo ni genera contrato. |
| Tokens/HMAC/sesiones | `public/lib.php`: `cb_opaque_token()`, `cb_hash_token()`, `cb_hmac()`, `cb_rate_limit()`, `cb_private_dir()`; `public/admin/invitations.php` (login + CSRF por página) | Patrón reutilizado tal cual: token 128 bits en claro solo una vez, SHA-256 en BD, archivos en directorio privado fuera del webroot. |
| Módulo de contratos AT | `contracts/contract-service.php`, `sign-contract.php`, `setup-contracts-db.php`; doc `Docs/MASTER/09_MODULO_CONTRATOS.md` | Firma doble con canvas, hash SHA-256, IP/UA, rotación de token al firmar. **Acoplado a WordPress** (`wp-load.php`, `$wpdb`, `wp_mail`). CumpleClick tiene BD propia y se despliega solo, así que se reutilizó el **patrón**, no el código. |
| Portal OmniCliente | `client-portal-omnichannel/` | Consume el módulo WP; no aplica a CumpleClick. |
| Migraciones 008–012 | Solo en la BD local y en worktrees de otras ramas (`.worktrees/baby-shower`, etc.), no en esta rama | La nueva migración se numeró **013** para no colisionar. |
| Método AT / MASTER | `Docs/METODO_AT/*`, `Docs/MASTER/*` | No mencionan CumpleClick; el flujo de cierre por WhatsApp era informal. |
| Latente (fuera de alcance) | `public/admin/index.php` llama `cb_client_ip()` en `subir_tema`, función que no existe en ninguna rama | Fatal al subir assets de temática. No se tocó; se dejó como tarea aparte. |

## 2. Diseño del flujo

```
Admin (backoffice)                     Cliente (enlace público)                    Sistema
------------------                     ------------------------                    -------
Crea la fiesta (nace INACTIVA)
  └─ "Aceptación" → completa Resumen
     del Plan + datos del cliente
     → Generar enlace  ───────────────►  aceptar-plan.php?t=<token 32 hex>
        (token se muestra 1 vez;                │ lee Resumen del Plan
         en BD solo SHA-256)                    │ lee 3 documentos (versión + hash fijos)
                                                │ marca 3 casillas obligatorias
                                                │ + casilla OPCIONAL de marketing
                                                │ nombre, RUT (mod 11), correo, relación
                                                │ firma en canvas (PNG)
                                                └─ POST (nonce HMAC, honeypot, rate-limit)
                                                                                    ├─ valida todo en servidor
                                                                                    ├─ guarda firma.png + comprobante.html
                                                                                    │  en acceptance_dir (privado)
                                                                                    ├─ fila accepted: fecha UTC, IP, UA,
                                                                                    │  meta navegador, hashes, versión
                                                                                    ├─ ROTA el token de aceptación
                                                                                    ├─ emite token de comprobante (solo lectura)
                                                                                    └─ correo al cliente + correo a AT
Badge "T&C: Aceptado y firmado"  ◄──────────────────────────────────────────────────┘
Descarga comprobante / firma
Editar → "Fiesta activa" ✔ (solo ahora)
```

Reglas:

- **Bloqueo del cierre:** `cb_party_can_activate()` exige una aceptación `accepted` o `waived`. `admin/index.php` rechaza `activa=1` en caso contrario (fiesta nueva siempre nace inactiva). La exención (`waived`) es explícita, con motivo y autor, pensada para demos/eventos internos.
- **Fiestas ya activas** al migrar quedan eximidas automáticamente con motivo `migration:013` para no romper operación (auditable en el historial).
- **Un solo enlace vigente** por fiesta: generar uno nuevo revoca el `pending` anterior. Vigencia configurable (1–90 días, default 14).
- **Integridad:** el texto legal se versiona (`CB_LEGAL_VERSION` en `lib.acceptance.php` y `Versión:` en cada `.md`, fail-closed si difieren). La fila fija versión y SHA-256 del texto al emitir el enlace; si el texto cambia antes de firmar, la aceptación se rechaza y hay que emitir un enlace nuevo. El comprobante HTML es autocontenido (texto íntegro + firma embebida + evidencia técnica) y su SHA-256 queda en BD; las descargas verifican el hash antes de servir.
- **Evidencia sobrevive** al borrado de la fiesta (FK `ON DELETE SET NULL` + snapshot de slug/etiqueta).
- **Correos:** `mail()` nativo, texto plano, fail-soft (nunca rompe la aceptación). Cliente recibe enlace del comprobante + hash; AT recibe resumen + enlace al backoffice. `notify_email` y `mail_from` en config → **TODO-LUIS**.

## 3. Marco legal chileno aplicado (resumen operativo, no asesoría)

- **Ley 19.799 (firma electrónica).** Art. 3: un documento firmado con cualquier firma electrónica vale como instrumento escrito, salvo actos solemnes, los que requieren concurrencia personal y los de derecho de familia. Un contrato de servicios para una fiesta es consensual → basta **firma electrónica simple (FES)**. La diferencia con la **firma electrónica avanzada (FEA)** es probatoria: la FEA (certificada por prestador acreditado) hace plena prueba como instrumento público (art. 5 N°1); la FES vale según las reglas generales y se sostiene con evidencia: identidad razonable (nombre, RUT, correo), intención inequívoca (casillas separadas + trazo), integridad (hash del texto y del comprobante) y trazabilidad (fecha, IP, UA, versión). Todo eso es lo que guarda `cc_plan_acceptances`. Para montos de este tamaño y relación de consumo, FES es proporcional; la FEA (p. ej. e-Sign/Acepta) solo se justificaría para contratos corporativos grandes.
- **Ley 19.496 (consumidor).** Art. 12 A: en contratos por medios electrónicos el consentimiento no obliga si el consumidor no tuvo acceso claro y previo a los términos y no pudo guardarlos/imprimirlos; además hay que enviar confirmación escrita. Cubierto con: documentos íntegros en la página, botón imprimir/PDF, correo de confirmación con comprobante. Art. 3 bis b): derecho a retracto de 10 días salvo que el proveedor disponga expresamente lo contrario → **decisión pendiente (TODO-LUIS + abogado)** en T&C 4.2. Art. 16: cláusulas abusivas; por eso la limitación de responsabilidad excluye dolo/culpa grave y deja a salvo derechos irrenunciables.
- **Ley 19.628 + Ley 21.719 (datos personales).** La 21.719 (publicada dic-2024) es plenamente exigible desde el 1 de diciembre de 2026 (fecha a confirmar por abogado). Se aplican desde ya: base de licitud por finalidad (tabla en la Política), consentimiento **del padre/madre/tutor** para datos e imagen de menores con interés superior del niño, consentimiento **separado, opcional y revocable** para marketing, derechos ARCO+portabilidad+bloqueo, plazos de conservación (fotos 30 días; evidencia contractual 5 años por prescripción ordinaria, a confirmar), encargados (Hostinger, correo). La IP se guarda en claro **solo** en la evidencia contractual (interés legítimo/defensa); en formularios públicos sigue como HMAC.
- **Imagen de menores.** Derecho a la propia imagen (art. 19 N°4 CPR) + Ley 21.719 → consentimiento del representante legal, informativo respecto de invitados (obligación del cliente de avisar a otros padres y derecho de exclusión), sin nombres completos ni domicilio en material promocional.
- **Propiedad intelectual (Ley 17.336).** Temáticas inspiradas en personajes de terceros: uso privado, sin afiliación, sin fines comerciales para el cliente; indemnidad por material que aporte el cliente.

## 4. Implementación (archivos)

| Archivo | Rol |
|---|---|
| `CumpleBooth/database/migrations/013_plan_acceptances.php` / `.down.php` | Tabla `cc_plan_acceptances` + índices + exención automática de fiestas activas previas. |
| `CumpleBooth/public/lib.acceptance.php` | Librería: documentos legales versionados + hash, markdown mínimo seguro, emisión de enlaces, nonce sin sesión, RUT, validación y decodificación del PNG (rechaza lienzo vacío), aceptación (archivos + rotación de token + comprobante), estado por fiesta, exención/revocación, correos. |
| `CumpleBooth/public/legal/*.md` | `terminos-y-condiciones.md`, `politica-de-privacidad.md`, `consentimiento-imagen-menores.md` (versión `2026-09-05`, con aviso de borrador y marcadores `TODO-LUIS`). |
| `CumpleBooth/public/aceptar-plan.php` | Página pública por token: resumen, documentos, casillas, datos, canvas, confirmación. Rate-limit GET/POST, nonce HMAC ligado al enlace, honeypot, `Sec-Fetch-Site`. |
| `CumpleBooth/public/comprobante-aceptacion.php` | Entrega del comprobante al cliente por token propio; verifica SHA-256 antes de servir. |
| `CumpleBooth/public/admin/aceptaciones.php` | Backoffice por fiesta: generar enlace (se muestra una vez), historial, estado, descarga de comprobante/firma (verifica hash y pertenencia), revocar, eximir con motivo. |
| `CumpleBooth/public/admin/index.php` | Badge `T&C: …` + botón "Aceptación" en cada fiesta; bloqueo de `activa` sin aceptación; fiesta nueva nace inactiva. |
| `CumpleBooth/public/lib.php`, `config/cumpleclick.example.php` | Claves nuevas: `acceptance_dir`, `notify_email`, `mail_from` (+ env `CC_ACCEPTANCE_DIR`, `CC_NOTIFY_EMAIL`, `CC_MAIL_FROM`). |
| `CumpleBooth/tests/backend/acceptance.php`, `tests/backend/lint.php` | 46 checks (SQLite temporal) + smoke require de los 3 entrypoints nuevos. |

Decisiones:

- **Nativo en `cc_*`, no el módulo WP de `contracts/`:** evita acoplar CumpleClick a WordPress y respeta su BD/despliegue independientes. Se copió el patrón (token, canvas, hash, rotación).
- **Comprobante HTML autocontenido en vez de PDF:** sin dependencias nuevas (no hay librería PDF en el proyecto ni Composer), imprimible a PDF desde el navegador, firma embebida en base64, hash verificable. Si Luis quiere PDF nativo, el punto de extensión es `cb_acceptance_evidence_html()`.
- **Documentos en `public/legal/`:** PROD solo recibe `dist/` (copia byte a byte de `public/`), así que deben viajar ahí. Son públicos por naturaleza.
- **Correo con `mail()` nativo:** Hostinger compartido lo soporta; no había ningún envío de correo en CumpleBooth. Fail-soft.

## 5. Pruebas realizadas (LOCAL, 2026-09-05)

- `php tests/backend/acceptance.php` → **OK 46 checks** (migración + exención automática, bundle legal y hash, markdown, RUT, emisión/revocación, nonce, validación negativa (casilla faltante, RUT malo, firma vacía, PNG falso, honeypot), aceptación completa con archivos y hashes, rotación de token, comprobante por token propio, doble aceptación bloqueada, activación permitida, exención/revocación, vencimiento, supervivencia de evidencia al borrar la fiesta).
- `php tests/backend/lint.php` → OK 67 archivos + smoke require de 11 entrypoints. `tests/backend/leads.php` → OK 11 (regresión).
- `php scripts/migrate.php` en BD local MySQL → `applied 013_plan_acceptances`; 32 fiestas activas quedaron `waived`.
- Flujo E2E en navegador contra `php -S 127.0.0.1:8099 -t public` (BD local, contraseña admin de prueba por variable de entorno, servidor detenido al terminar): crear fiesta activa → bloqueada con mensaje; crear inactiva → badge "T&C: Sin enviar"; generar enlace sin precio → error; con precio → enlace mostrado una vez; página pública renderiza sin errores de consola; POST sin casillas/datos → 8 errores de servidor; casillas + datos + firma en canvas → confirmación con enlace de comprobante y hash; enlace usado → 404; comprobante público → 200; admin muestra "Aceptado y firmado", descarga comprobante (200, attachment) y firma (image/png), 404 para filas de otra fiesta; editar → activar → OK. Datos de prueba eliminados de la BD local al terminar.
- `npm run build` + `php scripts/check-dist-parity.php` → OK paridad public→dist (296 archivos).
- Preexistente y ajeno: `tests/backend/run.php` falla en `carreras: Full recibe su mundo 3D correcto` (assets de temática), igual que antes de este cambio.

No probado: envío real de correo (en local `mail()` no tiene SMTP; la página lo maneja y lo informa), comportamiento en PHP 8.0/8.1 (baseline 8.2 en local; el código no usa sintaxis >8.0 salvo `match`, ya usado en el proyecto).

## 6. Pendientes para Luis (marcados `TODO-LUIS` en código/textos)

1. Revisión legal de los tres documentos por abogado (obligatorio antes de usar con clientes).
2. Razón social, RUT y domicilio definitivos (hoy AUTOMATIZATECH SpA, RUT 78.363.717-0 según vault).
3. Porcentaje/monto de anticipo, medios de pago, escala de cancelación, valores de reposición de equipos, recargo por traslado.
4. Derecho a retracto (art. 3 bis Ley 19.496): reconocerlo o excluirlo expresamente.
5. `notify_email` (correo interno que recibe cada aceptación) y `mail_from` en la config de PROD.
6. Plazos de conservación (fotos 30 días, evidencia 5 años, leads 12 meses) y lista real de encargados (Drive, correo).
7. Contacto de CumpleClick para el Resumen del Plan (WhatsApp/correo) y correo de privacidad.
8. Publicar los tres documentos también en el sitio comercial (`sitio/index.html` hoy solo tiene párrafos breves en `#condiciones`/`#privacidad`).

## 7. Despliegue (LOCAL → PROD Hostinger). Nada está en PROD.

Orden: **1) config privada, 2) migración, 3) archivos PHP base, 4) resto.**

1. Config privada (fuera de `public_html`): agregar a la config real las claves `acceptance_dir` (ruta absoluta privada, p. ej. `/home/ACCOUNT/private/cumpleclick/acceptances`), `notify_email`, `mail_from`. Crear el directorio con permisos de escritura para PHP. **No versionar ni pegar valores en el chat.**
2. Subir `CumpleBooth/database/migrations/013_plan_acceptances.php` y `.down.php` a la carpeta privada de migraciones y ejecutar `php scripts/migrate.php`. Aditiva; rollback con `.down.php` (no borra archivos de evidencia).
3. Archivos web (fuente `CumpleBooth/dist/` = copia de `public/`, paridad verificada):

| Ruta local exacta | Destino PROD relativo | Clase / orden |
|---|---|---|
| `CumpleBooth/database/migrations/013_plan_acceptances.php` | `<privado>/database/migrations/013_plan_acceptances.php` | OBLIGATORIO — paso 2, antes que cualquier PHP web |
| `CumpleBooth/database/migrations/013_plan_acceptances.down.php` | `<privado>/database/migrations/013_plan_acceptances.down.php` | OBLIGATORIO — rollback |
| `CumpleBooth/dist/lib.php` | `/public_html/cumpleclick/lib.php` | OBLIGATORIO — **primero** de los web (claves de config nuevas) |
| `CumpleBooth/dist/lib.acceptance.php` | `/public_html/cumpleclick/lib.acceptance.php` | OBLIGATORIO — segundo; lo requieren admin/index.php y las páginas nuevas |
| `CumpleBooth/dist/legal/terminos-y-condiciones.md` | `/public_html/cumpleclick/legal/terminos-y-condiciones.md` | OBLIGATORIO — antes que `aceptar-plan.php` (fail-closed sin ellos) |
| `CumpleBooth/dist/legal/politica-de-privacidad.md` | `/public_html/cumpleclick/legal/politica-de-privacidad.md` | OBLIGATORIO |
| `CumpleBooth/dist/legal/consentimiento-imagen-menores.md` | `/public_html/cumpleclick/legal/consentimiento-imagen-menores.md` | OBLIGATORIO |
| `CumpleBooth/dist/aceptar-plan.php` | `/public_html/cumpleclick/aceptar-plan.php` | OBLIGATORIO |
| `CumpleBooth/dist/comprobante-aceptacion.php` | `/public_html/cumpleclick/comprobante-aceptacion.php` | OBLIGATORIO |
| `CumpleBooth/dist/admin/aceptaciones.php` | `/public_html/cumpleclick/admin/aceptaciones.php` | OBLIGATORIO |
| `CumpleBooth/dist/admin/index.php` | `/public_html/cumpleclick/admin/index.php` | OBLIGATORIO — **último** (activa el bloqueo; requiere todo lo anterior) |
| `CumpleBooth/config/cumpleclick.example.php` | `<privado>/config/cumpleclick.example.php` | OPCIONAL — plantilla de referencia |
| `Docs/BLUEPRINTS/CUMPLECLICK-ACEPTACION-TERMINOS-Y-FIRMA.md` | — | No se sube (documentación) |

No subir: `config/cumpleclick.local.php`, `storage/`, `tests/`, `src/`, `node_modules/`, ni ningún `.sqlite`/backup. Los bundles `dist/assets/*` **no cambiaron** en este delta (solo PHP y `.md`); no hace falta resubir `index.html`.

4. Gate posterior en PROD: `aceptar-plan.php?t=x` → 400; token inválido de 32 hex → 404; `legal/terminos-y-condiciones.md` → 200; en admin, una fiesta sin aceptación no se puede activar; generar un enlace de prueba, aceptar con datos ficticios, descargar comprobante, verificar que el correo llegue a `notify_email`, luego revocar/borrar la prueba.

## 8. Rollback

`php scripts/migrate.php` no revierte; ejecutar manualmente `(require 'database/migrations/013_plan_acceptances.down.php')(cb_pdo())` o `DROP TABLE cc_plan_acceptances`, y restaurar `admin/index.php` y `lib.php` anteriores. Los archivos de evidencia en `acceptance_dir` se conservan a propósito.
