import { T } from './textos.mjs';
import { crearMotor } from './motor.mjs';
import { crearMundo } from './mundo.mjs';
import { Entrada } from './entrada.mjs';
import { Movimiento, Camara } from './personaje.mjs';
import { Sonido } from './sonido.mjs';
import { cargar, guardar, nombreSeguro } from './persistencia.mjs';
import { anotar } from './posiciones.mjs';
import { patron, acierto, valor } from './reglas.mjs';
const $ = id => document.getElementById(id), params = new URLSearchParams(location.search), saved = cargar();
document.title = T.titulo.replace('\n', ' ');
document.querySelectorAll('[data-t]').forEach(el => el.textContent = T[el.dataset.t]);
for (const id of ['saltar', 'magia', 'revelar']) {
    $(id).querySelector('small').textContent = T[id];
    $(id).setAttribute('aria-label', T[id]);
}
$('pausa').setAttribute('aria-label', T.pausa);
$('palanca').setAttribute('aria-label', T.palanca);
$('mundo').setAttribute('aria-label', T.lienzo);
$('nombre').value = nombreSeguro(params.get('nombre') || saved?.name);
$('edad').value = Math.min(12, Math.max(4, Number(params.get('edad') || saved?.age) || 5));
$('nombres').value = saved?.players.map(p => p.name).join(', ') || '';
$('suave').checked = matchMedia('(prefers-reduced-motion: reduce)').matches || saved?.reduced || false;
$('sonido').checked = saved?.sound !== false;
$('jugar').textContent = T.cargando;
$('carga-texto').textContent = T.cargando;
const audio = new Sonido(), input = new Entrada(), state = { phase: 'inicio', tutorial: 0, name: $('nombre').value, age: Number($('edad').value), players: [], turn: 0, remaining: 60, rise: 0, sound: true, reduced: $('suave').checked, score: 0 };
let motor, world, motion, cam, stepDelay = 0, cineTime = 0, pausedPhase = 'corte', hintTime = 0, toastTimer, last = performance.now(), saveTime = 0, readyAt = 0, walkStart = 0, magicCooldown = 0, round = 0, combo = 0, sinceScore = 0, roundWait = 0;
function toast(text) { $('toast').textContent = text; $('toast').classList.add('show'); clearTimeout(toastTimer); toastTimer = setTimeout(() => $('toast').classList.remove('show'), 1800); }
function objective(zone, text, icon = '✦') { $('zona').textContent = zone; $('instruccion').textContent = text; $('objetivo-icono').textContent = icon; }
function updateHud() { $('jugador').textContent = state.phase === 'tutorial' ? T.edadFiesta(state.name, state.age) : state.players[state.turn]?.name || state.name; $('turno').textContent = state.phase === 'turno' ? T.jugador(state.turn + 1, state.players.length) : state.phase === 'tutorial' ? T.tutorial : T.celeb(state.name); $('puntos').textContent = state.score; $('reloj').textContent = state.phase === 'turno' ? T.tiempo(state.remaining) : ''; }
function showPlay() { for (const id of ['inicio', 'panel'])
    $(id).hidden = true; for (const id of ['hud', 'objetivo', 'controles'])
    $(id).hidden = false; }
function panel(title, text, button, eyebrow = '') { input.clear(); $('panel').hidden = false; $('panel-titulo').textContent = title; $('panel-texto').textContent = text; $('seguir').textContent = button; $('panel-ceja').textContent = eyebrow; $('controles').hidden = true; }
function tutorialStep() { objective(T.tutorial, T.pasos[state.tutorial], T.pasoIcono[state.tutorial]); $('pasos').replaceChildren(...Array.from({ length: 4 }, (_, i) => { const el = document.createElement('i'); el.className = i <= state.tutorial ? 'done' : ''; return el; })); for (const id of ['saltar', 'magia', 'revelar'])
    $(id).classList.remove('pulse'); const id = ['palanca', 'saltar', 'magia', 'revelar'][state.tutorial]; if (id !== 'palanca')
    $(id).classList.add('pulse'); if (state.tutorial === 2)
    world.point(0, -5.5);
else if (state.tutorial === 3)
    world.point(5, 0);
else
    world.point(0, 5); audio.say(T.pasos[state.tutorial], T.pasoAudio[state.tutorial], true); hintTime = 0; }
function passStep() { if (stepDelay > 0)
    return; audio.chime(state.tutorial * 2); toast(T.refuerzo[state.tutorial]); stepDelay = 1.8; }
