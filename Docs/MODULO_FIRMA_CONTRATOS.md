# 🖋️ Módulo de Contratos con DOBLE FIRMA — AutomatizaTech

Sistema completo para generar, revisar, firmar y archivar contratos digitales con flujo de **doble firma** (representante AT primero, luego cliente), firma electrónica simple conforme a **Ley 19.799 (Chile)**, y generación de PDF profesional usando la misma librería FPDF que cotizaciones e invoices.

---

## 🔄 Flujo end-to-end

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  1) CREAR (status: at_pending)                                               │
│     POST /contracts/create-contract.php                                      │
│     → Genera PDF preliminar                                                  │
│     → Email INTERNO al rep AT con link de revisión                           │
│                                                                              │
│  2) AT FIRMA (status: at_signed)                                             │
│     /contracts/at-sign-contract.php?token=AT_REVIEW_TOKEN                    │
│     → Login WP requerido (capability 'edit_posts')                           │
│     → Firma con canvas o subir imagen                                        │
│     → PDF se regenera con firma AT                                           │
│                                                                              │
│  3) ENVIAR AL CLIENTE (status: sent)                                         │
│     Botón "Enviar al cliente" en at-sign-contract.php                        │
│     → Email corporativo branded al cliente con link                          │
│                                                                              │
│  4) CLIENTE FIRMA (status: signed)                                           │
│     /contracts/sign-contract.php?token=SIGN_TOKEN  (público)                 │
│     → Lee el contrato (PDF inline)                                           │
│     → Firma canvas o sube imagen                                             │
│     → Acepta términos                                                        │
│     → PDF FINAL regenerado con AMBAS firmas + audit trail                    │
│     → Email al cliente con PDF firmado adjunto                               │
│     → Email interno a AT con copia                                           │
│     → PDF queda disponible en ficha del cliente                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 📁 Archivos del módulo

| Archivo | Función |
|---|---|
| `Docs/CONTRATO_SOPORTE_POSTPROYECTO.md` | **Plantilla legal blindada v2.0** con cesión de propiedad al cliente (cl. 12), portabilidad y handover obligatorio (cl. 13), y blindaje completo a AT |
| `contracts/setup-contracts-db.php` | Crea/actualiza tabla `wp_automatiza_contracts` |
| `contracts/contract-service.php` | Lógica central: create, sign_as_at, send_for_client_signature, sign_as_client |
| `contracts/contract-mailer.php` | Emails corporativos branded (4 templates: review interno, request firma cliente, copia firmada cliente, notif interna) |
| `contracts/create-contract.php` | Endpoint POST para crear contratos (admin / N8N) |
| `contracts/at-sign-contract.php` | Página interna firma AT (requiere login WP) |
| `contracts/sign-contract.php` | Página pública firma cliente (token) |
| `wp-content/themes/automatiza-tech/lib/contract-pdf-fpdf.php` | Generador PDF (extiende FPDF, header/footer corporativo, soporta doble firma + audit trail) |

---

## 🚀 Instalación (una sola vez)

1. **Crear la tabla:**
   ```
   https://automatizatech.cl/contracts/setup-contracts-db.php?key=AT_SETUP_2026
   ```
   *(cambia `AT_SETUP_2026` por algo seguro en producción)*

2. **(Opcional) Subir firma corporativa AT preimpresa:**
   `wp-content/themes/automatiza-tech/assets/images/firma-at.png` (PNG transparente, ~300×100px). Si no existe, el rep AT firma manualmente cada vez.

3. **Verificar SMTP:** ya está configurado en `wp-content/themes/automatiza-tech/smtp-config.php` (Hostinger + `contacto@automatizatech.cl`). Los emails saldrán automáticamente con branding AT.

---

## 📞 Cómo crear un contrato

### Desde código PHP (ej: cuando se confirma el pago final)

```php
require_once ABSPATH . 'contracts/contract-service.php';

$contract = ContractService::create_contract([
    'client_id'      => 42,
    'project_id'     => 17,
    'monthly_amount' => 350000,
    'currency'       => 'CLP',
    'starts_at'      => '2026-05-01',
    'ends_at'        => '2027-04-30',
    'expires_in_days'=> 14,
    'placeholders'   => [
        'razon_social_cliente'         => 'Mascotas PetsGo SpA',
        'rut_cliente'                  => '76.123.456-7',
        'representante_cliente_nombre' => 'María Pérez',
        'representante_cliente_rut'    => '15.123.456-7',
        'domicilio_cliente'            => 'Av. Apoquindo 1234, Santiago',
        'email_cliente'                => 'maria@petsgo.cl',
        'telefono_cliente'             => '+56 9 1234 5678',
        'nombre_proyecto'              => 'Bot WhatsApp PetsGo + Portal',
        'propuesta_id'                 => 'PROP-2026-0042',
        'fecha_propuesta'              => '20-04-2026',
        'fecha_aceptacion'             => '25-04-2026',
        'fecha_entrega'                => '28-04-2026',
        'fecha_pago_final'             => '29-04-2026',
        'nombre_plan_soporte'          => 'Premium',
        'horas_evolutivas_mes'         => '8',
        'monto_mensual'                => '350.000',
        'monto_mensual_palabras'       => 'trescientos cincuenta mil',
    ],
]);

// $contract->id, $contract->contract_number, $contract->at_review_token
// → Email interno enviado automáticamente al rep AT
```

