import * as THREE from '../vendor/three.webgpu.js';
import { cargarGLB, normalizar, Personaje } from './modelos.mjs';

import { PERSONAJES } from './avatares.mjs';

class Integrante extends Personaje {
  constructor(model, clips) {
    super(model, clips);
    this.reposo = new Map();
    model.traverse(b => {
      if (b.isBone) this.reposo.set(b, { q: b.quaternion.clone(), p: b.position.clone(), s: b.scale.clone() });
    });
    this.poses = {};
    // Los dos amigos sólo tienen clips de baile; sus poses de vuelo usan el rig existente.
    if (!this.has('jump')) {
      this.poses.idle = this.pose([['LeftArm', 'LeftForeArm', .3, -.95, .08], ['RightArm', 'RightForeArm', -.3, -.95, .08]]);
      this.poses.jump = this.pose([
        ['LeftArm', 'LeftForeArm', .6, .45, .65],
        ['RightArm', 'RightForeArm', -.18, .97, .12],
        ['LeftUpLeg', 'LeftLeg', .12, -.92, -.28],
        ['RightUpLeg', 'RightLeg', -.12, -.82, -.5],
      ]);
      this.restaurar();
    }
    this.procedural = null;
  }
  restaurar() {
    for (const [b, r] of this.reposo) { b.quaternion.copy(r.q); b.position.copy(r.p); b.scale.copy(r.s); }
    this.root.updateMatrixWorld(true);
  }
  pose(brazos) {
    this.restaurar();
    for (const [nombre, hijo, x, y, z] of brazos) {
      const b = this.model.getObjectByName(nombre), c = this.model.getObjectByName(hijo);
      if (!b || !c) continue;
      const origen = b.getWorldPosition(new THREE.Vector3());
      const direccion = c.getWorldPosition(new THREE.Vector3()).sub(origen).normalize();
      const giro = new THREE.Quaternion().setFromUnitVectors(direccion, new THREE.Vector3(x, y, z).normalize());
      const mundial = b.getWorldQuaternion(new THREE.Quaternion()).premultiply(giro);
      b.quaternion.copy(b.parent.getWorldQuaternion(new THREE.Quaternion()).invert().multiply(mundial));
      this.root.updateMatrixWorld(true);
    }
    return new Map([...this.reposo.keys()].map(b => [b, b.quaternion.clone()]));
  }
  play(name, opciones = {}) {
    const disponible = this.has(name) ? name : name === 'wave' && this.has('dance') ? 'dance' : null;
    if (disponible) { this.procedural = null; return super.play(disponible, opciones); }
    if (this.procedural !== name) {
      this.mixer.stopAllAction(); this.current = null; this.procedural = name;
      this.restaurar();
    }
    return true;
  }
  update(dt) {
    if (!this.procedural) return super.update(dt);
    const pose = this.poses[this.procedural] || this.poses.idle;
    if (pose) for (const [b, q] of pose) b.quaternion.slerp(q, dt > 0 ? 1 - Math.exp(-dt * 14) : 1);
  }
}

export async function crearEquipo(scene) {
  const equipo = {};
  await Promise.all(PERSONAJES.map(async id => {
    const d = await cargarGLB(`./models/${id}.glb`);
    if (!d) throw new Error('No se pudo cargar un integrante del equipo.');
    // Cada modelo se instancia una sola vez. Conservamos las matrices originales
    // de enlace del rig al medirlo, antes de normalizar su tamaño.
    const actor = new Integrante(normalizar(d.scene, 2.45, { sombras: false }), d.animations);
    actor.play('idle'); actor.update(0);
    scene.add(actor.root); equipo[id] = actor;
  }));
  return equipo;
}