function start() { world.reset(); audio.named = 0; state.name = nombreSeguro($('nombre').value); state.age = Math.min(12, Math.max(4, Number($('edad').value) || 5)); state.sound = $('sonido').checked; state.reduced = $('suave').checked || matchMedia('(prefers-reduced-motion: reduce)').matches; const names = $('nombres').value.split(',').map(s => nombreSeguro(s, '')).filter(Boolean); if (names[0] !== state.name)
    names.unshift(state.name); while (names.length < 8)
    names.push(T.nombres(names.length + 1)); state.players = names.slice(0, 12).map(name => ({ name, score: 0 })); state.turn = 0; state.score = 0; state.remaining = 60; state.rise = 0; state.tutorial = 0; state.phase = 'tutorial'; motion.reset(); walkStart = motion.walked; showPlay(); audio.start(state.sound); audio.name(state.name); stepDelay = 0; setTimeout(() => { if (state.phase === 'tutorial')
    tutorialStep(); }, 2300); tutorialStepVisual(); guardar(state); }
function tutorialStepVisual() { objective(T.tutorial, T.pasos[0], T.pasoIcono[0]); world.point(0, 5); }
function startSlice() { state.phase = 'corte'; world.hidePoint(); $('pasos').replaceChildren(); for (const id of ['saltar', 'magia', 'revelar'])
    $(id).classList.remove('pulse'); world.setTargets([{ x: 0, z: 4 }, { x: -3, z: -1 }, { x: 3, z: 0 }, { x: 3, z: -5 }, { x: -4, z: -5 }]); objective(T.corteZona, T.corte); }
function wow() { state.phase = 'wow'; cineTime = 0; world.hidePoint(); $('controles').hidden = true; $('objetivo').hidden = true; $('cine').hidden = false; $('cine-titulo').textContent = T.wow; audio.name(state.name); audio.chime(12); }
function finishSlice() { state.rise = 1; $('cine').hidden = true; if (params.get('corte') === '1')
    ceremony();
else
    handoff(); }
function handoff(resume = false) { state.phase = 'entrega'; world.setTargets([]); world.hidePoint(); $('cine').hidden = true; const player = state.players[state.turn]; panel(T.listoNombre(player.name), T.listoTexto, T.empezar, T.listoTurno); objective(T.zona[state.turn % 3], T.meta[state.turn % 3]); $('objetivo').hidden = false; $('resultados').replaceChildren(); audio.say(T.compartir(player.name) + ' ' + T.ayudaVoz[state.turn % 3], T.instruccionAudio[state.turn % 3], true); if (!resume)
    state.remaining = 60; guardar(state); }
function beginTurn(resume = false) { state.phase = 'turno'; state.remaining = resume ? state.remaining : 60; round = 0; combo = 0; sinceScore = 0; roundWait = 0; magicCooldown = 0; hintTime = 0; input.clear(); const mode = state.turn % 3; world.gardenMode(mode === 1); world.setTargets(patron(mode, round)); motion.reset(...[[-11, 5.3], [7.2, 4], [0, -1]][mode]); showPlay(); objective(T.zona[mode], T.meta[mode], ['↑', '◎', '✧'][mode]); audio.say(T.ayudaVoz[mode], T.instruccionAudio[mode], true); guardar(state); }
function playTurn(dt, realDt, action) {
    state.remaining = Math.max(0, state.remaining - realDt);
    sinceScore += dt;
    const mode = state.turn % 3;
    if (roundWait > 0) {
        roundWait -= dt;
        if (roundWait <= 0) {
            round++;
            world.setTargets(patron(mode, round));
            toast(T.vuelta);
        }
    }
    else {
        const candidates = world.items().filter(item => !item.collected && acierto(mode, item, motion.p, action)).sort((a, b) => Math.hypot(a.x - motion.p.x, a.z - motion.p.z) - Math.hypot(b.x - motion.p.x, b.z - motion.p.z));
        const hit = candidates[0];
        if (hit && (mode === 0 || magicCooldown === 0)) {
            hit.collected = true;
            magicCooldown = .55;
            combo = sinceScore < 5 ? combo + 1 : 1;
            sinceScore = 0;
            const score = valor(mode, combo);
            state.players[state.turn].score += score;
            state.score += score;
            audio.chime(combo % 12);
            toast(mode === 0 ? (combo > 1 ? T.combo(combo) : T.salto) : mode === 1 ? T.secreto : T.cristal);
            if (mode === 1) {
                world.revealFriend(hit.id, hit.x, hit.z);
                audio.say([T.secreto, T.secreto, T.secreto][hit.id], ['rescate-nieva', 'rescate-copito', 'rescate-estrella'][hit.id]);
            }
            if (mode === 2)
                world.flash(hit.x, hit.z);
            if (world.items().every(i => i.collected))
                roundWait = 1.1;
        }
    }
    const next = world.items().filter(t => !t.collected).sort((a, b) => Math.hypot(a.x - motion.p.x, a.z - motion.p.z) - Math.hypot(b.x - motion.p.x, b.z - motion.p.z))[0];
    if (next)
        world.point(next.x, next.z);
    else
        world.hidePoint();
    if (hintTime > 17) {
        audio.say(T.ayudaVoz[mode], T.instruccionAudio[mode]);
        hintTime = 0;
    }
    if (state.remaining <= 0) {
        world.gardenMode(false);
        audio.chime(12);
        if (state.turn + 1 === state.players.length)
            ceremony();
        else {
            const previous = state.players[state.turn];
            // Se anota apenas termina su turno y no al final de la fiesta: si la tablet se
            // apaga o el cumpleanos se corta antes de la ceremonia, lo que ya se jugo no se
            // pierde de la tabla.
            anotar(previous.name, previous.score);
            state.turn++;
            handoff();
            toast(T.resumen(previous.name, previous.score));
        }
    }
}
function ceremony() {
    // El ultimo jugador nunca pasa por el cambio de turno, asi que se anota aca. Los demas
    // ya estan anotados; volver a mandarlos solo inflaria su contador de partidas.
    const ultimo = state.players[state.turn];
    if (ultimo) { anotar(ultimo.name, ultimo.score); }
    state.phase = 'final'; state.rise = 1; world.setTargets([]); world.hidePoint(); world.gardenMode(false); $('cine').hidden = true; $('objetivo').hidden = true; $('controles').hidden = true; motion.reset(0, -3.8); world.hero.root.rotation.y = 0; world.guide.position.set(-5, 0, -7.8); world.partyNames(T.bienvenida(state.name), state.players.map(p => p.name)); audio.party(); audio.name(state.name); $('resultados').replaceChildren(...state.players.map(p => { const row = document.createElement('div'), name = document.createElement('span'), score = document.createElement('b'); name.textContent = p.name; score.textContent = `★ ${p.score}`; row.append(name, score); return row; })); panel(T.bienvenida(state.name), T.finalTexto, T.verFiesta, T.fiestaCeja); guardar(state); }
