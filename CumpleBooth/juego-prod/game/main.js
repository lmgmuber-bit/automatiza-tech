// Tu Cumple en 3D — el reino completo. Une motor (WebGPU + post-proceso),
// mundo, heroína, cámara (con momentos cinematográficos), nieve, efectos,
// misiones, entrada, interfaz, guardado, voz y audio.
// Estados: inicio → jugando ⇄ pausa → final → libre.

import * as THREE from "../vendor/three.webgpu.js";
import { T } from "./strings.js";
import { loadSave, writeSave, resetProgress, limpiarNombre, limpiarEdad, configurarGuardado } from "./save.js";
import { anotarResultado } from "./posiciones.js";
import { input, bindInput, updateInput, endInputFrame, clearInput } from "./input.js";
import { audio, configurarAudio } from "./audio.js";
import { voz } from "./voz.js";
import { UI } from "./ui.js";
import { crearMotor, CALIDADES } from "./motor.js";
import { Heroe } from "./heroe.js";
import { Camara } from "./camara.js";
import { crearNieve } from "./nieve.js";
import { FX } from "./fx.js";
import { Misiones } from "./misiones.js";
import { cargarGLB, normalizar, Personaje } from "./modelos.js";
import { resolverTema, aplicarTema } from "./temas/index.js";
import { iniciarAyudantes } from "./ayudantes.js";
import { crearFoto } from "./foto.js";
import { baseCumpleClick, cargarFiesta, crearSubidor, pedirFotosKiosco } from "./fiesta.js";
import { crearGaleria } from "./galeria.js";

const PASO = 1 / 60;

