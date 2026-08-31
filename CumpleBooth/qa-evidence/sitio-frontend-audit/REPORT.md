# Auditoría independiente — frontend público CumpleClick

| Campo | Resultado |
|---|---|
| Fecha | 2026-08-01 |
| URL | `http://localhost/automatiza-tech/CumpleBooth/sitio/` |
| Alcance | Solo frontend público; auditoría inicial y verificación posterior a correcciones |
| Viewports | 1440×900, 768×1024 y 390×844 |
| Consola | Sin errores ni advertencias propias tras migrar de `THREE.Clock` a `THREE.Timer` |
| Rendimiento local | TTFB 0.3 ms, FCP/LCP 392 ms, CLS 0 |

## Dictamen

La landing tiene una identidad visual propia, coherente y memorable. La jerarquía del hero,
la explicación en tres pasos, el carrusel de temáticas, el video, los planes y el formulario
componen una historia comercial clara. Todavía no la publicaría como versión definitiva sin
resolver primero el desbordamiento horizontal, accesibilidad básica, coherencia de planes y
riesgo de marcas/personajes protegidos.

## Estado después de las correcciones de Codex

- Desbordamiento corregido: `scrollWidth === clientWidth` en 375, 390, 768 y 1440 px.
- H1 corregido: nombre accesible “Su personaje favorito. Su foto. Al instante.”.
- FAQ confirmada como `details/summary` nativo, operable con Enter y foco visible.
- Modal corregido con nombre accesible, fondo `inert`, foco inicial, ciclo con Tab/Shift+Tab,
  cierre con Escape y retorno al botón disparador.
- Galería retirada del Plan Mágico y conservada en Premium; cuota visible alineada a 200 fotos.
- Se conservaron los precios de lanzamiento al 50% por aprobación explícita de Luis el 2026-08-01.
- Temática a medida alineada a `+$25.000`.
- Formulario ampliado con comuna y consentimiento; sigue sin almacenar datos ni abrir WhatsApp
  mientras `[WHATSAPP_NUMBER]` continúe sin configurar.
- Agregados privacidad, cobertura, condiciones básicas, enlaces de pie y hooks locales de
  intención de conversión sin trackers externos.
- Evidencia posterior: `after-mobile-hero.png`, `after-mobile-pricing.png` y
  `after-mobile-form.png`.

### Formulario persistente — 2026-08-01

- El formulario ahora registra solicitudes en `cc_leads` dentro de la BD independiente de
  CumpleClick mediante `sitio/api/contacto.php`.
- Controles: POST JSON, límite de 16 KB, validación servidor, honeypot, rate limit persistente,
  prepared statements, referencia opaca e IP almacenada únicamente como HMAC.
- Migración `006_public_leads` aplicada en MySQL local y verificada también en SQLite.
- PHP 8.0/8.2/8.3/8.4: 11/11 pruebas específicas y lint verde.
- Smoke HTTP: 201 válido, 422 inválido y 405 para GET. Chrome 834×1194 mostró la referencia
  creada sin errores de consola ni overflow. Los registros de QA se eliminaron al finalizar.
- La integración de WhatsApp permanece pendiente por decisión de Luis.

## Hallazgos

### 1. Desbordamiento horizontal en móvil y tablet

- Severidad: media.
- En 390 px, el documento mide 413 px: 38 px de desbordamiento.
- En 768 px, el documento mide 795 px: 42 px de desbordamiento.
- Se observa una barra horizontal permanente. Los principales elementos fuera del viewport son
  el bloque `.contacto`, `.contacto__form` y el frame de la experiencia; el carrusel también debe
  quedar recortado por su propio contenedor, no por el documento.
- Evidencia: `screenshots/mobile-hero.png` y `screenshots/tablet-hero.png`.

### 2. FAQ no operable con teclado/lector de pantalla

- Severidad: media.
- Las cuatro preguntas se ven, pero no aparecen como botones o controles expandibles en el árbol
  de accesibilidad. Deben implementarse con `button`, `aria-expanded` y `aria-controls`, o con
  `details/summary` correctamente semánticos.
