(() => {
  'use strict';

  const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'video[controls]',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  document.querySelectorAll('[data-event-profile]').forEach((root) => {
    const trigger = root.querySelector('[data-ep-open]');
    const dialog = root.querySelector('[data-ep-dialog]');
    // Hay dos cierres: la ✕ superior y el botón al final del recorrido, que
    // es el alcanzable con una mano en móvil.
    const closeButtons = Array.from(root.querySelectorAll('[data-ep-close]'));
    const closeButton = closeButtons[0] || null;
    const skipButton = root.querySelector('[data-ep-skip]');
    const soundButton = root.querySelector('[data-ep-sound]');
    const intro = root.querySelector('[data-ep-intro]');
    const profile = root.querySelector('[data-ep-profile]');
    const video = root.querySelector('[data-ep-video]');
    const tabs = Array.from(root.querySelectorAll('[role="tab"]'));
    let opener = null;
    let fallbackOpen = false;

    if (!trigger || !dialog || !profile) return;

    const focusables = () => Array.from(dialog.querySelectorAll(focusableSelector))
      .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.offsetParent !== null);

    const focusInitial = () => {
      const target = intro && !intro.hidden ? skipButton : (tabs[0] || closeButton);
      window.requestAnimationFrame(() => target?.focus());
    };

    const revealProfile = (animated = true) => {
      if (video) video.pause();
      if (intro) intro.hidden = true;
      profile.hidden = false;
      skipButton?.setAttribute('hidden', '');
      if (animated && !reducedMotion.matches) profile.classList.add('ep-fallback-enter');
      window.requestAnimationFrame(() => (tabs[0] || closeButton)?.focus());
    };

    const resetIntro = () => {
      profile.classList.remove('ep-fallback-enter');
      // Reabrir siempre empieza arriba: si no, la ficha aparece a media
      // altura con el encabezado del protagonista fuera de pantalla.
      profile.scrollTop = 0;
      if (!intro || !video || reducedMotion.matches) {
        if (intro) intro.hidden = true;
        profile.hidden = false;
        skipButton?.setAttribute('hidden', '');
        if (!reducedMotion.matches) profile.classList.add('ep-fallback-enter');
        return;
      }

      intro.hidden = false;
      profile.hidden = true;
      skipButton?.removeAttribute('hidden');
      video.currentTime = 0;
      video.muted = true;
      if (soundButton) {
        // Solo el texto: `textContent` sobre el botón borraría los iconos.
        const label = soundButton.querySelector('[data-ep-sound-label]');
        if (label) label.textContent = 'Activar sonido';
        else soundButton.textContent = 'Activar sonido';
        soundButton.setAttribute('aria-pressed', 'false');
      }
    };

    const open = () => {
      opener = document.activeElement instanceof HTMLElement ? document.activeElement : trigger;
      resetIntro();
      document.body.classList.add('ep-lock');

      if (typeof dialog.showModal === 'function') {
        if (!dialog.open) dialog.showModal();
      } else {
        dialog.setAttribute('open', '');
        dialog.setAttribute('aria-modal', 'true');
        fallbackOpen = true;
      }

      if (video && intro && !intro.hidden) {
        const playAttempt = video.play();
        if (playAttempt && typeof playAttempt.catch === 'function') {
          playAttempt.catch(() => {
            // El póster y "Omitir" siguen disponibles si el navegador bloquea autoplay.
          });
        }
      }
      focusInitial();
    };

    const close = () => {
      video?.pause();
      if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
        return;
      }
      dialog.removeAttribute('open');
      fallbackOpen = false;
      document.body.classList.remove('ep-lock');
      opener?.focus();
    };

    const onClosed = () => {
      document.body.classList.remove('ep-lock');
      opener?.focus();
    };

    const selectTab = (tab, moveFocus = true) => {
      tabs.forEach((candidate) => {
        const selected = candidate === tab;
        candidate.setAttribute('aria-selected', selected ? 'true' : 'false');
        candidate.tabIndex = selected ? 0 : -1;
        const panelId = candidate.getAttribute('aria-controls');
        const panel = panelId ? document.getElementById(panelId) : null;
        if (panel) panel.hidden = !selected;
      });
      // Cambiar de protagonista muestra su ficha desde el principio, no desde
      // el punto donde quedó la anterior.
      profile.scrollTop = 0;
      if (moveFocus) tab.focus();
    };

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => selectTab(tab, false));
      tab.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        let next = index;
        if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
        if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = tabs.length - 1;
        selectTab(tabs[next]);
      });
    });

    trigger.addEventListener('click', open);
    closeButtons.forEach((button) => button.addEventListener('click', close));
    skipButton?.addEventListener('click', () => revealProfile());
    video?.addEventListener('ended', () => revealProfile());
    video?.addEventListener('error', () => revealProfile(false));
    dialog.addEventListener('close', onClosed);
    dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      close();
    });

    soundButton?.addEventListener('click', () => {
      if (!video) return;
      video.muted = !video.muted;
      const label = soundButton.querySelector('[data-ep-sound-label]');
      const text = video.muted ? 'Activar sonido' : 'Silenciar';
      if (label) label.textContent = text;
      else soundButton.textContent = text;
      soundButton.setAttribute('aria-pressed', video.muted ? 'false' : 'true');
      if (video.paused) video.play().catch(() => {});
    });

    dialog.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && fallbackOpen) {
        event.preventDefault();
        close();
        return;
      }
      if (event.key !== 'Tab') return;
      const available = focusables();
      if (!available.length) {
        event.preventDefault();
        return;
      }
      const first = available[0];
      const last = available[available.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    if (reducedMotion.addEventListener) {
      reducedMotion.addEventListener('change', (event) => {
        if (event.matches && dialog.open && intro && !intro.hidden) revealProfile(false);
      });
    }
  });
})();