export async function boot() {
  const qTema = new URLSearchParams(location.search);
  // La temática decide textos, voces, música, modelos y mundo. Si viene de una
  // fiesta de CumpleClick, la temática es la del kiosco.
  const ccBaseTema = baseCumpleClick(qTema);
  const slugTema = (qTema.get("p") || "").trim().toLowerCase();
  const fiestaPrevia = slugTema && !qTema.get("tema") ? await cargarFiesta(ccBaseTema, slugTema) : null;
  const tema = resolverTema(qTema, fiestaPrevia);
  aplicarTema(tema);
  configurarGuardado(tema.claveGuardado);
  voz.configurar(tema.ruta + tema.voces);
  configurarAudio({ reino: tema.ruta + tema.musica.reino, fiesta: tema.ruta + tema.musica.fiesta, disponibles: tema.ruta + tema.disponibles });
  let save = loadSave();
  voz.precargar(save);
  const ui = new UI();
  voz.enganchar(ui, T);
  voz.usarMaster(() => audio.nodos());
  ui.fillStart(save);
  ui.destellos = save.destellos;
  const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  const q = new URLSearchParams(location.search);
  const debug = q.get("debug") === "1";
  // Fiesta de CumpleClick: ?p=<slug> trae nombre, invitados, marco y temática.
  const ccBase = baseCumpleClick(q);
  const fiestaSlug = (q.get("p") || "").trim().toLowerCase();
  const cargaFiesta = fiestaPrevia ? Promise.resolve(fiestaPrevia) : fiestaSlug ? cargarFiesta(ccBase, fiestaSlug) : Promise.resolve(null);
  ui.el.debug.hidden = !debug;
  const isTouch = "ontouchstart" in window || navigator.maxTouchPoints > 0;
  audio.precargar();
  ui.setLoading(true);

  // ── Motor y mundo ──────────────────────────────────────────────────────
  const canvas = document.getElementById("juego");
  const calidadPedida = CALIDADES[q.get("calidad")] ? q.get("calidad") : (isTouch ? "media" : "alta");
  const motor = await crearMotor(canvas, { calidad: calidadPedida });
  const { scene, camera } = motor;
  const { crearMundo } = await tema.mundo();
  const mundo = await crearMundo(motor, save, tema);
  const heroe = new Heroe(scene, mundo);
  heroe.ayuda = save.ayuda;
  const cam = new Camara(camera, mundo);
  const nieve = reduced ? null : crearNieve(scene, { cantidad: isTouch ? 700 : 1400 });
  const fx = new FX(scene, camera, { reducedMotion: reduced });
  window.addEventListener("resize", () => motor.resize());
  motor.resize();

  // La heroína: esqueleto + clips. Si el GLB falla, se juega con una cápsula.
  const archivoHeroina = /^[a-z0-9-]+$/.test(q.get("modelo") || "") ? `./models/${q.get("modelo")}.glb` : tema.ruta + tema.heroina.archivo;
  const cargaHeroe = cargarGLB(archivoHeroina).then((g) => {
    if (!g) {
      const cap = new THREE.Mesh(new THREE.CapsuleGeometry(0.35, 1.0, 6, 12), new THREE.MeshStandardMaterial({ color: 0x9fd4ff }));
      cap.position.y = 0.85; cap.castShadow = true;
      const grupo = new THREE.Group(); grupo.add(cap);
      heroe.setPersonaje({ root: grupo, play() { return true; }, update() {}, currentName: "idle" });
      return;
    }
    normalizar(g.scene, tema.heroina.alto || 1.72);
    // La textura del rig trae la luz horneada: un poco de emisivo con su propio
    // color la mantiene clara bajo la luz real en vez de gris y apagada.
    g.scene.traverse((o) => { if (o.isMesh && o.material && o.material.map) { const m = o.material; m.emissiveMap = m.map; m.emissive.set(0xffffff); m.emissiveIntensity = 0.32; m.roughness = 0.62; m.needsUpdate = true; } });
    heroe.setPersonaje(new Personaje(g.scene, g.animations));
  });
  // El muñeco de nieve guía, en la plaza.
  const cargaGuia = cargarGLB(tema.ruta + tema.guia.archivo).then((g) => { if (!g) return; const obj = normalizar(g.scene, tema.guia.alto || 1.5); mundo.guia.mesh.add(obj); });
  await Promise.all([mundo.listo, cargaHeroe, cargaGuia]);
  const fiesta = await cargaFiesta;
  if (fiesta) {
    fiesta.base = ccBase;
    if (!q.get("nombre") && fiesta.nombre) { save.nombre = fiesta.nombre; writeSave(save); voz.setNombre(save.nombre); ui.fillStart(save); }
    ui.setFiesta(fiesta);
    // Lanzado desde el kiosco de CumpleClick (?kiosco=1): el kiosco es la app principal
    // y el juego vuelve a él desde la pausa o al terminar la fiesta.
    if (q.get("kiosco") === "1" && fiesta.slug) {
      const volver = () => { location.href = fiesta.base + "?p=" + encodeURIComponent(fiesta.slug); };
      for (const id of ["btn-kiosco-pausa", "btn-kiosco-final"]) {
        const b = document.getElementById(id);
        if (b) { b.hidden = false; b.style.display = ""; b.addEventListener("click", volver); }
      }
    }
  }
  const misiones = new Misiones({ mundo, heroe, fx, audio, ui, save, cine: cam });
  heroe.onEvent = (ev) => misiones.handleEvent(ev);
  const cpInicial = mundo.checkpoints.find((c) => c.id === save.checkpoint);
  if (cpInicial && save.tutorial) heroe.respawn(cpInicial.pos);
  ui.setLoading(false);

  // ── Estado ─────────────────────────────────────────────────────────────
  let state = "inicio";
  let time = 0, acc = 0, finalT = 0, confettiT = 0;
  let pausedFrom = "jugando";
  let tut = { step: -1, wait: 0, saltos: 0, dobles: 0, distancia: 0, hielo: 0 };
  // Los invitados ayudan desde el celular (QR); sin backend se apaga solo.
  const ayudantes = iniciarAyudantes({ heroe, fx, ui, mundo, misiones, save, audio, estado: () => state, baseApi: fiesta ? ccBase + "sala.php" : null, debug });
  // Modo foto: botón en el HUD, foto automática en el medio de cada cinemática y en la fiesta.
  const foto = crearFoto({ canvas, ui, audio, save });
  // Las fotos cuelgan en el reino: las del kiosco (con el PIN de la galería) y las que saca el juego.
  const galeria = crearGaleria({ scene, mundo, textos: { placeholder: T.fiesta.placeholder } });
  foto.onFoto = (f) => galeria.agregar(f.url);
  if (fiesta) {
    foto.usarFiesta(fiesta);
    foto.subir = crearSubidor(ccBase, fiesta.slug);
    galeria.nombres(fiesta.invitados.map((i) => i.nombre));
  }
  async function cargarFotosKiosco(pin) {
    if (!fiesta || !/^[0-9]{4}$/.test(pin || "")) return;
    let cred = null;
    for (let i = 0; i < 24 && !(cred = ayudantes.credenciales()); i++) await new Promise((r) => setTimeout(r, 500));
    if (!cred) { ui.toast(T.fiesta.fotosError, 2600); return; }
    try {
      const r = await pedirFotosKiosco(ccBase, { ...cred, slug: fiesta.slug, pin });
      await galeria.mostrar(r.fotos.map((f) => f.url));
      ui.toast(r.fotos.length ? T.fiesta.fotosOk(r.fotos.length) : T.fiesta.fotosNinguna, 2800);
    } catch (e) {
      ui.toast(T.fiesta.errores[e.message] || T.fiesta.fotosError, 2800);
    }
  }
  const jugarOriginal = cam.jugar.bind(cam);
  cam.jugar = (tomas, total) => { jugarOriginal(tomas, total); foto.programar(total * 0.45, "wow"); };

  function applySettings(s) {
    const nombre = limpiarNombre(s.nombre);
    const edad = limpiarEdad(s.edad);
    if (nombre) save.nombre = nombre;
    if (edad) save.edad = edad;
    save.ayuda = !!s.ayuda; save.sonido = !!s.sonido; save.destellos = !!s.destellos;
    heroe.ayuda = save.ayuda;
    ui.destellos = save.destellos;
    audio.setEnabled(save.sonido); voz.setEnabled(save.sonido); voz.setNombre(save.nombre);
    writeSave(save);
    ui.fillStart(save);
  }

  function startGame() {
    applySettings(ui.readStart());
    audio.init(); audio.resume();
    audio.setEnabled(save.sonido); voz.setEnabled(save.sonido); voz.setNombre(save.nombre);
    ui.showScreen(null);
    ui.setTouchVisible(isTouch);
    clearInput();
    state = save.fiesta ? "libre" : "jugando";
    cam.orbita = false;
    if (save.fiesta) { mundo.salon.visible = true; cargarInvitados(); }
    audio.playMusic(save.fiesta ? "fiesta" : "reino");
    ui.toast(T.bienvenida(save.nombre), 3000);
    misiones.refreshUI();
    if (fiesta) cargarFotosKiosco(ui.readStart().pin);
    if (!save.tutorial && !save.fiesta) tutStart();
  }

  // ── Tutorial: 7 pasos, uno a la vez; cada uno se cumple jugando ────────
  function tutStart() { tut = { step: 0, wait: 0, saltos: 0, dobles: 0, distancia: 0, hielo: 0 }; ui.tutorialStep(0); }
  function tutNext() { tut.step++; tut.wait = 0.6; ui.tutorialStep(null); if (tut.step >= T.tutorial.length) { tut.step = -1; setTimeout(tutFinish, 500); } }
  function tutFinish() { tut.step = -1; ui.tutorialStep(null); if (!save.tutorial) { save.tutorial = true; writeSave(save); ui.toast(T.tutorialListo, 3200); } }
  function tutUpdate(dt) {
    if (tut.step < 0) return;
    if (tut.wait > 0) { tut.wait -= dt; if (tut.wait <= 0) ui.tutorialStep(tut.step); return; }
    switch (tut.step) {
      case 0: tut.distancia += heroe.velocidadPlano * dt; if (tut.distancia > 12) tutNext(); break;
      case 1: if (input.jumpPressed && heroe.onGround) tut.saltos++; if (tut.saltos >= 2) tutNext(); break;
      case 2: if (input.jumpPressed && !heroe.onGround) tutNext(); break;
      case 3: if (heroe.stats.congelaciones > 0) tutNext(); break;
      case 4: if ((mundo.blancos.find((b) => b.id === "tutorial")?.flash || 0) > 0) tutNext(); break;
      case 5: if (heroe.onIce || (mundo.sobrePlataforma && heroe.onGround && mundo.sobrePlataforma(heroe.pos.x, heroe.pos.z, heroe.pos.y))) tut.hielo += dt; if (tut.hielo > 0.8) tutNext(); break;
      case 6: if (heroe.stats.revelaciones > 0) tutNext(); break;
      default: break;
    }
  }

  function pause() {
    voz.callar();
    if (state !== "jugando" && state !== "libre") return;
    pausedFrom = state; state = "pausa"; clearInput();
    ui.el.ayudaPausa.checked = save.ayuda; ui.el.sonidoPausa.checked = save.sonido;
    ui.showScreen("pausa");
  }
  function resume() { if (state !== "pausa") return; state = pausedFrom; ui.showScreen(null); clearInput(); audio.resume(); }

  async function cargarInvitados() {
    if (mundo.invitados.length) return;
    await mundo.cargarInvitados(tema.invitados.map((i) => ({ nombre: i.nombre, url: tema.ruta + i.archivo, alto: i.alto })));
    // Quien tenga clip de baile en su GLB, baila con su propio esqueleto.
    for (const inv of mundo.invitados) {
      const cfg = tema.invitados.find((i) => i.nombre === inv.nombre);
      if (!cfg || !cfg.baila) continue;
      const g = await cargarGLB(tema.ruta + cfg.archivo);
      if (g && g.animations.length) {
        // Personaje envuelve el modelo en su propio root: se cuelga del puesto
        // de la hermana para que siga dentro del salon, con los pies en el suelo.
        const modelo = inv.obj.children[0] || inv.obj;
        const p = new Personaje(modelo, g.animations);
        inv.obj.add(p.root);
        inv.personaje = p; p.play("dance");
      }
    }
  }

  function startFinal() {
    state = "final"; finalT = 0; clearInput();
    // Va aca y no en el bucle del estado "final": esta funcion corre una sola vez por
    // partida, el bucle sesenta veces por segundo.
    anotarResultado(save, mundo.copoTotal);
    ui.tutorialStep(null); ui.dialog(null);
    mundo.salon.visible = true;
    cargarInvitados();
    cam.orbita = true; cam.dist = 7.5; cam.pitch = 0.3;
    foto.programar(2.6, "fiesta");
    audio.playMusic("fiesta");
    audio.sfx("fanfarria");
    const c = mundo.castilloCentro;
    fx.comic(T.fiesta, new THREE.Vector3(c.x, c.y + 2, c.z), { estilo: "fiesta", dur: 2 });
    ui.flash();
  }
  function exploreFree() {
    state = "libre"; cam.orbita = false; cam.dist = 5.4; cam.yaw = Math.PI;
    ui.showScreen(null); clearInput(); audio.playMusic("reino");
    misiones.refreshUI();
  }
  function restart() {
    save = resetProgress(save);
    location.reload();
  }
  misiones.onFinal = startFinal;

  // ── Entrada y botones ──────────────────────────────────────────────────
  bindInput({ surface: canvas, buttons: { jump: document.getElementById("b-jump"), swing: document.getElementById("b-congelar"), zip: document.getElementById("b-magia"), sense: document.getElementById("b-revelar") } });
  let controlesDesde = "inicio";
  const click = (id, fn) => { const el = document.getElementById(id); if (el) el.addEventListener("click", () => { audio.init(); audio.sfx("click"); fn(); }); };
  click("btn-jugar", startGame);
  click("btn-controles", () => { controlesDesde = "inicio"; ui.showScreen("controles"); });
  click("btn-continuar", resume);
  click("btn-controles-pausa", () => { controlesDesde = "pausa"; ui.showScreen("controles"); });
  click("btn-reiniciar", restart);
  click("btn-cerrar-controles", () => ui.showScreen(controlesDesde));
  click("btn-pausa", pause);
  click("btn-saltar-tutorial", tutFinish);
  click("btn-repetir", restart);
  click("btn-explorar", exploreFree);
  ui.el.ayudaPausa?.addEventListener("change", (e) => { save.ayuda = e.target.checked; heroe.ayuda = save.ayuda; ui.el.ayuda.checked = save.ayuda; writeSave(save); });
  ui.el.sonidoPausa?.addEventListener("change", (e) => { save.sonido = e.target.checked; audio.setEnabled(save.sonido); voz.setEnabled(save.sonido); ui.el.sonido.checked = save.sonido; writeSave(save); });
  window.addEventListener("pointerdown", () => { audio.init(); audio.resume(); }, { once: true });
  ui.showScreen("inicio");

  // ── Bucle ──────────────────────────────────────────────────────────────
  const clock = new THREE.Clock();
  let fpsAcc = 0, fpsN = 0, fpsMedia = 60, fpsVentana = 0, ultimoAjuste = 0;
  const camPos = new THREE.Vector3();
  let forzado = null; // entrada simulada para pruebas automáticas (?debug=1)

  function step(dt) {
    heroe.update(dt, input, cam.yaw);
    misiones.update(dt, time);
    tutUpdate(dt);
  }

  motor.renderer.setAnimationLoop(() => {
    const dt = Math.min(0.05, clock.getDelta());
    time += dt;
    updateInput();
    if (forzado) { input.move.x = forzado.x; input.move.y = forzado.y; }
    if (input.pausePressed) { if (state === "pausa") resume(); else if (state === "jugando" || state === "libre") pause(); }
    if ((state === "jugando" || state === "libre") && !cam.enCine) {
      cam.girar(input.look.x, input.look.y);
      acc += dt;
      let n = 0;
      while (acc >= PASO && n < 4) {
        // Las pulsaciones simuladas se entregan justo antes del paso de física
        // que las va a leer (endInputFrame las borra después de cada paso).
        if (n === 0 && forzado) {
          if (forzado.salto) { input.jumpPressed = true; input.jump = true; forzado.salto = false; forzado.sostener = 12; }
          else if (forzado.sostener > 0) { input.jump = true; forzado.sostener--; }
          if (forzado.poder) { input[forzado.poder] = true; input[forzado.poder + "Pressed"] = true; forzado.poder = null; }
        }
        step(PASO); acc -= PASO; n++; endInputFrame();
      }
      if (n === 4) acc = 0;
    } else if (state === "final") {
      finalT += dt;
      if (heroe.personaje) { heroe.personaje.play("idle"); heroe.personaje.update(dt); }
      confettiT += dt;
      if (confettiT > (reduced ? 0.5 : 0.12)) { confettiT = 0; const c = mundo.castilloCentro; fx.burst(new THREE.Vector3(c.x + (Math.random() - 0.5) * 12, c.y + 6, c.z + (Math.random() - 0.5) * 8), { count: 6, colors: [0xe63946, 0x2b6fdb, 0xffd60a, 0xffffff, 0xb48cff], speed: 2, gravity: 2.5, life: 2.6, spread: 1 }); }
      if (finalT > 3.5 && ui.el.final.hidden && ui.el.pausa.hidden) ui.showFinal(save, mundo.copoTotal);
    } else if (state === "inicio") {
      cam.yaw += dt * 0.12;
      if (heroe.personaje) heroe.personaje.update(dt);
    } else if (heroe.personaje) {
      heroe.personaje.update(dt);
    }
    for (const inv of mundo.invitados) if (inv.personaje) inv.personaje.update(dt);
    cam.update(dt, heroe);
    camPos.copy(camera.position);
    mundo.update(dt, time, heroe.pos, camPos);
    if (nieve) nieve.update(dt, camPos);
    fx.update(dt);
    galeria.update(time);
    motor.render();
    foto.procesar(dt);

    fpsAcc += dt; fpsN++; fpsVentana += dt;
    if (fpsVentana >= 3) {
      fpsMedia = fpsN / fpsAcc;
      if ((state === "jugando" || state === "libre") && fpsMedia < 45 && time - ultimoAjuste > 6 && !q.get("calidad")) {
        const nuevo = motor.bajarCalidad();
        if (nuevo) { ultimoAjuste = time; if (debug) console.info("calidad →", nuevo, "por", fpsMedia.toFixed(0), "fps"); }
      }
      if (debug) {
        const i = motor.info();
        ui.setDebug(`${fpsMedia.toFixed(0)} fps · ${(fpsAcc / fpsN * 1000).toFixed(1)} ms · ${i.calls} draw calls · ${i.tris} tris · ${motor.backend} · calidad ${motor.calidad} · ${state}`);
      }
      fpsAcc = 0; fpsN = 0; fpsVentana = 0;
    }
  });

  window.__juego = { state: () => state, heroe, cam, mundo, motor, save, misiones, startGame, tutFinish, startFinal, ayudantes, foto, galeria, fiesta, cargarFotosKiosco, tema, get fps() { return fpsMedia; },
    /** Solo pruebas: mueve a la heroína (x, y en −1..1) durante `ms`, con salto o poder ("swing"/"zip"/"sense") opcional. */
    forzar(x, y, ms, salto = false, poder = null) { if (!debug) return; forzado = { x, y, salto, poder }; setTimeout(() => { forzado = null; }, ms); },
    get forzado() { return forzado; }, get acc() { return acc; } };
}