- Evidencia: `screenshots/desktop-faq.png`.

### 3. Modal de video no aísla el contenido de fondo

- Severidad: media.
- Escape sí cierra el modal y existe un botón “Cerrar video”, pero todos los enlaces, formularios
  y controles del fondo siguen disponibles en el árbol accesible. Debe usar `role="dialog"`,
  `aria-modal="true"`, foco inicial, trampa de foco, restauración al disparador e `inert` en el fondo.
- Evidencia: `screenshots/desktop-demo-playing.png`.

### 4. Nombre accesible del titular concatena palabras

- Severidad: baja.
- El H1 se anuncia como “Su personaje favorito. Sufoto. Alinstante.”. Visualmente está correcto,
  pero faltan separadores accesibles entre spans. Se corrige conservando espacios reales o con un
  `aria-label` completo.

### 5. Contradicción comercial sobre la galería

- Severidad: media.
- Plan Mágico y Plan Premium muestran “Galería privada para papás”. La regla de producto
  comunicada anteriormente reservaba la galería para el plan Full/Premium cuando el cliente la
  contrata. Hay que definir una única fuente de verdad y reflejarla en landing, admin y contrato.
- Evidencia: `screenshots/desktop-pricing.png` y `screenshots/mobile-pricing.png`.

### 6. Riesgo de uso público de marcas y personajes protegidos

- Severidad de negocio: alta.
- La página comercial publica nombres e imágenes reconocibles de Bluey, Cars, Frozen,
  Lilo & Stitch, Capitán América y KPop Demon Hunters. Antes de PROD debe existir autorización
  de uso o reemplazarse la comunicación pública por categorías/estilos originales. Esta observación
no cambia los nombres internos del kiosco; afecta la publicidad pública del servicio.

**Decisión de producto:** los nombres reales se mantienen en la comunicación pública por instrucción
de Luis; el camuflaje sigue siendo obligatorio únicamente en prompts de generación. Este punto queda
como validación comercial/legal previa a PROD, no como defecto técnico de esta corrección.

### 7. Advertencia técnica y mantenimiento

- Severidad: baja.
- Consola limpia de errores, con una advertencia: `THREE.Clock` está deprecado y recomienda
  `THREE.Timer`. No bloquea la publicación, pero conviene corregirla antes de actualizar Three.js.

## Mejoras de conversión recomendadas

1. Agregar evidencia real: 3–5 testimonios verificables, fotos del montaje y una galería breve de
   resultados con autorización escrita de los apoderados.
2. Agregar un bloque “Qué incluye exactamente” con duración, montaje/retiro, comuna o radio de
   cobertura, traslado, cantidad de invitados, conectividad, respaldo sin internet y entrega.
3. Convertir los planes en comparación explícita. Destacar con claridad qué agrega Premium/Full:
   juegos 3D, video personalizado, invitación y galería si corresponde.
4. Incorporar disponibilidad: selector de fecha/comuna antes de WhatsApp y una respuesta clara
   de “fecha disponible / consultar”.
5. Añadir una demostración corta del recorrido completo (nombre → ruleta → saludo → foto → QR →
   diploma), no solamente una temática terminada.
6. Añadir confianza y legalidad: privacidad de fotos, retención de 30 días, consentimiento,
   términos de servicio, política de cancelación y datos de AutomatizaTech.
7. Añadir analítica de conversión con consentimiento: clics a WhatsApp, reproducción del video,
   selección de plan y envío del formulario. Evitar recopilar datos de menores.
8. Preparar SEO local: títulos por servicio/comuna, Open Graph, datos estructurados de negocio y
   FAQ, sitemap y páginas orientadas a cumpleaños, colegios, Navidad y empresas.

## Orden recomendado

1. Corregir overflow y accesibilidad.
2. Alinear planes/galería y revisar el uso de marcas.
3. Conectar WhatsApp y medir conversiones.
4. Agregar prueba social, privacidad y condiciones.
5. Incorporar disponibilidad y demostración completa.
