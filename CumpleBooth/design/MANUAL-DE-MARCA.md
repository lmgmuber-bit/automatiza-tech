# CumpleClick — Manual de Identidad Corporativa

**Versión 1.0 — 2026-07-18 · Dirección elegida por Luis: "El globo dulce" (premium infantil)**

---

## 1. La marca en una frase

> CumpleClick convierte el cumpleaños en un recuerdo: un globo que también es una cámara.

El símbolo tiene doble lectura — **globo** (la fiesta) y **lente de cámara** (la foto) — con acabado de caramelo/juguete de colección: premium sin frialdad, infantil sin exagerar. Le habla al padre que paga con la promesa del asombro del niño.

Filosofía de diseño completa: [`../docs/BRAND-FILOSOFIA-CUMPLECLICK.md`](../docs/BRAND-FILOSOFIA-CUMPLECLICK.md).

---

## 2. Logo

| Archivo | Uso |
|---|---|
| `logo/cumpleclick-logo-master-render.png` | **Master de referencia** (render Higgsfield 1024px). Avatar de redes, mockups, impresión pequeña. |
| `logo/cumpleclick-globo-mark.svg` | Isotipo vectorial (solo símbolo). Favicon, watermark, stickers, tamaños chicos. |
| `logo/cumpleclick-globo-lockup.svg` | Lockup vertical (símbolo + wordmark). Cabeceras, portadas, diplomas. |
| `renders/` | Direcciones alternativas exploradas (caramelo, medalla oro, globo vidrio). **No usar como logo**; sirven para campañas especiales. |

### Reglas de uso

- **Zona de respeto**: alrededor del logo, mínimo el diámetro del aro amarillo del lente libre de otros elementos.
- **Tamaño mínimo**: isotipo 24px; lockup 120px de alto.
- **Fondos**: el logo vive sobre **crema** (`#FFF8EC`) o blanco. Sobre fondos oscuros/violeta usar el isotipo tal cual (el globo brilla solo); nunca recuadrarlo en una caja blanca.
- **Prohibido**: rotar, estirar, cambiar los colores del globo, agregar sombras duras, poner texto encima del globo, usar el hilo dorado separado del globo.

---

## 3. Paleta

| Rol | Nombre | Hex | Uso |
|---|---|---|---|
| Primario | Violeta Globo | `#8B5CF6` | Color de marca, fondos de acento, enlaces |
| Primario oscuro | Tinta Violeta | `#4C2882` | Wordmark "Cumple", titulares, texto sobre claro |
| Acento 1 | Fucsia Click | `#D6307F` | Wordmark "Click", CTAs, precio, badge |
| Acento 2 | Amarillo Lente | `#FBBF24` | Aro del lente, highlights, iconos festivos |
| Metal | Oro Hilo | `#E8A317` (degradé `#F5C542→#D99A14`) | Detalles finos, filos, hilo — **nunca en masas grandes** |
| Fondo | Crema | `#FFF8EC` | Fondo por defecto de todo material de marca |
| Soporte | Lila Confeti | `#A78BFA` | Confeti, fondos suaves, estados hover |
| Profundo | Fucsia Nudo | `#C2186B` | Sombras del fucsia, boquilla del globo |

Regla de proporción: **70% crema / 20% violeta-fucsia / 8% amarillo / 2% oro**. El oro es condimento, no plato.

Tokens CSS listos para usar: [`tokens.css`](tokens.css).

---

## 4. Tipografía

- **Display / titulares / wordmark**: **Baloo 2** ExtraBold (800) — la misma del producto (ya self-hosted en el kiosco). Interletrado normal, nunca condensado.
- **Cuerpo**: Baloo 2 SemiBold (600) para UI; `system-ui` para textos largos.
- El wordmark es siempre `Cumple` en Tinta Violeta + `Click` en Fucsia, una sola palabra, C mayúsculas.

---

## 5. Voz y tono (community management)

- **Personalidad**: la tía/el tío entusiasta que organiza la mejor parte de la fiesta. Cálido, directo, chileno neutro. Habla de recuerdos, no de tecnología.
- **Sí**: "el flash que tus hijos no van a olvidar", "cada invitado se lleva su foto con su personaje favorito", emojis con moderación (🎈📸🎉 máx. 2 por post).
- **No**: jerga técnica (IA, app, software), mayúsculas sostenidas, promesas de descuento agresivas, más de 3 hashtags visibles.
- **Hashtags base**: #CumpleClick #CumpleañosInfantiles #PhotoBoothChile + 1 de la temática.
- **CTA estándar**: "Agenda la fecha por WhatsApp 📲" (link en bio).

---

## 6. Aplicaciones

| Superficie | Especificación |
|---|---|
| Avatar IG/WhatsApp | `logo-master-render.png` recortado cuadrado, sin texto |
| Portada / highlights IG | Fondo crema, isotipo chico arriba, título Baloo 2 en Tinta Violeta |
| Posts feed | 1080×1350 (4:5), plantillas en `social/` |
| Reels/stories | 1080×1920 (9:16), bumper animado del globo al inicio o cierre |
| Diploma del kiosco | Lockup vertical arriba, oro solo en filetes |
| Sitio/landing | Tokens de `tokens.css`; hero sobre crema |

---

## 7. Precios de referencia (para materiales comerciales)

Mágico **$69.990** · Premium **$99.990** · Temática a medida **+$25.000** (CLP). Siempre en Fucsia Click, formato `$XX.XXX`.

---

## 8. Historial de decisión

- v1 vibrante (obturador/confeti plano) → exploración inicial, se conserva para stickers/UI del kiosco.
- v2 premium "oro sobre tinta" (medalla, globo vidrio) → descartada como principal por fría/adulta; el render medalla queda para campañas premium.
- **v3 "El globo dulce" → ELEGIDA como identidad oficial (Luis, 2026-07-18).**

Renders generados con Higgsfield (`nano_banana_pro`); jobs: `19f962cb` (elegido), `4dbbe510`, `addf19c3`, `f582f69b`.