### Desde HTTP (N8N / Postman, login WP requerido vía cookie/auth)

```http
POST /contracts/create-contract.php
Content-Type: application/json

{
  "client_id": 42,
  "monthly_amount": 350000,
  "starts_at": "2026-05-01",
  "placeholders": { "razon_social_cliente": "Mascotas PetsGo SpA", ... }
}
```

Respuesta:
```json
{
  "ok": true,
  "id": 7,
  "contract_number": "AT-CTR-20260429-A3F4B",
  "status": "at_pending",
  "pdf_url": ".../uploads/automatiza-tech-contracts/AT-CTR-20260429-A3F4B.pdf",
  "at_review_url": ".../contracts/at-sign-contract.php?token=..."
}
```

---

## 🛡️ Cláusulas blindaje (en la plantilla legal)

| Cl. | Asunto | Beneficio |
|---|---|---|
| **9** | Limitación de responsabilidad | Tope = últimos 3 meses pagados. Sin daños indirectos, lucro cesante, multas SII, decisiones IA, etc. |
| **9.4** | Garantía limitada | NO garantía de comerciabilidad ni resultados de IA |
| **9.5** | Indemnidad | Cliente protege a AT por uso indebido y datos cargados |
| **12** | Propiedad intelectual | **Cliente es dueño** del Proyecto al pago final. AT conserva know-how y componentes preexistentes (licencia de uso interno) |
| **13** | Portabilidad / Salida | AT obligado a entregar en {{dias_handover}} días: código fuente, credenciales, accesos, dominios, BDs, configuraciones, sesión técnica de transferencia |
| **14** | No contratación de personal | 12 meses, cláusula penal = 6 meses sueldo |

---

## 🔐 Validez legal de la firma electrónica simple

Conforme a la **Ley 19.799** y su Reglamento, la firma electrónica simple es válida para contratos de prestación de servicios cuando se registra:
- Nombre completo del firmante ✅
- RUT ✅
- Email ✅
- Dirección IP ✅
- Fecha y hora ✅
- User-Agent (navegador/dispositivo) ✅
- Hash SHA-256 del documento ✅
- Imagen de firma (canvas trazo o upload) ✅
- Aceptación expresa de términos (checkbox) ✅

Todo esto queda registrado en la tabla `wp_automatiza_contracts` y se imprime en el bloque "REGISTRO DE FIRMA ELECTRÓNICA SIMPLE" del PDF firmado.

---

## 📊 Esquema de BD (`wp_automatiza_contracts`)

Campos clave:
- `status`: `draft → at_pending → at_signed → sent → viewed → signed → expired/cancelled`
- `sign_token` (64h): para link público al cliente
- `at_review_token` (64h): para link privado al rep AT
- `placeholders` (LONGTEXT JSON): todos los datos del contrato
- `pdf_url`: PDF preliminar / con firma AT
- `signed_pdf_url`: PDF FINAL con ambas firmas
- `document_hash` / `signed_document_hash`: SHA-256 de integridad
- Metadata firma AT: `at_signer_*`, `at_signed_at`, `at_signature_image_url`
- Metadata firma Cliente: `signer_*`, `signed_at`, `signature_image_url`

---

## 📂 Almacenamiento

- **PDFs:** `wp-content/uploads/automatiza-tech-contracts/`
  - `AT-CTR-YYYYMMDD-XXXXX.pdf` (preliminar / con firma AT)
  - `AT-CTR-YYYYMMDD-XXXXX-FIRMADO.pdf` (FINAL con ambas firmas)
- **Imágenes de firma:** `wp-content/uploads/automatiza-tech-contracts/signatures/`
  - `sig-at-{id}-{ts}.png`
  - `sig-client-{id}-{ts}.png`
- Acceso protegido por `.htaccess` (no listing).

---

## 🔌 Integración con la ficha del cliente

Para mostrar contratos en la ficha:

