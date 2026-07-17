/* ============================================================
   Mujer Vibranza · Studio — interacciones editoriales premium
   Lenis + GSAP. Preloader, cursor custom, imagen flotante en
   hover de servicios, reveals de línea, parallax, magnético.
   prefers-reduced-motion: todo estático e inmediato.
   ============================================================ */
(function () {
  "use strict";

  var root = document.documentElement;
  var reduceMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var hasGsap = !reduceMotion && window.gsap && window.ScrollTrigger;
  var finePointer =
    window.matchMedia && window.matchMedia("(pointer: fine)").matches;

  var preloader = document.getElementById("preloader");

  /* ---------- Sin GSAP / reduced motion: mostrar todo ---------- */
  if (!hasGsap) {
    if (preloader) preloader.style.display = "none";
    document
      .querySelectorAll("[data-reveal]")
      .forEach(function (el) {
        el.style.opacity = "1";
        el.style.transform = "none";
      });
    var hm = document.querySelector(".hero-media");
    if (hm) hm.style.clipPath = "none";
    initUI(null);
    return;
  }

  root.classList.add("gsap");
  gsap.registerPlugin(ScrollTrigger);

  /* ---------- Lenis ---------- */
  var lenis = new Lenis({ duration: 1.15, smoothWheel: true });
  lenis.on("scroll", ScrollTrigger.update);
  gsap.ticker.add(function (t) {
    lenis.raf(t * 1000);
  });
  gsap.ticker.lagSmoothing(0);
  window.__mvLenis = lenis;
  lenis.stop(); // bloqueado durante el preloader

  window.addEventListener("load", function () {
    ScrollTrigger.refresh();
  });

  /* ---------- Preparar estados iniciales ---------- */
  var heroLines = document.querySelectorAll(".hero-title .line-in");
  gsap.set(heroLines, { yPercent: 115 });
  gsap.set(".hero-eyebrow span, .hero-sub, .hero-scroll", { y: 24, opacity: 0 });

  /* ---------- Preloader: contador + cortina ---------- */
  function startSite() {
    lenis.start();
    var tl = gsap.timeline();
    tl.to(".hero-media", {
      clipPath: "inset(0% 0 0 0)",
      duration: 1.2,
      ease: "power3.inOut"
    })
      .to(heroLines, {
        yPercent: 0,
        duration: 1,
        stagger: 0.1,
        ease: "power4.out"
      }, "-=0.7")
      .to(".hero-eyebrow span, .hero-sub, .hero-scroll", {
        y: 0,
        opacity: 1,
        duration: 0.7,
        stagger: 0.08,
        ease: "power3.out"
      }, "-=0.55");
    setupScroll();
  }

  // El intro se muestra en cada carga (decisión de la clienta 2026-07-17).
  if (preloader) {
    var counter = { v: 0 };
    var countEl = document.getElementById("preCount");
    var draws = gsap.utils.toArray(".pre-draw");
    var ripples = gsap.utils.toArray(".pre-ripple");

    // Preparar el "dibujado": cada trazo empieza oculto por su propia longitud.
    draws.forEach(function (p) {
      var len = p.getTotalLength();
      gsap.set(p, { strokeDasharray: len, strokeDashoffset: len });
    });
    gsap.set(".pre-wm .pre-m, .pre-wm .pre-v", { yPercent: 115 });
    gsap.set(".pre-iso", { scale: 0.92, transformOrigin: "50% 50%" });

    var pre = gsap.timeline({
      defaults: { ease: "power2.inOut" },
      onComplete: function () {
        preloader.classList.add("done");
        startSite();
      }
    });
    window.__mvPre = pre; // expuesta para depurar/scrub

    pre
      // Contador corre en paralelo a todo
      .to(counter, {
        v: 100,
        duration: 2.6,
        ease: "power1.inOut",
        onUpdate: function () {
          if (countEl) countEl.textContent = Math.round(counter.v);
        }
      }, 0)
      // 1. El cuerpo (con el rostro de perfil) se dibuja y se solidifica
      .to("#isoOuter", { strokeDashoffset: 0, duration: 1.05, ease: "power2.inOut" }, 0.15)
      .to("#isoOuter", { fillOpacity: 1, duration: 0.3, ease: "power1.out" }, 1.05)
      // 2. La melena fluye con sus mechones
      .to("#isoMid", { strokeDashoffset: 0, duration: 0.55, ease: "power2.inOut" }, 1.1)
      .to("#isoMid", { fillOpacity: 1, duration: 0.25, ease: "power1.out" }, 1.5)
      // 4. El anillo turquesa se dibuja
      .to("#isoRing", { strokeDashoffset: 0, duration: 0.45, ease: "power2.inOut" }, 1.55)
      // 5. El núcleo late en fucsia
      .to("#isoCore", { strokeDashoffset: 0, duration: 0.4, ease: "power2.inOut" }, 1.85)
      .to("#isoCore", { scale: 1.18, transformOrigin: "50% 50%", duration: 0.26, yoyo: true, repeat: 1, ease: "power2.out" }, 2.2)
      // 6. Ondas que salen del núcleo: Vibranza hecha movimiento
      .fromTo(
        ripples,
        { scale: 1, opacity: 0.5, transformOrigin: "50% 50%" },
        {
          scale: 1.7,
          opacity: 0,
          transformOrigin: "50% 50%",
          duration: 1.4,
          stagger: 0.28,
          ease: "power2.out"
        },
        2.3
      )
      .to(".pre-iso", { scale: 1, duration: 0.9, ease: "power3.out" }, 2.2)
      // 7. La marca entra con máscara
      .to(".pre-wm .pre-m", { yPercent: 0, duration: 0.7, ease: "power4.out" }, 2.5)
      .to(".pre-wm .pre-v", { yPercent: 0, duration: 0.8, ease: "power4.out" }, 2.63)
      // 8. Salida: el logo respira hacia el sitio y la cortina sube
      .to(".pre-stage", { scale: 1.06, opacity: 0, duration: 0.6, ease: "power2.in" }, 3.55)
      .to(".pre-count", { opacity: 0, duration: 0.35 }, 3.55)
      .to(preloader, { yPercent: -100, duration: 0.95, ease: "power4.inOut" }, 3.85)
      .set(preloader, { display: "none" });
  } else {
    startSite();
  }

  /* ---------- Scroll-driven: reveals, parallax, words ---------- */
  function setupScroll() {
    // Títulos de sección (.line-in fuera del hero): máscara hacia arriba
    document.querySelectorAll(".line").forEach(function (line) {
      if (line.closest(".hero-title")) return;
      var inner = line.querySelector(".line-in");
      if (!inner) return;
      gsap.set(inner, { yPercent: 115 });
      ScrollTrigger.create({
        trigger: line,
        start: "top 88%",
        once: true,
        onEnter: function () {
          gsap.to(inner, { yPercent: 0, duration: 1, ease: "power4.out" });
        }
      });
    });

    // [data-reveal] simple
    document.querySelectorAll("[data-reveal]").forEach(function (el) {
      ScrollTrigger.create({
        trigger: el,
        start: "top 90%",
        once: true,
        onEnter: function () {
          gsap.to(el, { opacity: 1, y: 0, duration: 0.9, ease: "power3.out" });
        }
      });
    });

    // [data-words]: palabras que se iluminan con el scroll (scrub)
    document.querySelectorAll("[data-words]").forEach(function (el) {
      var words = splitWords(el);
      gsap.set(words, { opacity: 0.16 });
      gsap.to(words, {
        opacity: 1,
        ease: "none",
        stagger: 0.3,
        scrollTrigger: {
          trigger: el,
          start: "top 80%",
          end: "bottom 60%",
          scrub: true
        }
      });
    });

    // Parallax en medios full-bleed
    document.querySelectorAll("[data-parallax-media]").forEach(function (m) {
      var img = m.querySelector("img");
      if (!img) return;
      gsap.fromTo(
        img,
        { yPercent: -8 },
        {
          yPercent: 8,
          ease: "none",
          scrollTrigger: {
            trigger: m,
            start: "top bottom",
            end: "bottom top",
            scrub: true
          }
        }
      );
    });

    // Parallax extra del hero al hacer scroll de salida
    gsap.to(".hero-media img", {
      yPercent: 12,
      ease: "none",
      scrollTrigger: {
        trigger: ".hero",
        start: "top top",
        end: "bottom top",
        scrub: true
      }
    });

    ScrollTrigger.refresh();
  }

  /* ---------- Split de palabras (preserva nodos hijos) ---------- */
  function splitWords(el) {
    var words = [];
    Array.prototype.slice.call(el.childNodes).forEach(function (node) {
      if (node.nodeType === 3) {
        var frag = document.createDocumentFragment();
        node.textContent.split(/(\s+)/).forEach(function (tok) {
          if (!tok) return;
          if (!tok.trim()) {
            frag.appendChild(document.createTextNode(tok));
            return;
          }
          var w = document.createElement("span");
          w.className = "word";
          w.textContent = tok;
          frag.appendChild(w);
          words.push(w);
        });
        el.replaceChild(frag, node);
      } else {
        words.push(node);
      }
    });
    return words;
  }

  /* ---------- Cursor custom + imagen flotante ---------- */
  if (finePointer) {
    root.classList.add("fine");
    var cursor = document.getElementById("cursor");
    var floatImg = document.getElementById("floatImg");
    var floatImgEl = document.getElementById("floatImgEl");

    var cx = gsap.quickTo(cursor, "x", { duration: 0.25, ease: "power3" });
    var cy = gsap.quickTo(cursor, "y", { duration: 0.25, ease: "power3" });
    var fx = gsap.quickTo(floatImg, "x", { duration: 0.55, ease: "power3" });
    var fy = gsap.quickTo(floatImg, "y", { duration: 0.55, ease: "power3" });
    gsap.set([cursor, floatImg], { xPercent: -50, yPercent: -50 });

    var lastX = 0;
    window.addEventListener("pointermove", function (e) {
      cx(e.clientX);
      cy(e.clientY);

      // El preview se corre al costado del cursor para no tapar el nombre.
      // Si no cabe a la derecha, salta a la izquierda; y se clampea al viewport.
      var w = floatImg.offsetWidth || 190;
      var h = floatImg.offsetHeight || 250;
      var pad = 16;
      var offset = w / 2 + 34;
      var tx = e.clientX + offset;
      if (tx + w / 2 + pad > window.innerWidth) tx = e.clientX - offset;
      tx = gsap.utils.clamp(w / 2 + pad, window.innerWidth - w / 2 - pad, tx);
      var ty = gsap.utils.clamp(h / 2 + pad, window.innerHeight - h / 2 - pad, e.clientY);
      fx(tx);
      fy(ty);

      // Inclinación según velocidad horizontal (efecto wow)
      var vx = e.clientX - lastX;
      lastX = e.clientX;
      gsap.to(floatImg, {
        rotation: gsap.utils.clamp(-10, 10, vx * 0.5),
        duration: 0.6,
        ease: "power3"
      });
    });

    // Crecer en elementos interactivos
    document.querySelectorAll("[data-cursor], a, button").forEach(function (el) {
      if (el.hasAttribute("data-cursor-img")) return;
      el.addEventListener("pointerenter", function () {
        cursor.classList.add("is-active");
      });
      el.addEventListener("pointerleave", function () {
        cursor.classList.remove("is-active");
      });
    });

    // Preview flotante en filas de servicio.
    // Si la fila declara data-video, se reproduce un clip en loop (Higgsfield);
    // si no, muestra la foto. El <video> se crea una sola vez y es perezoso.
    var floatVid = document.createElement("video");
    floatVid.muted = true;
    floatVid.loop = true;
    floatVid.playsInline = true;
    floatVid.preload = "none";
    floatVid.style.display = "none";
    floatImgEl.parentNode.appendChild(floatVid);

    document.querySelectorAll(".svc-row").forEach(function (row) {
      var src = row.getAttribute("data-img");
      var vid = row.getAttribute("data-video");
      var link = row.querySelector("[data-cursor-img]");
      if (!src || !link) return;
      link.addEventListener("pointerenter", function () {
        if (vid) {
          floatVid.src = vid;
          floatVid.style.display = "block";
          floatImgEl.style.display = "none";
          var p = floatVid.play();
          if (p && p.catch) p.catch(function () {});
        } else {
          floatImgEl.src = src;
          floatImgEl.style.display = "block";
          floatVid.style.display = "none";
          floatVid.pause();
        }
        gsap.killTweensOf(floatImg);
        gsap.to(floatImg, {
          opacity: 1,
          scale: 1,
          duration: 0.5,
          ease: "power3.out"
        });
        cursor.classList.add("is-hidden");
        gsap.to(cursor, { scale: 0, duration: 0.3 });
      });
      link.addEventListener("pointerleave", function () {
        gsap.to(floatImg, {
          opacity: 0,
          scale: 0.6,
          duration: 0.4,
          ease: "power3.out",
          onComplete: function () {
            // Pausar al salir: si no, el clip sigue corriendo invisible.
            if (vid) floatVid.pause();
          }
        });
        gsap.to(cursor, { scale: 1, duration: 0.3 });
      });
    });
  }

  /* ---------- Magnético ---------- */
  if (finePointer) {
    document.querySelectorAll("[data-magnetic]").forEach(function (el) {
      var mx, my;
      el.addEventListener("pointermove", function (e) {
        if (!mx) {
          mx = gsap.quickTo(el, "x", { duration: 0.4, ease: "power3" });
          my = gsap.quickTo(el, "y", { duration: 0.4, ease: "power3" });
        }
        var r = el.getBoundingClientRect();
        mx((e.clientX - (r.left + r.width / 2)) * 0.4);
        my((e.clientY - (r.top + r.height / 2)) * 0.5);
      });
      el.addEventListener("pointerleave", function () {
        if (mx) {
          mx(0);
          my(0);
        }
      });
    });
  }

  /* ---------- UI común (anclas, header, menú, thumbs) ---------- */
  initUI(lenis);

  function initUI(lenisInstance) {
    var hdr = document.getElementById("hdr");
    var navToggle = document.getElementById("navToggle");
    var mobileMenu = document.getElementById("mobileMenu");

    /* Header: barra esmerilada al pasar el hero (legibilidad garantizada) */
    if (hdr) {
      var hero = document.getElementById("hero");
      var updateHdr = function () {
        var threshold = hero ? hero.offsetHeight * 0.6 : 400;
        var y = lenisInstance
          ? lenisInstance.scroll
          : window.pageYOffset || document.documentElement.scrollTop;
        hdr.classList.toggle("scrolled", y > threshold);
      };
      if (lenisInstance) lenisInstance.on("scroll", updateHdr);
      else window.addEventListener("scroll", updateHdr, { passive: true });
      updateHdr();
    }

    /* Menú mobile (overlay) accesible */
    if (navToggle && mobileMenu) {
      var setMenu = function (open) {
        navToggle.setAttribute("aria-expanded", String(open));
        navToggle.setAttribute("aria-label", open ? "Cerrar menú" : "Abrir menú");
        mobileMenu.classList.toggle("open", open);
        mobileMenu.setAttribute("aria-hidden", String(!open));
        if (lenisInstance) open ? lenisInstance.stop() : lenisInstance.start();
        document.body.style.overflow = open && !lenisInstance ? "hidden" : "";
      };
      navToggle.addEventListener("click", function () {
        setMenu(navToggle.getAttribute("aria-expanded") !== "true");
      });
      mobileMenu.addEventListener("click", function (e) {
        if (e.target.closest("a")) setMenu(false);
      });
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && mobileMenu.classList.contains("open")) {
          setMenu(false);
          navToggle.focus();
        }
      });
      window.__mvCloseMenu = function () { setMenu(false); };
    }

    /* Anclas con scroll suave + offset del header */
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
      a.addEventListener("click", function (e) {
        var id = a.getAttribute("href");
        if (!id || id.length < 2) return;
        var target = document.querySelector(id);
        if (!target) return;
        if (window.__mvCloseMenu) window.__mvCloseMenu();
        if (lenisInstance) {
          e.preventDefault();
          lenisInstance.scrollTo(target, { offset: -64, duration: 1.3 });
        }
      });
    });

    /* Thumbnails editoriales en servicios (viewport angosto): paridad de
       motion mobile. Reemplazan el hover-float del desktop con un reveal
       por scroll. Se condiciona por ancho para cubrir cualquier teléfono. */
    // Angosto (teléfonos) O táctil sin mouse (tablets): en ambos no hay
    // hover, así que el preview inline por scroll es el equivalente.
    var isNarrow =
      window.matchMedia &&
      (window.matchMedia("(max-width: 759px)").matches ||
        window.matchMedia("(pointer: coarse)").matches);
    if (lenisInstance && isNarrow && window.ScrollTrigger) {
      document.querySelectorAll(".svc-row").forEach(function (row) {
        var src = row.getAttribute("data-img");
        var vidSrc = row.getAttribute("data-video");
        var link = row.querySelector(".svc-link");
        if (!src || !link) return;
        var fig = document.createElement("figure");
        fig.className = "svc-thumb";
        var media;
        if (vidSrc) {
          // Paridad con desktop: el clip Higgsfield también en mobile.
          media = document.createElement("video");
          media.src = vidSrc;
          media.muted = true;
          media.loop = true;
          media.playsInline = true;
          media.setAttribute("playsinline", "");
          media.setAttribute("muted", "");
          media.preload = "none";
          media.poster = src;
        } else {
          media = document.createElement("img");
          media.src = src;
          media.alt = "";
          media.loading = "lazy";
        }
        fig.appendChild(media);
        link.appendChild(fig);
        ScrollTrigger.create({
          trigger: row,
          start: "top 78%",
          once: true,
          onEnter: function () {
            fig.classList.add("reveal");
            if (vidSrc) {
              var p = media.play();
              if (p && p.catch) p.catch(function () {});
            }
          }
        });
        // Ahorro de batería: reproducir solo cuando la fila está en pantalla
        // (entra por arriba O por abajo), pausar al salir por cualquier lado.
        if (vidSrc) {
          var safePlay = function () {
            if (!fig.classList.contains("reveal")) return;
            var p = media.play();
            if (p && p.catch) p.catch(function () {});
          };
          ScrollTrigger.create({
            trigger: row,
            start: "top bottom",
            end: "bottom top",
            onEnter: safePlay,
            onEnterBack: safePlay,
            onLeave: function () { media.pause(); },
            onLeaveBack: function () { media.pause(); }
          });
        }
      });
      ScrollTrigger.refresh();
    }
  }
})();