function fail(e) { state.phase = 'error'; $('inicio').hidden = false; $('carga-texto').textContent = T.error; $('jugar').disabled = true; console.error(e); }
$('config').addEventListener('submit', e => { e.preventDefault(); if (world)
    start(); });
$('pausa').addEventListener('click', () => { if (state.phase === 'final') {
    $('panel').hidden = true;
    $('cine').hidden = true;
    $('inicio').hidden = false;
    for (const id of ['hud', 'objetivo', 'controles'])
        $(id).hidden = true;
    state.phase = 'inicio';
    $('continuar').hidden = false;
    return;
} if (['inicio', 'pausa'].includes(state.phase))
    return; pausedPhase = state.phase; state.phase = 'pausa'; audio.pause(); panel(T.pausaTitulo, T.pausaTexto, T.volver); guardar({ ...state, phase: pausedPhase }); });
$('seguir').addEventListener('click', () => { if (state.phase === 'pausa') {
    state.phase = pausedPhase;
    $('panel').hidden = true;
    $('controles').hidden = !['tutorial', 'corte', 'turno'].includes(state.phase);
    audio.resume();
    if (state.phase === 'entrega')
        handoff(true);
}
else if (state.phase === 'entrega')
    beginTurn(true);
else if (state.phase === 'final') {
    $('panel').hidden = true;
    $('cine').hidden = false;
    $('cine-titulo').textContent = T.bienvenida(state.name);
} });
$('continuar').addEventListener('click', () => { const d = cargar(); if (!d?.players.length)
    return; Object.assign(state, d); state.sound = $('sonido').checked; state.reduced = $('suave').checked || matchMedia('(prefers-reduced-motion: reduce)').matches; state.score = state.players.reduce((s, p) => s + p.score, 0); audio.start(state.sound); audio.name(state.name); showPlay(); if (['inicio', 'tutorial', 'corte'].includes(d.phase)) {
    state.tutorial = 0;
    state.phase = 'tutorial';
    motion.reset();
    walkStart = motion.walked;
    tutorialStep();
}
else if (d.phase === 'final')
    ceremony();
else {
    state.rise = 1;
    handoff(true);
} toast(T.continuarInfo); });
$('escuchar').addEventListener('click', () => audio.repeat());
addEventListener('keydown', e => { if (e.key === 'Escape' && state.phase !== 'inicio')
    state.phase === 'pausa' ? $('seguir').click() : $('pausa').click(); });
document.addEventListener('visibilitychange', () => { if (document.hidden && !['inicio', 'pausa', 'final', 'error'].includes(state.phase))
    $('pausa').click(); });
addEventListener('pagehide', () => { if (state.players.length)
    guardar({ ...state, phase: state.phase === 'pausa' ? pausedPhase : state.phase }); });
