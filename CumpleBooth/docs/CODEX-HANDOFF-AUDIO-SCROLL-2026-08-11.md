# Handoff Codex → Claude — portada y audio de invitación

Fecha: 2026-08-11
Rama de trabajo: `codex/cumplebooth-protagonista-dynamic`.
Estado: cambios locales verificados; no commit, push, merge ni deploy.

## Decisión UX aprobada por Luis

Tanto `capitulos=1` (scroll) como `hero=auto&capitulos=auto` comienzan detrás
de una portada obligatoria:

> Tu invitación está lista
> Toca para abrir la invitación
> Activa música y voz

Ese único toque/clic:

1. Cierra la portada.
2. Inicia la música de la temática y la narración inicial de Alice.
3. En scroll habilita el recorrido.
4. En video inicia el clip y mantiene el scroll bloqueado hasta su evento
   `ended`; recién entonces aparece “Desliza para seguir”.
5. La despedida global de cualquier temática dice: “Toca aquí para ver la invitación a la fiesta.”

No guardar en este documento el token de prueba que compartió Luis. Úsalo solo
si él lo vuelve a entregar en el chat.

## Motivo técnico

Los tres MP3 de prueba responden HTTP 200 como `audio/mpeg`; no había rutas
rotas. Chrome/Safari no autorizan de forma confiable un audio con una rueda de
mouse o `scroll`. La portada garantiza un gesto de usuario real y consistente
para ambos modos.

El botón secundario `Activar música y voz` queda oculto; solo aparece como
respaldo si un navegador excepcional rechaza la reproducción aun después del
toque inicial.

## Archivos modificados

- `public/invitacion.php`
  - portada de apertura y versiones cache `invitation.css?v=3` /
    `invitation.js?v=3`.
- `public/assets/invitation.js`
  - bloqueo de listeners antes de abrir, arranque sincronizado de música,
    Alice y video; preparación silenciosa de despedida; evita intentos dobles.
- `public/assets/invitation.css`
  - estilos de portada temática, responsive y accesible.

## Evidencia local con Chromium

- Scroll: rueda antes de abrir mantiene `scrollY=0` y no reproduce audio.
- Scroll: tocar portada inicia música y Alice; despedida se reproduce al final.
- Video: tocar portada inicia música, Alice y video; `inv-scroll-locked`
  permanece activo durante el clip y se libera al terminar.

## Integración solicitada a Claude

- Luis pidió que Claude integre los cambios después; no cambiar la carpeta
  WAMP ni la rama que atiende `localhost` desde este handoff.
- No tocar el Show 3D ni assets de juego.
- Construir: `npm run build`.
- Verificar: `C:\wamp64\bin\php\php8.2.29\php.exe scripts/check-dist-parity.php`.
- No subir `storage/`, SQLite, el MP3 dinámico local de Vicente ni secretos.
- Si hay FTP autorizado, se sube desde `dist/`, no desde `public/`.