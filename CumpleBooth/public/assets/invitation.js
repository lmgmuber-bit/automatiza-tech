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
  const themeIntro = document.querySelector('[data-inv-theme-intro]');
  const themeIntroVideo = document.querySelector('[data-inv-theme-intro-video]');
  const themeIntroSkip = document.querySelector('[data-inv-theme-intro-skip]');
  const themeIntroProgress = document.querySelector('[data-inv-theme-intro-progress]');
  let musicStarted = false;
  let musicStartPending = false;
  let musicPrimePromise = Promise.resolve(false);
  let introNarrationTriggered = false;
  let introNarrationPending = false;
  let startAutoHero = null;
  let outroPrimed = false;
  let introNarrationPrimed = false;
  let introNarrationPrimePending = false;
  let introNarrationPrimePromise = Promise.resolve(false);
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
    if (!(narrationIntro instanceof HTMLAudioElement) || !narrationIntro.getAttribute('src')) {
      introNarrationTriggered = true;
      return;
    }
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

  // Auto-scroll a la siguiente sección: al terminar de hablar Alice, la
  // página avanza sola hasta el final del hero (el invitado no tiene que
  // tocar nada para seguir — pedido de Luis 2026-08-12). Vale para Scroll y
  // Automática, en las dos invitaciones y en las dos temáticas (la lógica no
  // distingue tema). En Automática el video de entrada y la narración de
  // Alice corren en paralelo y no siempre terminan al mismo tiempo: si el
  // avance dispara apenas termina el video pero Alice todavía dice "Tenemos
  // el agrado de invitarte...", el scroll cae en la siguiente sección y esa
  // sección arranca SU narración encima de la de Alice (reporte de Luis
  // 2026-08-12: "se pisan"). `maybeAutoAdvance` exige las dos condiciones —
  // narración terminada Y scroll ya no bloqueado por el video — sin importar
  // cuál de las dos termine primero; `markReady` más abajo llama a esta misma
  // función cuando el video termina. Solo avanza una vez.
  let autoAdvanced = false;
  let introNarrationEnded = false;
  const autoAdvanceToNextSection = () => {
    if (autoAdvanced || reducedMotion.matches) return;
    autoAdvanced = true;
    // Apunta al elemento real que sigue al hero (capítulos, playlist de
    // personajes o detalles, según exista), no a un número calculado a
    // partir del alto del hero: si el layout todavía no está 100% asentado
    // en ese instante (poster/video con carga tardía), el cálculo por altura
    // puede quedar corto y no alcanza a disparar el IntersectionObserver que
    // arranca la playlist de personajes (`runPlaylist`), dejando al
    // invitado sin videos y sin haber avanzado — reporte de Luis 2026-08-12.
    const heroSection = document.querySelector('.inv-hero');
    const nextSection = heroSection ? heroSection.nextElementSibling : null;
    const target = nextSection
      ? nextSection.getBoundingClientRect().top + window.scrollY
      : (heroSection ? heroSection.getBoundingClientRect().bottom + window.scrollY : window.scrollY + window.innerHeight);
    window.scrollTo({ top: target, behavior: 'smooth' });
  };
  const maybeAutoAdvance = () => {
    if (!introNarrationEnded) return;
    if (document.body.classList.contains('inv-scroll-locked')) return;
    autoAdvanceToNextSection();
  };
  if (narrationIntro instanceof HTMLAudioElement) {
    narrationIntro.addEventListener('ended', () => {
      introNarrationEnded = true;
      maybeAutoAdvance();
    }, { once: true });
  }

  // Sin narración de Alice en la portada (los baby shower), la página NO baja
  // sola: primero hubo un hueco donde no pasaba nada nunca, después un scroll
  // automático a los 1,2s — y Luis lo devolvió: se sentía apurado, la portada
  // tiene el nombre, la fecha y los dos contadores y el invitado quiere
  // leerlos (2026-08-31: "mejor que el usuario le dé click para deslizar,
  // pero que aparezca a los 3 segundos"). Así que: el botón "Toca para
  // seguir" aparece a los 3 segundos, y el clic lleva a los videos, que ahí
  // sí corren solos. Deslizar a mano sigue funcionando desde el primer
  // instante, sin esperar al botón.
  const ESPERA_BOTON_HISTORIA = 3000;
  const botonHistoria = document.querySelector('.inv-hero [data-inv-historia]');
  let historiaArmada = false;
  const irALaHistoria = () => {
    const heroSection = document.querySelector('.inv-hero');
    const nextSection = heroSection ? heroSection.nextElementSibling : null;
    const target = nextSection
      ? nextSection.getBoundingClientRect().top + window.scrollY
      : window.scrollY + window.innerHeight;
    window.scrollTo({ top: target, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
  };
  const armarBotonHistoria = () => {
    if (historiaArmada || !botonHistoria) return;
    historiaArmada = true;
    botonHistoria.addEventListener('click', irALaHistoria);
    window.setTimeout(() => {
      botonHistoria.classList.remove('inv-scroll-hint--waiting');
      botonHistoria.classList.add('inv-scroll-hint--ready');
      botonHistoria.removeAttribute('aria-hidden');
    }, ESPERA_BOTON_HISTORIA);
  };

  // El intro temático dura 15 segundos. Si esperamos a que termine para llamar
  // `play()` sobre Alice, Chrome/iOS ya no lo consideran parte del toque del
  // sobre. Se desbloquea la pista en silencio dentro del gesto y se reinicia;
  // al entrar al hero puede reproducirse con voz aunque el cierre sea `ended`.
  const primeIntroNarration = () => {
    if (introNarrationPrimed || introNarrationPrimePending
      || !(narrationIntro instanceof HTMLAudioElement) || !narrationIntro.getAttribute('src')) {
      return introNarrationPrimePromise;
    }
    introNarrationPrimePending = true;
    const originalVolume = narrationIntro.volume;
    narrationIntro.volume = 0;
    narrationIntro.currentTime = 0;
    const reset = () => {
      narrationIntro.pause();
      narrationIntro.currentTime = 0;
      narrationIntro.volume = originalVolume;
    };
    const attempt = narrationIntro.play();
    if (attempt && typeof attempt.then === 'function') {
      introNarrationPrimePromise = attempt.then(() => {
        reset();
        introNarrationPrimed = true;
        introNarrationPrimePending = false;
        return true;
      }).catch(() => {
        reset();
        introNarrationPrimePending = false;
        return false;
      });
    } else {
      reset();
      introNarrationPrimed = true;
      introNarrationPrimePending = false;
      introNarrationPrimePromise = Promise.resolve(true);
    }
    return introNarrationPrimePromise;
  };

  // Si el intro termina justo mientras el navegador aún resuelve el play()
  // silencioso, espera su reset antes de iniciar Alice. De otro modo ese reset
  // tardío puede pausar la narración real, especialmente en el hero automático.
  const startIntroNarrationAfterPrime = () => {
    if (!introNarrationPrimePending) {
      startIntroNarration();
      return;
    }
    introNarrationPrimePromise.then(() => startIntroNarration());
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
    armarBotonHistoria();
    primeOutro();
    if (musicStarted) {
      if (introNarrationTriggered || !narrationIntro) {
        stopListening();
      } else {
        startIntroNarrationAfterPrime();
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
    startIntroNarrationAfterPrime();
  };

  // Mantiene la música corriendo a volumen cero durante el intro temático. El
  // navegador la autoriza porque este `play()` ocurre en el click del sobre;
  // recién al abrir el hero se aplica el volumen normal y se revela el mute.
  const primeMusicForThemeIntro = () => {
    if (!(musicEl instanceof HTMLAudioElement) || !musicEl.getAttribute('src')
      || musicStarted || musicStartPending) return musicPrimePromise;
    musicStartPending = true;
    musicEl.volume = 0;
    const attempt = musicEl.play();
    const onPrimed = () => {
      musicStarted = true;
      musicStartPending = false;
      setSoundActivation(false);
      return true;
    };
    if (attempt && typeof attempt.then === 'function') {
      musicPrimePromise = attempt.then(onPrimed).catch(() => {
        musicStartPending = false;
        setSoundActivation(true);
        return false;
      });
    } else {
      musicPrimePromise = Promise.resolve(onPrimed());
    }
    return musicPrimePromise;
  };

  const startInvitationAudioAfterThemeIntro = () => {
    if (typeof startAutoHero === 'function') startAutoHero();
    armarBotonHistoria();
    primeOutro();
    const activateAudio = () => {
      if (musicStarted) {
        applyMusicVolume();
        if (muteBtn) muteBtn.hidden = false;
        setSoundActivation(false);
        startIntroNarrationAfterPrime();
        return;
      }
      startMusic();
    };
    if (musicStartPending) {
      musicPrimePromise.then(activateAudio);
      return;
    }
    activateAudio();
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

    const finishGate = () => {
      entryGate.hidden = true;
      document.documentElement.classList.remove('inv-entry-gate-active');
      document.body.classList.remove('inv-entry-gate-active');
    };

    let themeIntroFinished = false;
    const finishThemeIntro = () => {
      if (themeIntroFinished) return;
      themeIntroFinished = true;
      if (themeIntroVideo instanceof HTMLVideoElement) themeIntroVideo.pause();
      if (themeIntro) {
        themeIntro.classList.add('is-leaving');
        themeIntro.setAttribute('aria-hidden', 'true');
      }
      document.documentElement.classList.remove('inv-theme-intro-active');
      document.body.classList.remove('inv-theme-intro-active');
      window.setTimeout(() => {
        if (themeIntro) themeIntro.hidden = true;
      }, reducedMotion.matches ? 0 : 320);
      startInvitationAudioAfterThemeIntro();
    };

    const openThemeIntro = () => {
      if (!themeIntro || !(themeIntroVideo instanceof HTMLVideoElement)) return false;
      finishGate();
      themeIntroFinished = false;
      themeIntro.classList.remove('is-leaving');
      themeIntro.hidden = false;
      themeIntro.setAttribute('aria-hidden', 'false');
      document.documentElement.classList.add('inv-theme-intro-active');
      document.body.classList.add('inv-theme-intro-active');
      themeIntroVideo.currentTime = 0;
      themeIntroVideo.muted = false;
      themeIntroVideo.volume = 1;
      primeMusicForThemeIntro();
      primeIntroNarration();
      primeOutro();

      if (reducedMotion.matches) {
        finishThemeIntro();
        return true;
      }

      const playAttempt = themeIntroVideo.play();
      if (playAttempt && typeof playAttempt.catch === 'function') {
        playAttempt.catch(() => finishThemeIntro());
      }
      window.requestAnimationFrame(() => themeIntroSkip?.focus());
      return true;
    };

    themeIntroSkip?.addEventListener('click', finishThemeIntro);
    themeIntroVideo?.addEventListener('ended', finishThemeIntro);
    themeIntroVideo?.addEventListener('error', finishThemeIntro);
    themeIntroVideo?.addEventListener('timeupdate', () => {
      if (!themeIntroProgress || !(themeIntroVideo instanceof HTMLVideoElement)) return;
      const duration = Number.isFinite(themeIntroVideo.duration) ? themeIntroVideo.duration : 0;
      const ratio = duration > 0 ? Math.min(1, Math.max(0, themeIntroVideo.currentTime / duration)) : 0;
      themeIntroProgress.style.transform = `scaleX(${ratio})`;
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && themeIntro && !themeIntro.hidden) finishThemeIntro();
    });

    entryOpenButton.addEventListener('click', () => {
      if (opened) return;
      opened = true;
      // El desbloqueo de audio va PRIMERO y sin esperar nada: tiene que
      // quedar dentro del mismo gesto de click para que el navegador lo
      // cuente como iniciado por el usuario. La animación del sobre corre
      // en paralelo, no antes.
      entryOpenButton.disabled = true;

      // El video se inicia dentro del mismo gesto del sobre para conservar su
      // audio en iOS/Chrome. Al terminar u omitirlo recién arranca la música y
      // narración de la invitación, evitando dos pistas sonando a la vez.
      if (openThemeIntro()) return;
      startMusic();

      const finish = () => {
        finishGate();
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
      // El video de entrada terminó. Si Alice ya había terminado de hablar
      // mientras el scroll seguía bloqueado, acá recién se dispara el avance
      // automático; si Alice todavía no termina, maybeAutoAdvance no hace
      // nada y es el propio 'ended' de la narración el que lo dispara más
      // tarde — nunca antes de que las dos cosas hayan terminado.
      maybeAutoAdvance();
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
        // El acceso a la invitación aparece cuando los videos TERMINAN, no
        // al empezar el último: mientras el clip corre, un botón encima
        // compite con lo que se está contando (pedido de Luis 2026-08-31).
        if (hint) hint.classList.remove('is-visible');
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
          return;
        }
        // Se acabaron los videos. Antes esto sólo llenaba la barra y el
        // invitado quedaba mirando el último cuadro, sin nada que hacer y
        // teniendo que deslizar a mano para llegar a la tarjeta.
        if (progressBar) progressBar.style.transform = 'scaleX(1)';
        if (!hint) return;
        hint.classList.add('is-visible');
        // `block: 'nearest'` no mueve nada si el botón ya se ve —que es lo
        // normal, con la lista ocupando la pantalla completa—: sólo rescata
        // al invitado que quedó a medio scroll. Aquí no se salta a la
        // tarjeta: el último paso lo da él, tocando el botón.
        if (!reducedMotion.matches && typeof hint.scrollIntoView === 'function') {
          hint.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
      };
      video.addEventListener('ended', advance);
      playAt(0);
    };

    // La lista arranca cuando el invitado llega a ella.
    //
    // Esto usaba IntersectionObserver y ya nos costó caro dos veces: en la
    // landing del sitio el observador entregó la intersección ONCE SEGUNDOS
    // tarde en producción, y probando esta misma sección se comprobó que hay
    // navegadores donde no dispara NUNCA, con la sección ocupando la pantalla
    // completa. Cuando eso pasa el invitado se queda mirando un recuadro negro
    // y la invitación se acaba ahí, sin error ni nada raro en consola.
    //
    // Pesa más ahora que la página baja sola hasta acá: antes el invitado
    // llegaba deslizando y al menos entendía que algo tenía que pasar.
    //
    // Se mide a mano contra el viewport, que es lo único que no puede fallar.
    // El umbral es el mismo 40% de antes.
    const listaALaVista = () => {
      const alto = window.innerHeight || 0;
      if (!alto) return false;
      const caja = playlistSection.getBoundingClientRect();
      if (caja.height <= 0) return false;
      const visible = Math.min(caja.bottom, alto) - Math.max(caja.top, 0);
      return visible / Math.min(caja.height, alto) >= 0.4;
    };
    const revisarLista = (forzar) => {
      if (started) { dejarDeRevisar(); return; }
      // `forzar` lo usa el observador: si él dice que la sección se ve, se le
      // cree aunque la medición diga otra cosa (transform, zoom, contenedores
      // raros). Al revés también vale: la medición no necesita al observador.
      if (forzar !== true && !listaALaVista()) return;
      runPlaylist();
      dejarDeRevisar();
    };
    let observador = null;
    const dejarDeRevisar = () => {
      window.removeEventListener('scroll', revisarLista);
      window.removeEventListener('resize', revisarLista);
      if (observador) { observador.disconnect(); observador = null; }
    };
    window.addEventListener('scroll', revisarLista, { passive: true });
    window.addEventListener('resize', revisarLista);
    // Y una primera revisión ya, por si la sección entra en pantalla sin que
    // haya un scroll de por medio (pantallas altas, o el propio avance
    // automático que llega antes del primer evento).
    window.requestAnimationFrame(revisarLista);

    // El observador SIGUE puesto, además de la medición. No es cinturón y
    // tirantes por gusto: probando esto se encontró un navegador donde no
    // llega ni la intersección NI el evento de scroll, así que ninguno de los
    // dos alcanza por sí solo, y no hay forma de saber desde acá cuál de los
    // dos falla en el celular de un invitado. El primero que dispare gana;
    // `started` impide que la lista arranque dos veces.
    if (typeof IntersectionObserver === 'function') {
      observador = new IntersectionObserver((entries) => {
        if (!entries.some((e) => e.isIntersecting)) return;
        revisarLista(true);
      }, { threshold: 0.4 });
      observador.observe(playlistSection);
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

/* ──────────────────────────────────────────────────────────────────────────
   Lista de regalos — "Para cuando llegue"

   La sección ya viene renderizada del servidor: esto solo la vuelve
   interactiva. Sin JS la lista se ve completa y se lee bien, solo que no se
   puede reservar — mejor que una sección vacía esperando a que cargue algo.

   No hay cuentas. La identidad del invitado es un token que emite el servidor
   en la primera reserva y que guardamos acá. Es lo que le permite soltar lo
   suyo, y lo que hace que "Lo llevas tú" sea distinto de "Ya lo tomaron" sin
   que nadie más sepa quién es quién.
   ────────────────────────────────────────────────────────────────────────── */
(() => {
  const seccion = document.querySelector('[data-inv-gifts]');
  if (!seccion) return;

  const invitacion = seccion.getAttribute('data-inv-token') || '';
  const lista = seccion.querySelector('[data-gifts-list]');
  const contador = seccion.querySelector('[data-gifts-count]');
  const estado = seccion.querySelector('[data-gifts-status]');
  const botonOtro = seccion.querySelector('[data-gift-add]');
  const nota = seccion.querySelector('[data-gifts-nota]');
  const cajaYo = seccion.querySelector('[data-gifts-yo]');
  const campoNombre = seccion.querySelector('[data-gifts-nombre]');
  const cajaNuevo = seccion.querySelector('[data-gifts-nuevo]');
  const campoNuevo = seccion.querySelector('[data-gifts-nuevo-titulo]');
  const okNuevo = seccion.querySelector('[data-gifts-nuevo-ok]');
  if (!lista) return;

  // El almacenamiento puede tirar excepción (modo privado, cookies
  // bloqueadas). Que falle no puede dejar la sección inutilizable: sin token
  // igual se puede reservar, solo que después no se podrá soltar.
  const LLAVE = 'cc-regalos-' + invitacion;
  const leerToken = () => {
    try { return window.localStorage.getItem(LLAVE) || ''; } catch (e) { return ''; }
  };
  const guardarToken = (valor) => {
    try { window.localStorage.setItem(LLAVE, valor); } catch (e) { /* sin persistencia */ }
  };
  const leerNombre = () => {
    try { return window.localStorage.getItem('cc-regalos-nombre') || ''; } catch (e) { return ''; }
  };
  const guardarNombre = (valor) => {
    try { window.localStorage.setItem('cc-regalos-nombre', valor); } catch (e) { /* nada */ }
  };

  botonOtro.hidden = false;
  nota.hidden = false;
  cajaYo.hidden = false;
  campoNombre.value = leerNombre();
  campoNombre.addEventListener('change', () => guardarNombre(campoNombre.value.trim()));

  const decir = (texto, esError) => {
    estado.textContent = texto;
    estado.classList.toggle('is-error', !!esError);
  };

  const MENSAJES = {
    ya_tomado: 'Alguien lo tomó recién. Elige otro.',
    limite_alcanzado: 'Ya marcaste tres regalos. Suelta uno si quieres cambiarlo.',
    no_es_tuyo: 'Ese regalo no lo tomaste tú.',
    nombre_requerido: 'Nos falta tu nombre para anotarlo.',
    titulo_requerido: 'Escribe qué vas a regalar.',
    rate_limited: 'Demasiados intentos seguidos. Espera un momento.',
    no_encontrada: 'Esta invitación ya no está disponible.',
    servicio_no_disponible: 'No pudimos guardar el cambio. Inténtalo de nuevo.',
  };

  /** Vuelve a dibujar la lista con lo que devolvió el servidor. */
  const pintar = (datos) => {
    if (!datos || !Array.isArray(datos.items)) return;
    lista.textContent = '';
    datos.items.forEach((regalo) => {
      const li = document.createElement('li');
      li.className = 'inv-gift' + (regalo.tomado ? ' inv-gift--tomado' : '') + (regalo.mio ? ' inv-gift--mio' : '');
      li.setAttribute('data-gift-id', String(regalo.id));

      const texto = document.createElement('div');
      texto.className = 'inv-gift-texto';
      const titulo = document.createElement('p');
      titulo.className = 'inv-gift-title';
      titulo.textContent = regalo.title;
      texto.appendChild(titulo);
      if (regalo.notes) {
        const notas = document.createElement('p');
        notas.className = 'inv-gift-notes';
        notas.textContent = regalo.notes;
        texto.appendChild(notas);
      }
      li.appendChild(texto);

      if (regalo.mio) {
        // Solo quien lo tomó ve esto, y solo en su propio navegador.
        const suelta = document.createElement('button');
        suelta.className = 'inv-gift-btn inv-gift-btn--soltar';
        suelta.type = 'button';
        suelta.setAttribute('data-gift-release', '');
        suelta.textContent = 'Ya no puedo llevarlo';
        const marca = document.createElement('span');
        marca.className = 'inv-gift-estado inv-gift-estado--mio';
        marca.textContent = 'Lo llevas tú';
        li.appendChild(marca);
        li.appendChild(suelta);
      } else if (regalo.tomado) {
        const marca = document.createElement('span');
        marca.className = 'inv-gift-estado';
        // El texto depende del modo y lo pone el servidor en la sección: sin
        // lista nadie "tomó" nada, alguien va a llevarlo.
        marca.textContent = seccion.getAttribute('data-gifts-tomado') || 'Ya lo tomaron';
        li.appendChild(marca);
      } else {
        const boton = document.createElement('button');
        boton.className = 'inv-gift-btn';
        boton.type = 'button';
        boton.setAttribute('data-gift-claim', '');
        boton.textContent = 'Yo lo regalo';
        li.appendChild(boton);
      }
      lista.appendChild(li);
    });
    if (contador) {
      // En modo abierto no hay un total contra el cual medir: se cuenta lo
      // anotado. El modo lo pone el servidor en el propio elemento.
      contador.textContent = contador.getAttribute('data-gifts-modo') === 'open'
        ? (datos.total === 1 ? '1 cosa anotada' : datos.total + ' cosas anotadas')
        : datos.tomados + ' de ' + datos.total + ' ya tienen quien los lleve';
    }
    // El aviso de lista vacía deja de tener sentido apenas hay algo anotado.
    const vacio = seccion.querySelector('.inv-gifts-vacio');
    if (vacio) vacio.hidden = datos.items.length > 0;
  };

  const pedir = async (cuerpo) => {
    const respuesta = await fetch('gift-api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.assign({ t: invitacion, visitante: leerToken() }, cuerpo)),
    });
    let datos = null;
    try { datos = await respuesta.json(); } catch (e) { datos = null; }
    return datos || { ok: false, error: 'servicio_no_disponible' };
  };

  const bloquear = (boton, bloqueado) => {
    if (!boton) return;
    boton.disabled = bloqueado;
    boton.classList.toggle('is-esperando', bloqueado);
  };

  /** Devuelve el nombre escrito, o vacío tras llevar el foco al campo. */
  const pedirNombre = () => {
    const nombre = campoNombre.value.trim();
    if (!nombre) {
      decir('Escribe tu nombre para anotarte.', true);
      campoNombre.focus();
      return '';
    }
    guardarNombre(nombre);
    return nombre;
  };

  lista.addEventListener('click', async (evento) => {
    const reservar = evento.target.closest('[data-gift-claim]');
    const soltar = evento.target.closest('[data-gift-release]');
    if (!reservar && !soltar) return;

    const item = evento.target.closest('[data-gift-id]');
    if (!item) return;
    const id = parseInt(item.getAttribute('data-gift-id'), 10);

    if (soltar) {
      bloquear(soltar, true);
      decir('Soltando…');
      const datos = await pedir({ accion: 'soltar', id });
      bloquear(soltar, false);
      pintar(datos.lista);
      decir(datos.ok ? 'Listo, quedó disponible otra vez.' : (MENSAJES[datos.error] || MENSAJES.servicio_no_disponible), !datos.ok);
      return;
    }

    // El nombre sale del campo de arriba. Si está vacío no se abre ningún
    // cuadro del navegador: se lleva el foco al campo y se dice por qué.
    const nombre = pedirNombre();
    if (!nombre) return;

    bloquear(reservar, true);
    decir('Guardando…');
    const datos = await pedir({ accion: 'reservar', id, nombre });
    bloquear(reservar, false);
    if (datos.visitante) guardarToken(datos.visitante);
    pintar(datos.lista);
    decir(
      datos.ok ? '¡Anotado! Ya nadie más va a llevarlo.' : (MENSAJES[datos.error] || MENSAJES.servicio_no_disponible),
      !datos.ok
    );
  });

  botonOtro.addEventListener('click', () => {
    cajaNuevo.hidden = false;
    botonOtro.hidden = true;
    campoNuevo.focus();
  });

  campoNuevo.addEventListener('keydown', (evento) => {
    if (evento.key === 'Enter') { evento.preventDefault(); okNuevo.click(); }
  });

  okNuevo.addEventListener('click', async () => {
    const titulo = campoNuevo.value.trim();
    if (!titulo) { campoNuevo.focus(); return; }
    const nombre = pedirNombre();
    if (!nombre) return;
    bloquear(okNuevo, true);
    decir('Agregando…');
    const agregado = await pedir({ accion: 'agregar', titulo });
    if (!agregado.ok) {
      bloquear(okNuevo, false);
      decir(MENSAJES[agregado.error] || MENSAJES.servicio_no_disponible, true);
      return;
    }
    // Se agrega y se toma en el mismo gesto: quien lo escribió es quien lo lleva.
    const tomado = await pedir({ accion: 'reservar', id: agregado.id, nombre });
    bloquear(okNuevo, false);
    campoNuevo.value = '';
    cajaNuevo.hidden = true;
    botonOtro.hidden = false;
    if (tomado.visitante) guardarToken(tomado.visitante);
    pintar(tomado.lista || agregado.lista);
    decir(
      tomado.ok ? '¡Anotado! Ya nadie más va a llevarlo.' : (MENSAJES[tomado.error] || 'Lo agregamos, pero no pudimos marcarlo.'),
      !tomado.ok
    );
  });

  // Primera pasada: si este navegador ya reservó algo, hay que marcarlo
  // como suyo. El HTML del servidor no lo sabe — no puede, porque el
  // token vive solo acá — así que la marca la pone el cliente.
  if (leerToken()) {
    pedir({ accion: 'listar' }).then((datos) => {
      if (datos && datos.lista) pintar(datos.lista);
    });
  }
})();
