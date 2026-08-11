(() => {
  'use strict';

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  // ---------- Música de fondo + narración de Alice ----------
  // Mismos valores que el kiosco (src/App.jsx): 0.15 de ambiente, baja a 0.04
  // mientras habla una voz para que se entienda sin competir con la música.
  const MUSIC_VOL = 0.15;
  const MUSIC_VOL_DUCK = 0.04;
  const musicEl = document.querySelector('[data-inv-music]');
  const muteBtn = document.querySelector('[data-inv-mute]');
  const narrationIntro = document.querySelector('[data-inv-narration-intro]');
  const narrationOutro = document.querySelector('[data-inv-narration-outro]');
  const autoHero = document.querySelector('[data-inv-autoplay-once]');
  const soundActivation = document.querySelector('[data-inv-audio-activate]');
  const entryGate = document.querySelector('[data-inv-entry-gate]');
  const entryOpenButton = document.querySelector('[data-inv-entry-open]');
  let musicStarted = false;
  let musicStartPending = false;
  let introNarrationTriggered = false;
  let introNarrationPending = false;
  let startAutoHero = null;
  let outroPrimed = false;
  let duckDepth = 0; // cuántas voces piden música baja a la vez (evita que una termine y suba encima de otra)

  const applyMusicVolume = () => {
    if (!musicEl) return;
    musicEl.volume = duckDepth > 0 ? MUSIC_VOL_DUCK : MUSIC_VOL;
  };

  // Reproduce un <audio> de narración bajando la música mientras dura, y la
  // devuelve a su volumen normal al terminar (o si el audio falla/no existe).
  const setSoundActivation = (visible) => {
    if (soundActivation) soundActivation.hidden = !visible;
  };

  // Safari y algunos Chrome móviles requieren desbloquear cada audio dentro de
  // un gesto real. La despedida se prepara sin emitir sonido para que el final
  // del recorrido pueda reproducirla aunque llegue desde IntersectionObserver.
  const primeOutro = () => {
    if (outroPrimed || !(narrationOutro instanceof HTMLAudioElement) || !narrationOutro.getAttribute('src')) return;
    outroPrimed = true;
    const originalVolume = narrationOutro.volume;
    narrationOutro.volume = 0;
    narrationOutro.currentTime = 0;
    const reset = () => {
      narrationOutro.pause();
      narrationOutro.currentTime = 0;
      narrationOutro.volume = originalVolume;
    };
    const attempt = narrationOutro.play();
    if (attempt && typeof attempt.then === 'function') {
      attempt.then(reset).catch(() => {
        outroPrimed = false;
        narrationOutro.volume = originalVolume;
      });
    } else {
      reset();
    }
  };
  const duckWhile = (audio) => {
    if (!(audio instanceof HTMLAudioElement) || !audio.getAttribute('src')) return Promise.resolve(false);
    duckDepth += 1;
    applyMusicVolume();
    let released = false;
    const done = () => {
      if (released) return;
      released = true;
      duckDepth = Math.max(0, duckDepth - 1);
      applyMusicVolume();
    };
    audio.currentTime = 0;
    audio.addEventListener('ended', done, { once: true });
    audio.addEventListener('error', done, { once: true });
    const attempt = audio.play();
    if (attempt && typeof attempt.catch === 'function') {
      return attempt.then(() => true).catch(() => {
        done();
        return false;
      });
    }
    return Promise.resolve(true);
  };

  const startIntroNarration = () => {
    if (introNarrationTriggered || introNarrationPending) return;
    introNarrationPending = true;
    duckWhile(narrationIntro).then((started) => {
      introNarrationTriggered = started;
      introNarrationPending = false;
      if (musicStarted && introNarrationTriggered) {
        stopListening();
        setSoundActivation(false);
      } else if (!introNarrationTriggered) {
        setSoundActivation(true);
      }
    });
  };

  // Gestos que Chrome/Safari SÍ cuentan como interacción real para permitir
  // audio con sonido. "scroll" NO cuenta (política de autoplay de Chrome) —
  // y esta página es scroll de principio a fin, así que si el primer gesto
  // del invitado era justo un scroll, el intento fallaba en silencio y el
  // flag quedaba marcado como "ya iniciado" para siempre: ningún toque real
  // después volvía a intentarlo. Por eso solo se marca `musicStarted` cuando
  // `play()` confirma que arrancó de verdad, no al primer intento.
  const unlockEvents = ['pointerdown', 'touchstart', 'keydown', 'click', 'wheel'];
  const stopListening = () => {
    unlockEvents.forEach((evt) => window.removeEventListener(evt, startMusic));
  };
  const startMusic = () => {
    // In video mode the first gesture starts video, music and Alice together.
    if (typeof startAutoHero === 'function') startAutoHero();
    primeOutro();
    if (musicStarted) {
      if (introNarrationTriggered || !narrationIntro) {
        stopListening();
      } else {
        startIntroNarration();
      }
      return;
    }
    if (musicStartPending) return;
    musicStartPending = true;
    const onStarted = () => {
      musicStarted = true;
      musicStartPending = false;
      if (muteBtn) muteBtn.hidden = false;
      if (introNarrationTriggered || !narrationIntro) {
        stopListening();
        setSoundActivation(false);
      }
    };
    if (!musicEl) {
      onStarted();
    } else {
      musicEl.volume = MUSIC_VOL;
      const attempt = musicEl.play();
      if (attempt && typeof attempt.then === 'function') {
        attempt.then(onStarted).catch(() => {
          musicStartPending = false;
          // La rueda no autoriza audio en varios navegadores: el botón
          // visible permite que el invitado lo active con un toque.
          setSoundActivation(true);
        });
      } else {
        onStarted();
      }
    }
    // La narración de inicio entra junto con la música, igual que en el
    // kiosco (voz del personaje apenas arranca la experiencia). No depende
    // de si la música pudo arrancar o no — pero sí debe sonar una sola vez:
    // pointerdown y touchstart pueden llegar los dos por el mismo toque
    // antes de que el play() de la música resuelva.
    startIntroNarration();
  };

  if ((musicEl || narrationIntro || autoHero) && !entryGate) {
    unlockEvents.forEach((evt) => {
      window.addEventListener(evt, startMusic, { passive: true });
    });
  }

  if (entryGate && entryOpenButton) {
    document.documentElement.classList.add('inv-entry-gate-active');
    document.body.classList.add('inv-entry-gate-active');
    const envelope = entryOpenButton.querySelector('.inv-envelope');
    const card = entryOpenButton.closest('.inv-entry-gate-card');
    let opened = false;
    entryOpenButton.addEventListener('click', () => {
      if (opened) return;
      opened = true;
      // El desbloqueo de audio va PRIMERO y sin esperar nada: tiene que
      // quedar dentro del mismo gesto de click para que el navegador lo
      // cuente como iniciado por el usuario. La animación del sobre corre
      // en paralelo, no antes.
      startMusic();
      entryOpenButton.disabled = true;

      const finish = () => {
        entryGate.hidden = true;
        document.documentElement.classList.remove('inv-entry-gate-active');
        document.body.classList.remove('inv-entry-gate-active');
      };

      if (reducedMotion.matches || !envelope) {
        finish();
        return;
      }
      // Solapa abre, la carta asoma con un pequeño desfase (ver CSS), y
      // recién ahí toda la tarjeta se desvanece hacia la invitación real.
      envelope.classList.add('is-opening');
      window.setTimeout(() => {
        if (card) card.classList.add('is-leaving');
        window.setTimeout(finish, 380);
      }, 620);
    });
  }

  if (soundActivation) {
    soundActivation.addEventListener('click', startMusic);
  }

  if (muteBtn) {
    muteBtn.addEventListener('click', () => {
      const muted = musicEl ? !musicEl.muted : false;
      if (musicEl) musicEl.muted = muted;
      muteBtn.textContent = muted ? '🔇' : '🎵';
      muteBtn.setAttribute('aria-label', muted ? 'Activar música' : 'Silenciar música');
    });
  }

  // Narración de despedida: una sola vez, cuando el invitado llega a la
  // sección final (guardar/compartir) — el mismo punto exista o no capítulos
  // o lista de reproducción por delante, porque esa sección siempre está.
  if (narrationOutro) {
    const outroTrigger = document.querySelector('[data-inv-narration-outro-trigger]');
    if (outroTrigger && typeof IntersectionObserver === 'function') {
      let outroPlayed = false;
      const outroObserver = new IntersectionObserver((entries) => {
        if (outroPlayed || !entries[0].isIntersecting) return;
        outroPlayed = true;
        duckWhile(narrationOutro);
        outroObserver.disconnect();
      }, { threshold: 0.4 });
      outroObserver.observe(outroTrigger);
    }
  }

  // El loop de fondo es movimiento permanente que el invitado no puede parar.
  // Con movimiento reducido se congela en su primer fotograma, que sigue
  // sirviendo como imagen de fondo.
  // El video de entrada no se reproduce solo nunca: lo gobierna el scroll.
  // Sin excluirlo aquí, este bloque le ponía `loop` y lo hacía reproducirse,
  // peleando con el control por scroll. El candidato "auto" tampoco entra:
  // debe reproducirse UNA vez, no en loop; tiene su propio bloque más abajo.
  const heroVideo = document.querySelector('.inv-hero-media:not([data-inv-scrub]):not([data-inv-autoplay-once])');
  const applyMotionPreference = () => {
    if (!(heroVideo instanceof HTMLVideoElement)) return;
    if (reducedMotion.matches) {
      heroVideo.pause();
      heroVideo.removeAttribute('loop');
    } else if (heroVideo.paused) {
      heroVideo.setAttribute('loop', '');
      heroVideo.play().catch(() => {
        // Si el navegador bloquea el autoplay queda el póster del tema.
      });
    }
  };
  applyMotionPreference();
  if (reducedMotion.addEventListener) {
    reducedMotion.addEventListener('change', applyMotionPreference);
  }

  // Compartir la pieza en sí (imagen o video), no solo el enlace. WhatsApp no
  // admite adjuntar un archivo remoto por URL, así que la única vía real es la
  // Web Share API: el selector nativo del teléfono ofrece WhatsApp y el archivo
  // llega al chat. Donde no exista, queda el botón de envío por enlace.
  const shareRoot = document.querySelector('[data-inv-share]');
  const shareButton = shareRoot && shareRoot.querySelector('[data-share-file]');
  const shareStatus = shareRoot && shareRoot.querySelector('[data-share-status]');

  if (shareRoot && shareButton && typeof navigator.share === 'function' && typeof navigator.canShare === 'function') {
    const kind = shareRoot.dataset.shareKind === 'video' ? 'video' : 'image';
    const extension = kind === 'video' ? 'mp4' : 'jpg';
    const mime = kind === 'video' ? 'video/mp4' : 'image/jpeg';
    const label = shareRoot.dataset.shareLabel || 'invitación';

    // Sonda barata: se pregunta por un archivo del tipo correcto antes de
    // descargar nada, para no bajar un video de varios MB y descubrir después
    // que el navegador no puede compartirlo.
    let supportsFiles = false;
    try {
      supportsFiles = navigator.canShare({ files: [new File([new Blob([''], { type: mime })], 'probe.' + extension, { type: mime })] });
    } catch (error) {
      supportsFiles = false;
    }

    if (supportsFiles) {
      shareButton.hidden = false;
      let busy = false;

      shareButton.addEventListener('click', async () => {
        if (busy) return;
        busy = true;
        shareButton.disabled = true;
        if (shareStatus) shareStatus.textContent = 'Preparando la ' + label + '…';

        try {
          const response = await fetch(shareRoot.dataset.shareUrl, { credentials: 'same-origin' });
          if (!response.ok) throw new Error('HTTP ' + response.status);
          const blob = await response.blob();
          const safeName = (shareRoot.dataset.shareName || 'invitacion')
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .replace(/[^a-zA-Z0-9-]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase() || 'invitacion';
          const file = new File([blob], safeName + '.' + extension, { type: blob.type || mime });

          if (!navigator.canShare({ files: [file] })) throw new Error('unsupported');

          await navigator.share({
            files: [file],
            title: shareRoot.dataset.shareTitle || 'Invitación',
            text: shareRoot.dataset.shareText || '',
          });
          if (shareStatus) shareStatus.textContent = '';
        } catch (error) {
          // Cancelar el selector nativo no es un fallo: no se avisa nada.
          if (error && error.name === 'AbortError') {
            if (shareStatus) shareStatus.textContent = '';
          } else if (shareStatus) {
            shareStatus.textContent = 'No se pudo compartir la ' + label + '. Puedes descargarla o enviarla por WhatsApp.';
          }
        } finally {
          busy = false;
          shareButton.disabled = false;
        }
      });
    }
  }

  // ---------- Comparación "auto": video se reproduce una sola vez ----------
  // No lo gobierna el scroll: corre solo, y cuando termina se resalta la
  // pista de abajo para que el invitado sepa que ya puede seguir bajando.
  // Sin esto el video termina y queda en su último cuadro sin ninguna señal.
  // startAutoChapters se define más abajo (bloque "capítulos automáticos");
  // sigue siendo `undefined` acá si esa sección no existe en esta página, y
  // el chequeo de tipo antes de llamarla cubre ese caso sin acoplar el orden.
  if (autoHero instanceof HTMLVideoElement) {
    const hint = document.querySelector('[data-inv-auto-hint]');
    let ready = false;
    let heroStarted = false;
    let lockedScrollY = 0;
    const lockScroll = () => {
      lockedScrollY = window.scrollY;
      document.documentElement.classList.add('inv-scroll-locked');
      document.body.classList.add('inv-scroll-locked');
      document.body.style.top = '-' + lockedScrollY + 'px';
    };
    const unlockScroll = () => {
      document.documentElement.classList.remove('inv-scroll-locked');
      document.body.classList.remove('inv-scroll-locked');
      document.body.style.top = '';
      window.scrollTo(0, lockedScrollY);
    };
    const markReady = () => {
      if (ready) return;
      ready = true;
      unlockScroll();
      if (hint) {
        hint.classList.remove('inv-scroll-hint--waiting');
        hint.classList.add('inv-scroll-hint--ready');
        hint.removeAttribute('aria-hidden');
      }
    };
    if (reducedMotion.matches) {
      autoHero.pause();
      markReady();
    } else {
      autoHero.addEventListener('ended', markReady, { once: true });
      lockScroll();
      startAutoHero = () => {
        if (heroStarted) return;
        heroStarted = true;
        autoHero.currentTime = 0;
        autoHero.play().catch(markReady);
      };
    }
  }

  // ---------- Entrada inmersiva ----------
  // El scroll dentro de la sección controla el fotograma del video, de modo
  // que el invitado avanza dentro de la escena en vez de ver un fondo que se
  // repite. El asset viene con un keyframe por cuadro, así que el salto es
  // inmediato. Puede haber más de una en la página (el hero, y cualquier
  // "momento" de video que se agregue más abajo): cada una es independiente,
  // con su propio recorrido y su propio video.
  const dives = Array.from(document.querySelectorAll('[data-inv-dive]'));

  dives.forEach((dive) => {
    const scrub = dive.querySelector('[data-inv-scrub]');
    if (!scrub || reducedMotion.matches) {
      if (scrub) {
        // Sin scrub la sección deja de ser alta y vuelve a ser una sola
        // pantalla; con movimiento reducido tampoco tiene sentido el
        // recorrido, así que se comporta igual.
        dive.classList.remove('inv-hero--dive', 'inv-moment--dive');
        scrub.setAttribute('loop', '');
        scrub.play().catch(() => {});
      }
      return;
    }

    const copy = dive.querySelector('.inv-hero-copy');
    const hint = dive.querySelector('.inv-scroll-hint');
    const momentCaption = dive.querySelector('.inv-moment-caption');
    let duration = 0;
    let ticking = false;
    let inView = true;

    const paint = () => {
      ticking = false;
      if (!duration || !inView) return;
      const rect = dive.getBoundingClientRect();
      const travel = dive.offsetHeight - window.innerHeight;
      if (travel <= 0) return;
      const progress = Math.min(1, Math.max(0, -rect.top / travel));

      const time = progress * duration;
      // Reasignar el mismo tiempo fuerza decodificaciones inútiles.
      if (Math.abs(scrub.currentTime - time) > 0.01) scrub.currentTime = time;

      if (copy) {
        // El texto se va en el primer tercio: después estorba la escena.
        const fade = Math.min(1, progress / 0.38);
        copy.style.opacity = String(1 - fade);
        copy.style.transform = 'translateY(' + (-fade * 42).toFixed(1) + 'px) scale(' + (1 - fade * 0.06).toFixed(3) + ')';
      }
      if (hint) hint.style.opacity = String(Math.max(0, 1 - progress / 0.18));
      // Los "momentos" sin nombre/fecha usan su propia leyenda corta: se ve
      // solo mientras la escena todavía está entrando, igual que el nombre
      // en el hero.
      if (momentCaption) momentCaption.style.opacity = String(Math.max(0, 1 - progress / 0.3));

      // El desenfoque y el velo existen para que el texto se lea sobre la
      // escena. Cuando ya se fue estorban: dejan un borrón. Se abren a
      // medida que se entra, y ese despeje ES la sensación de atravesar
      // hacia dentro.
      const clear = Math.min(1, progress / 0.55);
      dive.style.setProperty('--dive-clear', clear.toFixed(3));
    };

    const request = () => {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(paint);
    };

    const start = () => {
      duration = Number.isFinite(scrub.duration) && scrub.duration > 0 ? scrub.duration : 0;
      if (duration) {
        scrub.pause();
        paint();
      }
    };

    scrub.addEventListener('loadedmetadata', start);

    // Saltar de fotograma exige que el servidor acepte peticiones `Range`, y
    // no todos lo hacen (el servidor embebido de PHP responde 200 en vez de
    // 206, y ahí `currentTime` simplemente se ignora). Cargar el archivo
    // completo como blob elimina esa dependencia y deja el salto instantáneo.
    // Son pocos MB y mientras tanto la sección funciona como fondo fijo.
    fetch(scrub.currentSrc || scrub.src, { credentials: 'same-origin' })
      .then((response) => (response.ok ? response.blob() : Promise.reject(new Error('HTTP ' + response.status))))
      .then((blob) => {
        scrub.src = URL.createObjectURL(blob);
        scrub.load();
      })
      .catch(() => {
        // Sin blob queda el video tal cual: si el hosting sí acepta Range el
        // recorrido funciona igual, y si no, la sección se ve estática.
      });

    if (scrub.readyState >= 1) start();
    // Safari en iOS no expone fotogramas hasta que el video se ha "tocado":
    // un play/pause silencioso lo desbloquea sin que se note.
    scrub.play().then(() => scrub.pause()).catch(() => {});

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request, { passive: true });

    // Fuera de pantalla no hay nada que decodificar.
    if (typeof IntersectionObserver === 'function') {
      new IntersectionObserver((entries) => {
        inView = entries[0].isIntersecting;
        if (inView) request();
      }, { rootMargin: '100px' }).observe(dive);
    }
  });

  // ---------- Agregar a mi calendario ----------
  // Un .ics generado en el navegador lo entienden Apple Calendar, Outlook y
  // Android; el enlace de Google Calendar solo sirve a quien use Google. Con
  // JS se prefiere el .ics y se esconde el enlace; sin JS queda el enlace,
  // que es mejor que nada.
  const calendarCard = document.querySelector('[data-inv-calendar]');
  const calendarButton = calendarCard && calendarCard.querySelector('[data-cal-add]');
  const calendarFallback = calendarCard && calendarCard.querySelector('[data-cal-fallback]');

  if (calendarCard && calendarButton && typeof Blob === 'function' && 'download' in document.createElement('a')) {
    calendarButton.hidden = false;
    if (calendarFallback) calendarFallback.hidden = true;

    calendarButton.addEventListener('click', () => {
      const data = calendarCard.dataset;
      // Las comas, los punto y coma y los saltos son separadores dentro de
      // iCalendar: sin escaparlos, una dirección con coma parte el campo y
      // el evento llega roto.
      const escapeIcs = (value) => String(value || '')
        .replace(/\\/g, '\\\\')
        .replace(/;/g, '\\;')
        .replace(/,/g, '\\,')
        .replace(/\r?\n/g, '\\n');

      const stamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
      const lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//CumpleClick//Invitacion//ES',
        'CALSCALE:GREGORIAN',
        'BEGIN:VEVENT',
        'UID:' + stamp + '-cumpleclick@invitacion',
        'DTSTAMP:' + stamp,
        // Sin sufijo Z: es hora local del invitado, que es la hora a la que
        // parte la fiesta. Convertirla a UTC la correría según la zona del
        // teléfono.
        'DTSTART:' + data.calStart,
        'DTEND:' + data.calEnd,
        'SUMMARY:' + escapeIcs(data.calTitle),
        data.calLocation ? 'LOCATION:' + escapeIcs(data.calLocation) : '',
        data.calUrl ? 'DESCRIPTION:' + escapeIcs('Invitación: ' + data.calUrl) : '',
        'BEGIN:VALARM',
        'TRIGGER:-P1D',
        'ACTION:DISPLAY',
        'DESCRIPTION:' + escapeIcs(data.calTitle),
        'END:VALARM',
        'END:VEVENT',
        'END:VCALENDAR',
      ].filter(Boolean);

      // CRLF: el estándar lo exige y Outlook rechaza el archivo sin él.
      const blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = (data.calFilename || 'cumpleanos')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-zA-Z0-9-]+/g, '-').replace(/^-+|-+$/g, '').toLowerCase() + '.ics';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
  }

  // Fundido + zoom de un frame de capítulos dado un progreso 0..1. La usan
  // tanto el modo scroll como el modo automático: es la misma coreografía,
  // solo cambia qué produce el `progress` (el dedo o un reloj). Cada slot
  // tiene su propio ancho (`spans`): una imagen vale 1, pero un video que
  // vive dentro del mismo recorrido puede valer más, para tener ancho de
  // scroll donde recorrerse cuadro a cuadro en vez de solo cruzarse.
  const paintChapterFrame = (els, progress) => {
    const { layers, spans, totalUnits, captions, dots, progressBar, count, hint } = els;
    const position = Math.max(0, Math.min(totalUnits, progress * totalUnits));

    // Encuentra en qué slot cae `position`, recorriendo los anchos
    // acumulados en vez de dividir en partes iguales.
    let currentIndex = count - 1;
    let offset = 0;
    for (let i = 0; i < count; i++) {
      const span = spans[i];
      if (position < offset + span || i === count - 1) {
        currentIndex = i;
        break;
      }
      offset += span;
    }
    const currentSpan = spans[currentIndex] || 1;
    const frac = Math.max(0, Math.min(1, (position - offset) / currentSpan));

    layers.forEach((layer, i) => {
      let opacity = 0;
      let rel = 0;
      // Sin esto el recorrido es un pase de diapositivas: las imágenes se
      // funden pero nada se mueve. `rel` va de -1 "entrando" a +1 "ya pasó",
      // así que en todo momento hay movimiento continuo aunque las capas
      // sean fijas.
      if (i === currentIndex) {
        opacity = 1 - frac;
        rel = frac;
      } else if (i === currentIndex + 1) {
        opacity = frac;
        rel = frac - 1;
      }
      layer.style.opacity = String(opacity);

      if (opacity > 0) {
        // Entra grande y se va asentando: la escena avanza hacia el
        // invitado en vez de aparecer y quedarse quieta.
        const scale = (1.16 - rel * 0.13).toFixed(4);
        // Parallax vertical suave: la saliente sigue subiendo mientras la
        // entrante todavía viene desde abajo.
        const shiftY = (rel * -4.5).toFixed(2);
        layer.style.transform = 'scale(' + scale + ') translate3d(0,' + shiftY + '%,0)';
      }

      // Si esta capa es un video y es la protagonista del momento, el mismo
      // `frac` que gobierna su opacidad gobierna también su fotograma: el
      // scroll lo recorre de punta a punta mientras dura su turno.
      const video = layer.__chapterVideo;
      if (video && video.__chapterDuration && i === currentIndex) {
        const time = frac * video.__chapterDuration;
        if (Math.abs(video.currentTime - time) > 0.02) video.currentTime = time;
      }
    });

    const activeIndex = frac >= 0.5 ? Math.min(count - 1, currentIndex + 1) : currentIndex;
    captions.forEach((caption) => {
      const idx = Number(caption.dataset.chapterIndex);
      caption.classList.toggle('is-active', idx === activeIndex);
    });
    dots.forEach((dot) => {
      const idx = Number(dot.dataset.chapterIndex);
      dot.classList.toggle('is-active', idx === activeIndex);
    });
    if (progressBar) progressBar.style.transform = 'scaleX(' + progress.toFixed(4) + ')';
    // Solo en el último slot: antes de eso todavía hay historia por contar
    // y el CTA estorbaría.
    if (hint) hint.classList.toggle('is-visible', activeIndex === count - 1);
  };

  // ---------- Capítulos ilustrados en scroll (versión 2) ----------
  // Progreso continuo según cuánto se hizo scroll dentro de la sección:
  // cada frame solo dos slots están visibles a la vez (el actual y el
  // siguiente). Un slot puede ser una imagen o un video real —el que
  // muestra a Rayo cruzando la meta vive adentro del mismo recorrido, no en
  // una sección aparte, así que necesita más ancho que los demás.
  const chaptersSections = Array.from(document.querySelectorAll('[data-chapters]'));
  chaptersSections.forEach((chaptersSection) => {
    const layers = Array.from(chaptersSection.querySelectorAll('[data-chapter-layer]'));
    const spans = layers.map((layer) => Number(layer.dataset.chapterSpan) || 1);
    const totalUnits = spans.reduce((sum, span) => sum + span, 0);
    const captions = Array.from(chaptersSection.querySelectorAll('[data-chapter-caption]'));
    const dots = Array.from(chaptersSection.querySelectorAll('[data-chapter-dot]'));
    const progressBar = chaptersSection.querySelector('[data-chapters-progress-bar]');
    const hint = chaptersSection.querySelector('[data-chapters-hint]');
    const count = layers.length;
    const els = { layers, spans, totalUnits, captions, dots, progressBar, count, hint };

    // Prepara los videos que viven dentro del recorrido: mismo truco que el
    // hero (cargar como blob) para poder saltar de cuadro sin depender de
    // que el servidor acepte peticiones `Range`.
    layers.forEach((layer) => {
      const video = layer.querySelector('[data-chapter-video]');
      if (!video) return;
      layer.__chapterVideo = video;
      video.__chapterDuration = 0;
      const onMeta = () => {
        video.__chapterDuration = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 0;
        video.pause();
      };
      video.addEventListener('loadedmetadata', onMeta);
      fetch(video.currentSrc || video.src, { credentials: 'same-origin' })
        .then((response) => (response.ok ? response.blob() : Promise.reject(new Error('HTTP ' + response.status))))
        .then((blob) => {
          video.src = URL.createObjectURL(blob);
          video.load();
        })
        .catch(() => {
          // Sin blob queda el video tal cual: si el hosting sí acepta Range
          // el recorrido funciona igual, y si no, se ve como fondo fijo.
        });
      if (video.readyState >= 1) onMeta();
      // Safari en iOS no expone fotogramas hasta que el video se ha
      // "tocado": un play/pause silencioso lo desbloquea sin que se note.
      video.play().then(() => video.pause()).catch(() => {});
    });

    if (reducedMotion.matches || count < 2) {
      // Sin movimiento: la primera capa queda de fondo fijo (el CSS ya
      // colapsa la sección a una sola pantalla) y la primera leyenda, si
      // existe, queda visible sin animarla. Con un solo capítulo, ya es el
      // último: el CTA se muestra de entrada.
      if (captions[0]) captions[0].classList.add('is-active');
      if (dots[0]) dots[0].classList.add('is-active');
      if (hint) hint.classList.add('is-visible');
    } else {
      let ticking = false;
      let inView = true;

      const paintChapters = () => {
        ticking = false;
        if (!inView) return;
        const rect = chaptersSection.getBoundingClientRect();
        const travel = chaptersSection.offsetHeight - window.innerHeight;
        if (travel <= 0) return;
        const progress = Math.min(1, Math.max(0, -rect.top / travel));
        paintChapterFrame(els, progress);
      };

      const requestChapters = () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(paintChapters);
      };

      paintChapters();
      window.addEventListener('scroll', requestChapters, { passive: true });
      window.addEventListener('resize', requestChapters, { passive: true });

      if (typeof IntersectionObserver === 'function') {
        new IntersectionObserver((entries) => {
          inView = entries[0].isIntersecting;
          if (inView) requestChapters();
        }, { rootMargin: '100px' }).observe(chaptersSection);
      }
    }
  });

  // ---------- Lista de reproducción de videos reales, sin scroll ----------
  // Nada de imágenes fundiéndose entre sí: son los videos de saludo que ya
  // existen y están aprobados, uno detrás de otro. Un solo <video> cambia de
  // fuente cuando cada clip termina.
  const playlistSection = document.querySelector('[data-video-playlist]');
  if (playlistSection) {
    const video = playlistSection.querySelector('[data-playlist-video]');
    const narration = playlistSection.querySelector('[data-playlist-narration]');
    const caption = playlistSection.querySelector('[data-playlist-caption]');
    const progressBar = playlistSection.querySelector('[data-playlist-progress-bar]');
    const hint = playlistSection.querySelector('[data-playlist-hint]');
    const dataScript = playlistSection.querySelector('[data-playlist-data]');
    let clips = [];
    try {
      clips = dataScript ? JSON.parse(dataScript.textContent) : [];
    } catch (error) {
      clips = [];
    }

    let started = false;

    const runPlaylist = () => {
      if (started || !video || !clips.length) return;
      started = true;

      if (reducedMotion.matches) {
        // Sin movimiento no hay lista: el póster del último clip (el cierre
        // "¡Te esperamos!") queda como imagen fija, igual que en el resto
        // del sitio con este ajuste activado.
        const last = clips[clips.length - 1];
        video.src = last.url;
        video.currentTime = 0;
        if (caption) caption.textContent = last.caption || '';
        if (progressBar) progressBar.style.transform = 'scaleX(1)';
        if (hint) hint.classList.add('is-visible');
        return;
      }

      let index = 0;
      const playAt = (i) => {
        index = i;
        const clip = clips[i];
        video.src = clip.url;
        if (caption) caption.textContent = clip.caption || '';
        if (progressBar) progressBar.style.transform = 'scaleX(' + ((i) / clips.length).toFixed(4) + ')';
        // El último clip es "¡Te esperamos!": ahí, y no antes, aparece el
        // acceso a la invitación.
        if (hint) hint.classList.toggle('is-visible', i === clips.length - 1);
        // Cada capítulo trae su propia línea de Alice (los clips van sin
        // audio propio): si no existe el MP3 de este capítulo en particular,
        // sigue mudo, sin romper el resto de la lista.
        if (narration) {
          narration.pause();
          if (clip.narration) {
            narration.src = clip.narration;
            duckWhile(narration);
          } else {
            narration.removeAttribute('src');
          }
        }
        video.play().catch(() => {
          // Si un clip puntual no puede reproducirse, se salta al
          // siguiente en vez de dejar la lista trabada en silencio.
          advance();
        });
      };
      const advance = () => {
        if (index + 1 < clips.length) {
          playAt(index + 1);
        } else if (progressBar) {
          progressBar.style.transform = 'scaleX(1)';
        }
      };
      video.addEventListener('ended', advance);
      playAt(0);
    };

    // The playlist waits until the guest scrolls to it after the intro video.
    if (typeof IntersectionObserver === 'function') {
      const observer = new IntersectionObserver((entries) => {
        if (!entries[0].isIntersecting) return;
        runPlaylist();
        observer.disconnect();
      }, { threshold: 0.4 });
      observer.observe(playlistSection);
    } else {
      runPlaylist();
    }
  }

  const revealables = Array.from(document.querySelectorAll('.inv-reveal'));
  if (!revealables.length) return;

  // Sin IntersectionObserver o con movimiento reducido no se oculta nada:
  // la clase que activa la transición solo se añade cuando se puede animar.
  if (reducedMotion.matches || typeof IntersectionObserver !== 'function') {
    return;
  }

  document.documentElement.classList.add('inv-js');

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('is-visible');
      observer.unobserve(entry.target);
    });
  }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

  revealables.forEach((element) => observer.observe(element));

  // Red de seguridad: si algo impide que el observador dispare, el contenido
  // no puede quedarse invisible para siempre.
  window.setTimeout(() => {
    revealables.forEach((element) => element.classList.add('is-visible'));
  }, 2500);
})();
