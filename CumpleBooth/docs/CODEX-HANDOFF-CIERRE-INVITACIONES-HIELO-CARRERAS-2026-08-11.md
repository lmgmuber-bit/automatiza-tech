# Handoff de cierre — Invitaciones Hielo y Carreras (2026-08-11)

## Estado y límites

- Rama de trabajo: `codex/frozen-invitation-parity`.
- Todo está **local**. No hubo deploy, push ni merge.
- No se tocó El Show 3D ni se modificaron sus assets o lógica.
- No se guardaron secretos, credenciales, dumps ni fotos privadas.
- Este documento no incluye tokens de invitación. Los enlaces locales están
  solamente en el chat y en `storage/event-profile-demo/demo-profile-links.json`
  (ignorado por Git y prohibido para PROD).

## Entregado en la invitación

### Hielo: candidatos de video para evaluación

- Hero auto candidato: `public/themes/hielo/invitation/candidate-hielo-auto.mp4`.
- Hero scroll candidato, con avance por cuadro: `public/themes/hielo/invitation/candidate-hielo-scroll.mp4`.
- Modo de evaluación de capítulos: `?capitulos=candidatos`.
- Los videos v2 se conservaron intactos. Para la revisión v3, el mapa de
  candidatos usa:
  - `saludo-anna-v3.mp4`: heroína joven-adulta, vestido formal azul hielo.
  - `saludo-sven-v3.mp4`: protagonista a distancia media/lejana, máximo 35%
    del alto del encuadre.
  - `saludo-bruni-v3.mp4`: espíritu pequeño, máximo 22% del alto.
- Los v3 fueron generados con imagen inicial, Kling 3.0 Turbo y después
  normalizados a H.264/AAC, 720×1280, 5.039 s.
- La capa CSS exclusiva de Hielo evita que el reproductor abra con negro; los
  controles y subtítulos permanecen por encima de ella.

### Audio de cierre: regla global

- Archivo único: `public/assets/audio/narracion-final.mp3`.
- Texto aprobado: **“Toca aquí para ver la invitación a la fiesta.”**
- `public/invitacion.php` fuerza que el último capítulo de cualquier temática
  use este MP3 antes de buscar una despedida por tema. Así Carreras y futuras
  temáticas no pueden volver a divergir.
- Se agregó `?v=<filemtime>` a la URL del MP3 para evitar que el navegador
  conserve la locución anterior en caché.
- Todos los CTA visibles ahora dicen **“Ver invitación”**.

### Perfil público “Conoce a…”

El módulo ya existía y se verificó que funciona tanto en `hero=auto` como en
`hero=scroll`. Solo se renderiza si la invitación tiene `party_id` y el perfil
asociado está habilitado con contenido público.

Para esta QA local se crearon dos fiestas y perfiles **ficticios**, sin fotos y
con textos “ejemplo”/“demo”; viven exclusivamente en
`storage/event-profile-demo/cumpleclick-demo.sqlite`:

| Slug local | Tema | Acceso público |
| --- | --- | --- |
| `qa-perfil-hielo-isidora` | Hielo | “Conoce a la cumpleañera” |
| `qa-perfil-carreras-vicente` | Carreras | “Conoce al cumpleañero” |

Incluyen gustos, tallas de ropa/polera/calzado e ideas para regalar solo para
probar diseño y flujo. **No son datos reales, no se exportan ni se suben.**

Hallazgo importante: la invitación Hielo de prueba original no tenía
`party_id`, por lo que el botón no podía aparecer. Para QA se asoció únicamente
en la SQLite local a la fiesta demo correspondiente. No automatizar una
asociación por nombre/tema: puede mezclar datos de personas distintas. En
operación normal, crear la invitación desde la fiesta correcta en el admin.

## Higgsfield / Seedance 2.5

- `seedance_2_5` es compatible con referencias de imagen, 9:16, 720p y 15 s
  (el catálogo indica rango 4–30 s).
- La sesión OAuth de MCP responde que Unlimited **no** está soportado para ese
  modelo. La preconsulta no creó trabajo ni consumió créditos.
- Antes de generar, reautenticar Higgsfield con la cuenta que tiene el plan o,
  con autorización explícita, consultar y aprobar el costo por créditos.
- Mantener prompts camuflados: no nombrar franquicias ni personajes protegidos,
  no texto/logos/niños, y usar referencias ya autorizadas.

## Validaciones realizadas

- `php8.2.29 -l public/invitacion.php`: OK.
- `npm run build`: OK.
- `npm test`: **101/101** OK.
- `php scripts/check-dist-parity.php`: OK, 346 archivos.
- `git diff --check`: OK.
- HTTP 200 y perfil público presente en las cuatro combinaciones de QA:
  Hielo auto/scroll y Carreras auto/scroll.
- Se ejecutó `graphify update .`; sus artefactos generados quedan visibles en
  `graphify-out/` y deben revisarse según la política de versionado del repo.

## Revisión solicitada antes de versionar

1. Revisar que la prioridad de `narracion-final.mp3` en el último capítulo no
   afecte narraciones intermedias.
2. Revisar el stack CSS Hielo: capa celeste solo en playlist, sin tapar CTA,
   subtítulos, progreso ni accesibilidad.
3. Confirmar que el modo candidatos sigue aislado detrás de query params y no
   cambia el recorrido aprobado por defecto.
4. Confirmar que las filas demo de SQLite y
   `storage/event-profile-demo/demo-profile-links.json` no se versionen ni
   suban a PROD.
5. Revisar la lista FTP de los MP4/MP3 nuevos antes de autorizar deploy; el
   archivo `docs/FTP-MANIFEST.md` sigue siendo la fuente de comparación.

## Versionado y FTP

No ejecutar commit, push, merge ni deploy hasta aprobación explícita de Luis.
Para una eventual entrega se deben regenerar/confirmar los contenidos en
`dist/` y subir solo los assets indicados por el manifest y el diff real. Nunca
subir `storage/`, bases SQLite, fuentes descargadas, URLs/tokens de demo,
`graphify-out/cache/` ni credenciales.