try {
    motor = await crearMotor($('mundo'));
    world = await crearMundo(motor.scene, p => { $('carga').style.width = `${p * 90}%`; });
    $('carga-texto').textContent = T.preparando;
    await world.celebration();
    motion = new Movimiento(world.hero);
    motion.reset(6, 4);
    cam = new Camara(motor.camera);
    cam.update(1, motion.p, state);
    // WebGL prepara sus programas en el primer render; evitar duplicar el trabajo.
    if (motor.metrics.backend === 'WebGPU')
        await motor.renderer.compileAsync(motor.scene, motor.camera);
    motor.render(1 / 60);
    await new Promise(requestAnimationFrame);
    readyAt = performance.now();
    last = readyAt;
    motor.resetMetrics();
    $('carga').style.width = '100%';
    $('jugar').textContent = T.jugar;
    $('jugar').disabled = false;
    $('carga-texto').textContent = T.listo;
    $('continuar').hidden = !saved?.players.length;
    $('debug').hidden = !params.has('debug');
    // El hook de pruebas queda detras de ?debug=1. Expone forzar(), que inyecta controles:
    // en una fiesta, con la tablet en manos de los ninos, eso es un mando invisible al alcance
    // de cualquiera que abra la consola. Las pruebas ya usan ?debug=1, asi que no cambian.
    if (params.has('debug')) {
        window.__juego = { state: () => ({ ...state, players: state.players.map(p => ({ ...p })), targets: world.items().map(({ x, y, z, collected, ring }) => ({ x, y: y ?? 1, z, collected, ring: !!ring })), position: { ...motion.p }, tutorial: state.tutorial, readyMs: readyAt, metrics: { ...motor.metrics, samples: undefined }, audio: { local: !!audio.localVoice, named: audio.named, failures: [...audio.failures] } }), forzar: (...args) => input.forzar(...args), heroe: world.hero.root, metrics: () => motor.metrics.samples.map(s => ({ ...s })), ready: true };
    }
    requestAnimationFrame(frame);
}
catch (e) {
    fail(e);
}
function frame(now) {
    const raw = (now - last) / 1000, dt = Math.min(.05, Math.max(.001, raw));
    last = now;
    const active = ['tutorial', 'corte', 'turno'].includes(state.phase);
    if (active) {
        const action = input.read();
        motion.update(dt, action, state.rise);
        magicCooldown = Math.max(0, magicCooldown - dt);
        hintTime += dt;
        if (action.magic || action.reveal) {
            world.flash(motion.p.x, motion.p.z);
            audio.chime(action.magic ? 5 : 9);
        }
        if (state.phase === 'tutorial') {
            if (stepDelay > 0) {
                stepDelay -= dt;
                if (stepDelay <= 0) {
                    state.tutorial++;
                    if (state.tutorial >= 4)
                        startSlice();
                    else
                        tutorialStep();
                }
            }
            else if (state.tutorial === 0 && motion.walked - walkStart > 2.5 || state.tutorial === 1 && action.jump || state.tutorial === 2 && action.magic && Math.hypot(motion.p.x, motion.p.z + 5.5) < 4 || state.tutorial === 3 && action.reveal)
                passStep();
            if (hintTime > 14 && stepDelay <= 0)
                tutorialStep();
        }
        else if (state.phase === 'corte') {
            for (const item of world.items()) {
                if (!item.collected && Math.hypot(item.x - motion.p.x, item.z - motion.p.z) < 1.2) {
                    item.collected = true;
                    state.score++;
                    state.players[0].score++;
                    audio.chime(state.score);
                    toast(T.estrella);
                }
            }
            const next = world.items().find(t => !t.collected);
            if (next)
                world.point(next.x, next.z);
            else
                wow();
        }
        else if (state.phase === 'turno')
            playTurn(dt, raw, action);
    }
    else if (state.phase !== 'pausa')
        world.hero.update(dt);
    if (state.phase === 'wow') {
        cineTime += dt;
        state.rise = Math.min(1, cineTime / 7);
        state.rise = state.rise * state.rise * (3 - 2 * state.rise);
        if (cineTime > 11)
            finishSlice();
    }
    if (state.phase !== 'pausa') {
        world.update(dt, { reduced: state.reduced, rise: state.rise, fiesta: state.phase === 'final' });
        cam.update(dt, motion.p, { ...state, cineTime });
    }
    updateHud();
    motor.render(raw);
    if (params.has('debug'))
        $('debug').textContent = T.debug(motor.metrics);
    saveTime += dt;
    if (saveTime > 5 && state.players.length) {
        saveTime = 0;
        guardar({ ...state, phase: state.phase === 'pausa' ? pausedPhase : state.phase });
    }
    requestAnimationFrame(frame);
}
