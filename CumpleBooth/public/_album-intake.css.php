<?php
/**
 * Estilos de la página de carga del invitado (subir.php).
 *
 * Se incluye dentro de un <style> para que la página completa llegue en una
 * sola petición: en la wifi de una fiesta, un archivo CSS aparte es un
 * round-trip más antes de que se vea algo.
 *
 * Los colores NO se escriben acá: llegan como variables CSS desde
 * cb_theme_css_vars(), que las lee de themes.json. Los valores de :root son
 * solo el respaldo para cuando el evento no declara paleta.
 */
?>
:root {
  --pink: #7C3AED;
  --pink-soft: #EDE4FB;
  --yellow: #FBBF24;
  --ink: #2b1a12;
  --bg-light1: #FFF8EC;
  --bg-light2: #F6EFFF;
  --dark1: #3b1d5e;
  --dark2: #26123f;
  --dark3: #8B5CF6;
  --radius: 20px;
}

* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

html, body { margin: 0; padding: 0; }

body {
  min-height: 100dvh;
  font-family: 'Baloo 2', 'Segoe UI Rounded', 'Segoe UI', system-ui, -apple-system, sans-serif;
  color: #fff;
  background: linear-gradient(165deg, var(--dark1) 0%, var(--dark2) 60%, var(--dark3) 145%);
  background-attachment: fixed;
  line-height: 1.5;
}

/* Contenedor único, angosto: la página se diseñó para un celular en la mano y
   en pantalla grande simplemente se centra en vez de estirarse. */
.sheet {
  position: relative;
  max-width: 560px;
  margin: 0 auto;
  padding: 28px 18px calc(32px + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.sheet--center {
  min-height: 100dvh;
  justify-content: center;
  text-align: center;
}

/* Banner de la temática, muy velado: da identidad sin competir con el texto.
   La URL la inyecta subir.php solo si el archivo existe en disco. */
.sheet::before {
  content: '';
  position: fixed;
  inset: 0 0 auto 0;
  height: 46vh;
  background-size: cover;
  background-position: center top;
  opacity: .22;
  -webkit-mask-image: linear-gradient(180deg, #000 0%, transparent 100%);
  mask-image: linear-gradient(180deg, #000 0%, transparent 100%);
  pointer-events: none;
  z-index: 0;
}
.sheet > * { position: relative; z-index: 1; }

.intro { text-align: center; }

.eyebrow {
  margin: 0 0 6px;
  font-size: .78rem;
  font-weight: 700;
  letter-spacing: .18em;
  text-transform: uppercase;
  color: var(--yellow);
}

.headline {
  margin: 0;
  font-size: clamp(1.5rem, 6.5vw, 2.1rem);
  font-weight: 800;
  line-height: 1.15;
  text-shadow: 0 2px 12px rgba(0,0,0,.35);
}

.lede {
  margin: 10px 0 0;
  font-size: 1.02rem;
  opacity: .92;
}

.panel {
  background: rgba(255,255,255,.10);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: var(--radius);
  padding: 18px 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  backdrop-filter: blur(6px);
}
.panel--done { align-items: center; text-align: center; gap: 12px; }

.rules {
  margin: 0;
  padding-left: 18px;
  font-size: .92rem;
  opacity: .9;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.rules strong { color: var(--yellow); }

/* El área de selección es toda la etiqueta: en un celular hay que poder tocar
   cualquier parte, no apuntarle a un botón chico. */
.picker {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-height: 132px;
  justify-content: center;
  padding: 20px 16px;
  border: 2px dashed rgba(255,255,255,.42);
  border-radius: var(--radius);
  background: rgba(0,0,0,.14);
  cursor: pointer;
  text-align: center;
  transition: border-color .2s, background .2s;
}
.picker:active { background: rgba(0,0,0,.24); }
.picker:focus-within { border-color: var(--yellow); }
.picker svg { color: var(--yellow); }
.picker-title { font-weight: 800; font-size: 1.05rem; }
.picker-hint { font-size: .86rem; opacity: .78; }
.picker input[type="file"] {
  position: absolute;
  width: 1px; height: 1px;
  padding: 0; margin: -1px;
  overflow: hidden;
  clip: rect(0,0,0,0);
  white-space: nowrap;
  border: 0;
}

.queue { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.queue:empty { display: none; }

.queue-item {
  display: grid;
  grid-template-columns: 48px 1fr auto;
  align-items: center;
  gap: 10px;
  padding: 8px;
  border-radius: 14px;
  background: rgba(0,0,0,.22);
}
.queue-item.is-done { background: rgba(22,163,74,.26); }
.queue-item.is-error { background: rgba(220,38,38,.26); }

.queue-thumb {
  width: 48px; height: 48px;
  border-radius: 10px;
  overflow: hidden;
  background: rgba(255,255,255,.14);
  display: grid;
  place-items: center;
  font-size: 1.2rem;
}
.queue-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

.queue-body { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.queue-name {
  font-size: .9rem;
  font-weight: 700;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.queue-meta { font-size: .78rem; opacity: .85; }

.queue-bar { height: 4px; border-radius: 999px; background: rgba(255,255,255,.20); overflow: hidden; }
.queue-bar span { display: block; height: 100%; width: 0; background: var(--yellow); transition: width .18s linear; }
.queue-item.is-ready .queue-bar { visibility: hidden; }

.queue-remove {
  width: 34px; height: 34px;
  border: none;
  border-radius: 50%;
  background: rgba(255,255,255,.16);
  color: #fff;
  font-size: 1.3rem;
  line-height: 1;
  cursor: pointer;
}

.field { display: flex; flex-direction: column; gap: 5px; }
.field label { font-size: .86rem; font-weight: 700; }
.optional { font-weight: 500; opacity: .7; }
.field input, .field textarea {
  width: 100%;
  padding: 11px 12px;
  border-radius: 12px;
  border: 1px solid rgba(255,255,255,.26);
  background: rgba(0,0,0,.24);
  color: #fff;
  font-family: inherit;
  font-size: 1rem; /* 16px evita que iOS haga zoom al enfocar el campo */
  resize: vertical;
}
.field input::placeholder, .field textarea::placeholder { color: rgba(255,255,255,.5); }

.consent { display: flex; align-items: flex-start; gap: 10px; font-size: .9rem; cursor: pointer; }
.consent input {
  flex: 0 0 auto;
  width: 22px; height: 22px;
  margin-top: 1px;
  accent-color: var(--pink);
}

.error {
  margin: 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(220,38,38,.3);
  border: 1px solid rgba(255,255,255,.24);
  font-size: .9rem;
  font-weight: 600;
}

/* 56 px de alto mínimo: mismo objetivo táctil que exige el kiosco. */
.cta {
  min-height: 56px;
  border: none;
  border-radius: 999px;
  background: var(--pink);
  color: #fff;
  font-family: inherit;
  font-size: 1.06rem;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 8px 22px rgba(0,0,0,.28);
}
.cta:disabled { opacity: .45; cursor: not-allowed; box-shadow: none; }
.cta--ghost { background: rgba(255,255,255,.16); }

.fineprint { margin: 0; font-size: .8rem; opacity: .72; text-align: center; }

.done-mark {
  width: 74px; height: 74px;
  border-radius: 50%;
  display: grid;
  place-items: center;
  background: rgba(22,163,74,.3);
  color: #fff;
}

.brandline {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin: 8px 0 0;
  font-weight: 800;
  opacity: .8;
}

:focus-visible { outline: 3px solid var(--yellow); outline-offset: 3px; border-radius: 10px; }

@media (prefers-reduced-motion: reduce) {
  * { transition-duration: 0.01ms !important; }
}