```php
$contracts = ContractService::list_by_client($client_id);
foreach ($contracts as $c) {
    echo "<a href='{$c->signed_pdf_url}' target='_blank'>";
    echo "  📄 {$c->contract_number} ({$c->status})";
    echo "</a>";
}
```

En el portal React se puede consumir vía un endpoint `api-contracts.php?client_id=X` (siguiendo el patrón de `api-omnichannel.php`).

---

## 🧪 Testing rápido

1. Crear tabla: visitar `setup-contracts-db.php?key=...`
2. Crear contrato vía PHP CLI o endpoint.
3. Revisar email interno (rep AT recibe link `at-sign-contract.php?token=...`).
4. Firmar como AT (canvas o upload).
5. Botón "Enviar al cliente" → email al cliente.
6. Cliente abre `sign-contract.php?token=...`, revisa PDF, firma, acepta términos.
7. Revisar correo del cliente: PDF FINAL adjunto con ambas firmas y registro de auditoría.

---

## 🐛 Bugs conocidos / corregidos

### ❌ PDF final enviado al cliente solo contenía firma de AT (corregido `001c6ae`)

**Síntoma:** El cliente recibía un correo con asunto "Contrato firmado", pero el PDF adjunto era el preliminar (solo firma AT, bloque del cliente vacío).

**Causa raíz:** `render_pdf()` lee la BD para decidir si incluir el bloque de firma del cliente (`if ($c->signed_at)`). En la implementación original, `signed_at` se grababa en un segundo `UPDATE` **después** de llamar `render_pdf()`, por lo que el PDF se generaba con `signed_at = null` → bloque de firma del cliente omitido.

**Fix en `contracts/contract-service.php` — `sign_as_client()`:**
```php
// ANTES (incorrecto):
$wpdb->update(table, ['signer_name'=>...], ['id'=>$c->id]);          // signed_at NO grabado
$signed_path = self::render_pdf($c->id, true);                       // lee BD: signed_at = null → sin firma cliente
$wpdb->update(table, ['status'=>'signed', 'signed_at'=>$now], ...); // demasiado tarde

// DESPUÉS (correcto):
$wpdb->update(table, ['status'=>'signed', 'signed_at'=>$now, 'signer_name'=>..., ...], ...); // TODO antes
$signed_path = self::render_pdf($c->id, true);   // lee BD: signed_at presente → incluye firma cliente
$wpdb->update(table, ['signed_pdf_url'=>..., 'signed_document_hash'=>...], ...); // solo URL + hash
```

---

### ❌ Fecha "01-01-1970" en ficha de contratos (corregido `9020ae3` / `42491b8`)

**Causa:** `list_by_client()` no incluía `created_at` en el SELECT. Posterior fix adicional: `strtotime('0000-00-00 00:00:00')` retorna `false` y `date('d-m-Y', false)` produce `01-01-1970`.

**Fix:** `created_at` añadido al SELECT + validación `$ts > 0` en el widget.

---

### ❌ Caracteres especiales (`?`) en títulos PDF (corregido commits `3581f33`–`78edd15`)

**Causa:** `mb_convert_encoding()` reemplaza caracteres no-ISO-8859-1 con `?`. Con 8 archivos definiendo `utf8_to_latin1()` bajo `if(!function_exists)`, el primero en cargarse (versión rota) ganaba.

**Fix:** Función movida dentro de la clase como `private static function enc()` (no puede ser sobreescrita por archivos externos). 37 llamadas actualizadas.

---

## 📋 Historial de cambios

| Fecha | Commit | Cambio |
|---|---|---|
| 2026-05-11 | `001c6ae` | **Fix crítico:** `signed_at` grabado antes de `render_pdf()` — PDF final incluye firma del cliente |
| 2026-05-11 | `42491b8` | Guard `strtotime > 0` para fecha en widget (evita `01-01-1970` con `0000-00-00`) |
| 2026-05-11 | `9020ae3` | `created_at` añadido al SELECT de `list_by_client()` |
| 2026-05-11 | `e18ef54` | Canal contacto corregido: solo `contacto@automatizatech.cl` (eliminado WhatsApp) |
| 2026-05-11 | `78edd15` | Negrita inline en PDF: `renderInlineBold()` con font switching |
| 2026-05-11 | `8418df8` | Encabezado PDF: logo 22mm, celda derecha 80mm, `º` con bytes UTF-8 |
| 2026-05-11 | `0f27546` | Encoding: `self::enc()` privado con `iconv //IGNORE`, 37 llamadas migradas |
| 2026-05-11 | `3581f33` | Fix inicial encoding con `iconv` en lugar de `mb_convert_encoding` |

---

_Versión 2.0 — 2026-05-11. Validar plantilla legal con asesoría antes de producción._
