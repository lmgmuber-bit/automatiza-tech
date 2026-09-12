// Infraestructura reutilizada con autorización: tucumple/game/modelos.js.
// Carga de GLB (Draco opcional) y normalización de materiales. Los modelos
// vienen de tres fuentes distintas (SAM 3D, Meshy rig, conceptos) y cada una
// trae sus manías: sin normales, sin metallicFactor (glTF lo asume 1 → negro),
// o con la textura duplicada en emissive (se ve quemada con luz real). Todo se
// arregla aquí, una sola vez, para que el resto del juego reciba mallas PBR
// consistentes.
import * as THREE from "../vendor/three.webgpu.js";
import { GLTFLoader } from "../vendor/GLTFLoader.js";
import { DRACOLoader } from "../vendor/DRACOLoader.js";
const draco = new DRACOLoader();
draco.setDecoderPath("./vendor/");
const loader = new GLTFLoader();
loader.setDRACOLoader(draco);
const cache = new Map();
/** Carga un GLB (una vez; se cachea la promesa) → { scene, animations } o null. */
export function cargarGLB(url) {
    if (!cache.has(url)) {
        cache.set(url, loader.loadAsync(url).catch((e) => { console.warn("GLB no cargó", url, e?.message); return null; }));
    }
    return cache.get(url);
}
/**
 * Escala a `alto` metros, apoya los pies en y=0 y centra en x/z. Arregla los
 * materiales para el render PBR con luz real. Devuelve el mismo objeto.
 */
export function normalizar(obj, alto, { sombras = true } = {}) {
    const box = new THREE.Box3().setFromObject(obj);
    const size = new THREE.Vector3();
    box.getSize(size);
    const s = size.y > 0 ? alto / size.y : 1;
    obj.scale.setScalar(s);
    box.setFromObject(obj);
    const c = new THREE.Vector3();
    box.getCenter(c);
    obj.position.x -= c.x;
    obj.position.z -= c.z;
    obj.position.y -= box.min.y;
    obj.traverse((o) => {
        if (!o.isMesh)
            return;
        o.castShadow = sombras;
        o.receiveShadow = sombras;
        o.frustumCulled = false; // los rigs animados salen de su caja; mejor no recortarlos
        const m = o.material;
        if (!m)
            return;
        if (o.geometry && !o.geometry.attributes.normal)
            o.geometry.computeVertexNormals();
        if (!m.metalnessMap && m.metalness === 1) {
            m.metalness = 0;
            m.roughness = Math.min(1, Math.max(0.55, m.roughness));
        }
        if (m.emissiveMap) {
            m.emissiveMap = null;
            m.emissive.set(0x000000);
        }
        if (m.map) {
            m.map.colorSpace = THREE.SRGBColorSpace;
            m.map.anisotropy = 4;
        }
        m.side = THREE.FrontSide;
        m.transparent = false;
        m.depthWrite = true;
        m.needsUpdate = true;
    });
    return obj;
}
/** Colisionador AABB de un objeto ya normalizado y colocado. */
export function cajaDe(obj, { encoger = 0 } = {}) {
    obj.updateMatrixWorld(true);
    const box = new THREE.Box3().setFromObject(obj);
    box.min.x += encoger;
    box.max.x -= encoger;
    box.min.z += encoger;
    box.max.z -= encoger;
    return box;
}
/**
 * Personaje con esqueleto: mezcla clips por nombre con fundido. Los clips
 * llegan como "Armature|RunFast|baselayer" o ya renombrados; se registran por
 * alias corto (idle / run / jump) buscando la palabra en el nombre.
 */
export class Personaje {
    constructor(scene, clips) {
        this.root = new THREE.Group();
        this.model = scene;
        this.root.add(scene);
        this.mixer = new THREE.AnimationMixer(scene);
        this.actions = new Map();
        const ALIAS = [["idle", "idle"], ["runfast", "run"], ["run", "run"], ["jump", "jump"], ["walk", "walk"], ["wave", "wave"], ["dance", "dance"]];
        for (const clip of clips) {
            const key = clip.name.toLowerCase();
            const action = this.mixer.clipAction(clip);
            for (const [needle, alias] of ALIAS) {
                if (key.includes(needle) && !this.actions.has(alias))
                    this.actions.set(alias, action);
            }
        }
        this.current = null;
        this.currentName = "";
    }
    has(name) { return this.actions.has(name); }
    play(name, { fade = 0.16, loop = true, speed = 1 } = {}) {
        const next = this.actions.get(name);
        if (!next)
            return false;
        if (this.current === next) {
            next.timeScale = speed;
            return true;
        }
        next.reset();
        next.setLoop(loop ? THREE.LoopRepeat : THREE.LoopOnce, Infinity);
        next.clampWhenFinished = !loop;
        next.timeScale = speed;
        next.fadeIn(fade).play();
        if (this.current)
            this.current.fadeOut(fade);
        this.current = next;
        this.currentName = name;
        return true;
    }
    update(dt) { this.mixer.update(dt); }
}
