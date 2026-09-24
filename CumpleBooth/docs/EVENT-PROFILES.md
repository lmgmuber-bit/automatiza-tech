# Perfiles de protagonistas por evento

## Alcance

`event_profile` es un módulo opcional asociado a `cc_parties`. Permite publicar
una ficha con uno o más `featured_people` desde todas las invitaciones vigentes
del mismo evento. La primera interfaz validada corresponde a cumpleaños
infantiles; el contrato de datos admite bodas, baby shower, bautizos, fiestas de
mascotas, cumpleaños de adultos y tipos personalizados.

No pertenece al flujo del kiosco, no modifica juegos y no depende del Show 3D.

## Configuración dinámica

`public/data/event-profile-presets.json` define:

- tipos de evento, textos sugeridos, secciones y campos recomendados;
- las cinco temáticas infantiles activas: `carreras`, `familia-canina`,
  `tropical`, `hielo` y `kpop`;
- un fallback para cualquier temática futura que respete el contrato estándar
  `themes/<slug>/fondo-banner.jpg` + `themes.json.colors`.

Agregar una temática no exige copiar PHP, HTML ni JavaScript. Se agrega una
entrada opcional al preset; si no existe, se usa `theme_fallback`.

## Legibilidad sobre los fondos temáticos (2026-08-08)

Los `fondo-banner.jpg` son ilustraciones claras, saturadas y con personajes. La
primera versión los usaba a tamaño completo bajo tarjetas translúcidas al 8-17 %
y el contraste real medido caía a 1,9-2,8:1 en `hielo` y `familia-canina`: bajo
AA incluso para texto grande. Ahora:

- El fondo se conserva como **atmósfera**: desenfocado y desaturado en
  `.ep-shell::before`, con un velo de dos paradas en `::after`.
- Las tarjetas tienen **superficie propia opaca**, no blanco translúcido, así que
  el contraste ya no depende de qué pixel de la foto quede detrás. Esto además
  quita un `backdrop-filter` por tarjeta, que era el coste más alto en móvil.
- Las superficies se derivan de `--ep-ink-base` / `--ep-ink-raise`, que mezclan
  la paleta del tema con un neutro oscuro. Sin esa mezcla, paletas cuyos `dark`
  son color saturado (`carreras`, rojo) quedaban monocromas y sin separación
  entre fondo y tarjeta.

Cada temática ajusta la intensidad desde `themes.<slug>.surface` del preset, sin
CSS nuevo:

| Clave | Qué controla | Rango admitido |
|---|---|---|
| `scrim` | opacidad del velo sobre el fondo | 0,4 – 0,98 |
| `blur` | desenfoque del fondo, en px | 0 – 40 |
| `saturate` | saturación del fondo | 0 – 1,5 |
| `surface_mix` | opacidad de las tarjetas | 50 % – 96 % |
| `title` | color de los títulos de sección | `#rrggbb` |

Los valores fuera de rango se recortan en `invitacion.php`; una temática sin
`surface` usa los valores por defecto del CSS y sigue siendo legible.

`section_accents` distingue gustos, tallas y regalos con un tono **y** un glifo,
para no comunicar la diferencia solo por color; el tono se tiñe hacia el acento
del tema. `person_accents` distingue protagonistas por su **orden**, nunca por
su nombre, y la paleta se ordena por distancia de tono respecto al acento del
tema para que el segundo protagonista no salga del mismo color que el primero.

## Privacidad

- No publicar dirección, teléfono, correo, colegio, rutinas, documentos,
  identificadores ni datos que permitan localizar a un menor.
- Cada campo tiene visibilidad individual y el servidor filtra valores vacíos o
  no públicos.
- Las fotos requieren autorización de publicación.
- La autorización para usar una foto como referencia generativa es separada,
  explícita y desactivada por defecto.
- Fotos, posters y videos viven fuera del webroot y se entregan solo cuando la
  invitación está publicada, vigente y pertenece al mismo evento.
- La retención del perfil sigue la retención configurada para la fiesta.

## Flujo de video

1. El admin selecciona protagonistas, estilo emocional y frase.
2. El backend compone un prompt original desde el preset temático y guarda una
   solicitud `draft`.
3. Higgsfield `models_explore` aporta modelo, parámetros y costo actual.
4. El admin guarda la cotización y Luis aprueba explícitamente.
5. Solo una solicitud `approved` puede pasar a `generating`.
6. El resultado se normaliza con
   `scripts/normalize-event-profile-video.mjs` y se registra como media del
   evento.
7. La invitación reproduce el video con poster, botón Omitir y fallback CSS.

La aplicación web no contiene tokens de Higgsfield ni llama directamente al
proveedor. El trabajo generativo se ejecuta mediante un operador/worker privado;
la tabla de solicitudes conserva prompt, modelo, costo, aprobación e
idempotencia sin almacenar secretos.

## Prompt base

```text
ONE SHOT — 5 seconds — vertical 9:16 — cinematic premium children's celebration.

Use the approved theme background only as a visual reference for palette,
lighting, depth and atmosphere. Create an original environment. Do not reproduce
recognizable characters, franchise elements, logos, emblems or protected visual
identities.

Smooth cinematic camera movement, layered foreground and background, elegant
particles, clear visual center and a graceful final reveal. No written text, no
names, no numbers, no gift information and no private information. Leave a clean
safe area for the frontend HTML overlay.

No child or real person appears unless an explicitly authorized character
reference is attached and the selected model accepts image references.
```

Los descriptores de escena específicos están en el preset y no contienen nombres
de franquicias ni personajes protegidos.

## Degradación segura

- Sin perfil o con perfil desactivado: invitación actual sin cambios visuales.
- Sin contenido público: no se muestra el acceso.
- Sin video, carga lenta o error: transición CSS hacia la ficha.
- `prefers-reduced-motion`: sin zoom ni transiciones intensas.
- Feature flag global apagado: módulo invisible sin borrar datos.
- El admin muestra `Ver como invitado` para la invitación publicada más
  reciente del evento. El enlace usa un alias HMAC reconstruible de 48 caracteres:
  no guarda el token aleatorio en texto plano, no revoca enlaces existentes y
  cualquier alteración de la firma falla cerrada.
- El admin distingue tres estados de publicación, con color **y** texto:
  `Desactivado`, `Activado pero sin contenido visible` y `Publicado`. El estado
  intermedio existe porque los campos nacen ocultos: activar el perfil no basta
  para que el invitado vea el acceso, y antes eso no se avisaba en ninguna parte.

## Operación y despliegue

- La migración es aditiva y no incluye backfill de datos existentes.
- No ejecutar migraciones en PROD sin backup y aprobación de Luis.
- No desplegar config real, storage, fotos, videos de prueba, prompts con datos
  personales ni credenciales.
- `dist/` se genera con Vite después de verificar fuente y pruebas.
