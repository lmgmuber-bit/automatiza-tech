# Continuación OpenCode — Aventura Perruna visual v1

Ticket: `AT-CUMPLECLICK-007`  
Rama: `codex/cumpleclick-secure-db-frontend`  
Ejecutor: OpenCode + DeepSeek V4 Flash  
Generador externo autorizado: Higgsfield

## Estado aprobado por Luis

No regenerar ni sustituir estos assets:

| Uso | Archivo | SHA-256 |
|---|---|---|
| Invitación genérica maestra, cuatro miembros | `public/themes/familia-canina/invitation/invitation-base-v1.png` | `58d3b542cc7c8c3137c9e5b24628979953e89970fe2420d0edbf5908e81315b3` |
| Fondo de ruleta, dos hermanas | `public/themes/familia-canina/roulette/roulette-background-v1.png` | `bce55c085ce35d4d45f122931d0ea0337b760dee64bbed7df90d686bfc82d80f` |

Ambos miden 1080×1920. El contrato visual canónico está en
`public/themes/familia-canina/visual-manifest.v1.json`.

La invitación maestra muestra únicamente la familia de cuatro. Los dos amigos
restantes pertenecen a la ruleta y a sus assets individuales; no deben
amontonarse dentro de la invitación.

## Prefijo obligatorio para todo prompt derivado

Agregar literalmente este bloque, después del escáner de camuflaje, a cada
prompt futuro de imagen:

```text
VISUAL REFERENCE LOCK:
Use the provided approved vertical reference image only to preserve its premium
photorealistic 3D collectible-toy finish, smooth rounded child-friendly
geometry, clean edges, soft matte-to-satin surfaces, subtle ambient occlusion,
bright warm cinematic daylight, soft realistic contact shadows, pastel
playground birthday atmosphere and saturated sky-blue, deep navy, warm orange,
coral, cream, pastel-pink, sunny-yellow and soft-green palette.

Preserve the same facial language: large clean oval eyes, small oval noses,
upright ears, rounded muzzles and warm family-friendly expressions. Preserve
the strong blue-versus-orange color identity. The vivid blue child must never
be gray, teal, lavender or washed out. Match the approved luxury-toy quality;
never use flat 2D drawing, thick outlines, sticker aesthetics, cheap plastic or
low-poly geometry.

Create a new original composition for the requested asset. Do not reproduce
logos, marks or a known poster. No text, letters, numbers, signatures, QR codes
or watermarks unless the task supplies validated exact event text.
```

No enviar al proveedor los IDs internos, el nombre público de la temática ni
referencias a obras existentes. Describir únicamente rasgos físicos.

## Próximo gate: video genérico

1. Verificar los dos checksums anteriores.
2. Registrar `visualVersion=1` y las rutas en el catálogo del tema sin romper
   Carreras.
3. Crear el thumbnail local de la invitación, sin generación externa.
4. Cotizar en Higgsfield un solo video de 5 s, 720p, 9:16, sin audio, usando
   `invitation-base-v1.png` como `start_image`.
5. Informar modelo, saldo y costo; Luis ya autorizó consumo secuencial.
6. Generar un solo `invitation-base-motion-v1.mp4` y detenerse para QA.

Prompt de movimiento exacto:

```text
Animate only the provided approved generic vertical 9:16 birthday invitation
start image for 5 seconds at 720p. Preserve exactly the four original canine
family figures: two taller parents behind two smaller puppy sisters. Preserve
every fur color and patch, face, proportion, pose, cake, gifts, balloon arch,
playground, palette, lighting, camera and composition.

Keep the large cream invitation sign completely blank, perfectly stable,
sharp, unobstructed and evenly lit for a separate application-rendered overlay.
The video itself must contain absolutely no text, letters, words, numbers,
symbols, logos, signatures, QR codes or watermarks.

Use a very slow cinematic push-in and subtle layered parallax. Balloons sway
gently, a few pieces of confetti drift, candle flames flicker and the four
figures blink or make tiny friendly movements. Do not add, remove, duplicate or
redesign any figure. Do not create camera cuts, lip-sync, fast zooms, warping,
flicker, text mutation or new objects. End close to the starting pose for a
clean loop.
```

## Ruleta obligatoria

Usar `roulette-background-v1.png` detrás de una rueda centrada dentro del área
del cartel vacío. La rueda tiene seis resultados internos: `azulita`, `chispa`,
`papa-marino`, `mama-coral`, `manchita` y `nube`.

Para cada resultado se necesitan thumbnail, retrato, recorte PNG, saludo MP4,
fallback JPG, composición fotográfica y diploma. El resultado seleccionado es
la única fuente de verdad desde la ruleta hasta Preview, QR y Diploma. Los IDs
internos nunca se envían a Higgsfield.

## Límite de ejecución

No generar en lote. Después del video genérico, detenerse. No crear todavía la
imagen personalizada, el video personalizado ni los seis personajes sin el
gate correspondiente. Sin deploy, commit, push o merge.
