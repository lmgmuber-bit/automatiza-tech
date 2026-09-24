import * as THREE from '../vendor/three.webgpu.js';

const PASO = 12;
const BLOQUES = 24;
const mod = (n, m) => ((n % m) + m) % m;
const mate = (color, extra = {}) => new THREE.MeshStandardMaterial({ color, roughness: .82, ...extra });

export async function crearEntorno(scene) {
  const loader = new THREE.TextureLoader();
  async function mapa(nombre, rx = 1, ry = 1) {
    const t = await loader.loadAsync(`./texturas/${nombre}`);
    t.colorSpace = THREE.SRGBColorSpace;
    t.wrapS = t.wrapT = THREE.RepeatWrapping;
    t.repeat.set(rx, ry);
    t.anisotropy = 4;
    return t;
  }
  const [roja, clara, azotea, pavimento, panorama] = await Promise.all([
    mapa('fachada-ladrillo-v2.jpg'),
    mapa('fachada-piedra-v2.jpg'),
    mapa('tex-techo-color.jpg', 2, 2),
    mapa('tex-vereda-color.jpg', 32, 1),
    mapa('horizonte-ciudad-v2.jpg'),
  ]);
  const dummy = new THREE.Object3D();
  const color = new THREE.Color();
  const caja = new THREE.BoxGeometry(1, 1, 1);
  const esfera = new THREE.IcosahedronGeometry(1, 1);
  const cilindro = new THREE.CylinderGeometry(1, 1, 1, 10);
  const lotes = [];
  function lote(nombre, geometry, material, capacidad) {
    const mesh = new THREE.InstancedMesh(geometry, material, capacidad);
    mesh.name = nombre;
    mesh.count = 0;
    mesh.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
    // La ventana urbana se recicla; no usar bounds de la posición anterior.
    mesh.frustumCulled = false;
    scene.add(mesh);
    lotes.push(mesh);
    return mesh;
  }
  function pieza(mesh, x, y, z, sx, sy, sz, tinte, rx = 0, ry = 0, rz = 0) {
    if (mesh.count >= mesh.instanceMatrix.count) throw new Error('Capacidad de detalle urbano excedida: ' + mesh.name);
    dummy.position.set(x, y, z);
    dummy.rotation.set(rx, ry, rz);
    dummy.scale.set(sx, sy, sz);
    dummy.updateMatrix();
    mesh.setMatrixAt(mesh.count, dummy.matrix);
    if (tinte) mesh.setColorAt(mesh.count, color.set(tinte));
    mesh.count++;
  }
  const fachadas = [
    lote('Fachadas de ladrillo', caja, mate('#ffffff', { map: roja }), 48),
    lote('Fachadas de piedra', caja, mate('#ffffff', { map: clara }), 48),
  ];
  const techos = lote('Azoteas', caja, mate('#7a8285', { map: azotea }), 96);
  const piedra = lote('Cornisas y balcones', caja, mate('#c4c0ad'), 650);
  const metal = lote('Barandas y mobiliario', caja, mate('#38494e', { metalness: .35, roughness: .57 }), 950);
  const madera = lote('Bancas', caja, mate('#926444'), 128);
  const troncos = lote('Troncos', cilindro, mate('#705743'), 100);
  const copas = lote('Copas', esfera, mate('#ffffff', { flatShading: true }), 180);
  const tanques = lote('Estanques', cilindro, mate('#7b8989', { metalness: .4, roughness: .55 }), 28);
  const tapas = lote('Tapas de estanques', new THREE.ConeGeometry(1, 1, 10), mate('#899393', { metalness: .3 }), 28);
  const luces = lote('Luces cálidas', caja, new THREE.MeshBasicMaterial({ color: '#ffdda5' }), 80);
  const toldos = lote('Toldos', caja, mate('#ffffff'), 48);
  const autos = lote('Vehículos estacionados', caja, mate('#ffffff', { metalness: .3, roughness: .38 }), 50);
  const vidrios = lote('Vidrios de vehículos', caja, mate('#263c48', { metalness: .4, roughness: .22 }), 50);
  const ruedas = lote('Ruedas', cilindro, mate('#20282d'), 100);
  const marcas = lote('Demarcaciones', caja, mate('#dedac5'), 180);
  const aceras = lote('Veredas', caja, mate('#c1beb2', { map: pavimento }), 2);
  const sombras = lote('Contacto de edificios', new THREE.PlaneGeometry(1, 1),
    new THREE.MeshBasicMaterial({ color: '#202a31', transparent: true, opacity: .19, depthWrite: false }), 100);

  const base = new THREE.Group();
  scene.add(base);
  const suelo = new THREE.Mesh(new THREE.PlaneGeometry(600, 300), mate('#6b716c'));
  suelo.rotation.x = -Math.PI / 2;
  suelo.position.y = -.04;
  base.add(suelo);
  const calle = new THREE.Mesh(new THREE.PlaneGeometry(600, 14), mate('#414d54'));
  calle.rotation.x = -Math.PI / 2;
  calle.position.y = .015;
  base.add(calle);

  // Sólo el cielo y los edificios distantes de la fotografía: excluir la azotea inferior.
  panorama.wrapS = panorama.wrapT = THREE.ClampToEdgeWrapping;
  panorama.offset.set(0, .31);
  panorama.repeat.set(1, .69);
  const cielo = new THREE.Mesh(new THREE.CylinderGeometry(128, 128, 124, 64, 1, true, Math.PI / 3, Math.PI * 7 / 6),
    new THREE.MeshBasicMaterial({ map: panorama, side: THREE.BackSide, fog: false, toneMapped: false, depthWrite: false }));
  cielo.name = 'Horizonte lejano';
  cielo.position.set(0, 37, 0);
  cielo.renderOrder = -10;
  scene.add(cielo);

  function arbol(x, z, variacion) {
    const h = 2.2 + mod(variacion, 3) * .25;
    pieza(troncos, x, h / 2 + .35, z, .13, h, .13);
    pieza(troncos, x + .3, h * .8, z, .065, 1.1, .065, null, 0, 0, -.6);
    for (let c = 0; c < 5; c++) {
      const angulo = c * 2.4 + variacion;
      pieza(copas, x + Math.sin(angulo) * .8, h + .55 + mod(c, 3) * .45, z + Math.cos(angulo) * .75,
        1.05, 1.1 + c * .06, .95, ['#687c46', '#7c8e50', '#516d42'][mod(variacion + c, 3)], 0, angulo);
    }
    pieza(piedra, x, .42, z, 2.5, .25, 2.3);
  }

  function farol(x, z, lado) {
    pieza(metal, x, 2.7, z, .12, 5.4, .12);
    pieza(metal, x, 5.35, z - lado * .55, .13, .13, 1.2);
    pieza(metal, x, 5.3, z - lado * 1.05, .5, .14, .65);
    pieza(luces, x, 5.22, z - lado * 1.05, .38, .065, .48);
  }

  function edificio(n, lado, xActual, plaza) {
    const k = mod(n * 7 + (lado < 0 ? 3 : 0), 11);
    const variante = mod(n, 2);
    const x = n * PASO;
    const z = lado < 0 ? -18 - mod(n, 3) * 2 : 21 + mod(n, 3) * 1.5;
    // Primer plano bajo: protege la lectura del héroe y de la celebración.
    const alto = lado < 0 ? 8.2 + k * .48 : 5.5 + mod(k, 4) * .45;
    const ancho = 8.1 + mod(k, 3) * .5;
    const fondo = 8.2;
    const frente = z - lado * fondo / 2;
    pieza(fachadas[variante], x, alto / 2 + .4, z, ancho, alto, fondo);
    pieza(techos, x, alto + .48, z, ancho + .18, .22, fondo + .18);
    pieza(sombras, x + 1.2, .035, z - lado * .5, ancho + 2.3, fondo + 2, 1, null, -Math.PI / 2);

    // Cornisas, zócalo y parapeto en volumen, no dibujados sobre el cielo.
    pieza(piedra, x, .6, frente, ancho + .3, .38, .4);
    for (const borde of [-1, 1]) {
      pieza(piedra, x, alto + .75, z + borde * fondo / 2, ancho + .5, .55, .25);
      pieza(piedra, x + borde * ancho / 2, alto + .75, z, .25, .55, fondo);
    }
    for (let piso = 1; piso <= 2; piso++) {
      pieza(piedra, x, .4 + alto * piso / 3, frente - lado * .12, ancho + .3, .12, .38);
    }

    // Detalle sólo en el tramo cercano; no añadir cientos de objetos lejanos.
    if (x < xActual - 42 || x > xActual + 108) return;
    const az = alto + .66;
    pieza(metal, x - 1.7, az + .5, z + 1, 1.8, 1, 1.25);
    for (let rejilla = 0; rejilla < 4; rejilla++) {
      pieza(piedra, x - 1.7, az + .25 + rejilla * .17, z + 1.64, 1.45, .035, .035);
    }
    pieza(piedra, x + 1.9, az + .65, z + 1.7, 2.2, 1.3, 2.2);
    pieza(techos, x + 1.9, az + 1.33, z + 1.7, 2.3, .1, 2.3);
    if (mod(n, 3) === 0) {
      for (const dx of [-.6, .6]) pieza(metal, x + dx, az + .45, z - 1.6, .1, .9, .1);
      pieza(tanques, x, az + 1.5, z - 1.6, 1, 1.7, 1);
      pieza(tapas, x, az + 2.5, z - 1.6, 1.06, .45, 1.06);
    }

    // Balcón con losa y baranda al costado de la fachada.
    const bx = x + (variante ? -1 : 1) * ancho / 3;
    const by = .4 + alto / 3;
    const bz = frente - lado * .6;
    pieza(piedra, bx, by, bz, 2.1, .16, 1.3);
    pieza(metal, bx, by + .7, bz - lado * .6, 2.1, .055, .055);
    for (let v = 0; v < 5; v++) pieza(metal, bx - .96 + v * .48, by + .36, bz - lado * .6, .045, .68, .045);
    pieza(toldos, x - ancho / 3, 2.1, frente - lado * .4, 2.4, .13, 1.2,
      variante ? '#688d81' : '#b9694f', lado * .12);
    pieza(metal, x - ancho / 3, 1.2, frente - lado * .025, 1.15, 1.5, .08);

    // Despejar el escenario final: ningún auto, banco o árbol atraviesa la tarima.
    if (plaza && Math.abs(x - xActual) < 18) return;
    const calleZ = lado * 9.2;
    arbol(x + 4.7, calleZ + lado * 1.2, k);
    farol(x - 4.6, calleZ, lado);
    for (let tabla = 0; tabla < 3; tabla++) {
      pieza(madera, x + 2.8, .85, calleZ + (tabla - 1) * .2, 1.7, .12, .17);
    }
    pieza(madera, x + 2.8, 1.22, calleZ + lado * .28, 1.7, .6, .1);
    for (const dx of [-.6, .6]) pieza(metal, x + 2.8 + dx, .6, calleZ, .1, .5, .6);

    if (mod(n, 3) === 1) {
      const cz = lado * 5.6;
      pieza(autos, x, .75, cz, 3.6, .68, 1.55, ['#668489', '#b56a4f', '#c2b49b'][mod(k, 3)]);
      pieza(vidrios, x - .15, 1.3, cz, 1.7, .6, 1.36);
      pieza(autos, x - .15, 1.65, cz, 1.85, .13, 1.45, '#c7c5b7');
      for (const dx of [-1.1, 1.1]) for (const dz of [-.8, .8]) {
        pieza(ruedas, x + dx, .5, cz + dz, .32, .15, .32, null, Math.PI / 2);
      }
    }
  }

  let indiceAnterior = null, plazaAnterior = false;
  function actualizar(x, plaza = false) {
    base.position.x = x;
    cielo.position.x = x;
    const indice = Math.floor(x / PASO);
    if (indice === indiceAnterior && plaza === plazaAnterior) return;
    indiceAnterior = indice;
    plazaAnterior = plaza;
    for (const mesh of lotes) mesh.count = 0;
    for (let j = 0; j < BLOQUES; j++) {
      const n = indice + j - 6;
      edificio(n, -1, x, plaza);
      edificio(n, 1, x, plaza);
      pieza(marcas, n * PASO, .04, 0, 4, .025, .12);
      if (mod(n, 5) === 0) for (let paso = 0; paso < 8; paso++) {
        pieza(marcas, n * PASO + (paso - 3.5) * .6, .04, 0, .35, .025, 10);
      }
    }
    for (const lado of [-1, 1]) {
      pieza(aceras, x, .17, lado * 9.25, 580, .34, 4.5);
      pieza(piedra, x, .24, lado * 7.1, 580, .48, .18);
      pieza(marcas, x, .045, lado * 4.5, 580, .025, .07);
    }
    // Estructuras urbanas de acero con luces de guía; una sola tanda de dibujo.
    for (let i = 0; i < 4; i++) {
      const px = (Math.floor(x / 72) + i) * 72 + 36;
      if (plaza && Math.abs(px - x) < 18) continue;
      for (const lado of [-1, 1]) {
        pieza(piedra, px, .7, lado * 7.3, 1.05, 1.4, 1.05);
        pieza(metal, px, 9.5, lado * 7.3, .35, 18, .35);
        pieza(luces, px, 13, lado * 7.08, .12, 2.3, .065);
      }
      for (const y of [18, 19]) pieza(metal, px, y, 0, .4, .18, 15);
      for (let tramo = 0; tramo < 10; tramo++) {
        pieza(metal, px, 18.5, (tramo - 4.5) * 1.45, .14, 1.8, .1, null, tramo % 2 ? .95 : -.95);
      }
    }
    for (const mesh of lotes) {
      mesh.instanceMatrix.needsUpdate = true;
      if (mesh.instanceColor) mesh.instanceColor.needsUpdate = true;
    }
  }
  return { actualizar };
}
