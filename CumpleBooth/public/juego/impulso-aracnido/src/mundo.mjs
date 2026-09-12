import * as THREE from '../vendor/three.webgpu.js';
import { cargarGLB, normalizar } from './modelos.mjs';
import { carril, aroY } from './reglas.mjs';
import { crearEntorno } from './entorno.mjs';
import { crearEquipo } from './equipo.mjs';
import { PERSONAJES, personajeValido } from './avatares.mjs';
const COLOR = { oro: '#ffce58', menta: '#a7ccff', coral: '#e63946', azul: '#3d7bff' };
const basic = color => new THREE.MeshBasicMaterial({ color });
export async function crearMundo(scene, progreso) {
  const entorno = await crearEntorno(scene); progreso(.3);
  const caja = new THREE.BoxGeometry(1,1,1), dummy = new THREE.Object3D();
  const red = new THREE.Group(); scene.add(red);
  const redLineas = new THREE.InstancedMesh(caja, basic('#b8d8ff'), 72); red.add(redLineas); redLineas.frustumCulled=false;
  for(let i=0;i<72;i++) { dummy.position.set((i%24-12)*2.8,1.9, i<24?-3.3:i<48?0:3.3); dummy.scale.set(2.2,.045,.045); dummy.rotation.set(0,i<48?.45:-.45,0); dummy.updateMatrix(); redLineas.setMatrixAt(i,dummy.matrix); }
  const arosVisuales = [], geoAro = new THREE.TorusGeometry(2.25,.105,6,36), colorAro=basic(COLOR.oro), colorDentro = basic('#ffe6a2');
  for(let i=0;i<14;i++) { const grupo=new THREE.Group(); const aro=new THREE.Mesh(geoAro,colorAro); aro.rotation.y=Math.PI/2; grupo.add(aro); const centro=new THREE.Mesh(new THREE.OctahedronGeometry(.5),colorDentro); grupo.add(centro); scene.add(grupo); arosVisuales.push({grupo,centro,indice:-1}); }
  const cable = new THREE.Mesh(new THREE.CylinderGeometry(.047,.047,1,6),basic('#fff0cc')); scene.add(cable); cable.visible=false;
  const ancla = new THREE.Group(), anclaCentro=new THREE.Mesh(new THREE.OctahedronGeometry(.6),basic(COLOR.menta)); ancla.add(anclaCentro);
  const anclaAro=new THREE.Mesh(new THREE.TorusGeometry(.9,.07,5,20),basic(COLOR.menta)); ancla.add(anclaAro);scene.add(ancla);
  const estela=new THREE.InstancedMesh(new THREE.SphereGeometry(.12,4,3),basic(COLOR.menta),28);estela.frustumCulled=false;scene.add(estela);
  const historial=[]; const partPos=new Float32Array(120*3), partGeo=new THREE.BufferGeometry(); partGeo.setAttribute('position',new THREE.BufferAttribute(partPos,3));
  const particulas=new THREE.Points(partGeo,new THREE.PointsMaterial({color:COLOR.oro,size:.22,sizeAttenuation:true}));scene.add(particulas);let destello=0, origen=new THREE.Vector3();
  const equipo=await crearEquipo(scene);let elegido='heroe',personaje=equipo.heroe;progreso(.55);
  function elegir(id){elegido=personajeValido(id);personaje=equipo[elegido];}
  const celebracion=new THREE.Group();scene.add(celebracion);celebracion.visible=false;
  const plataforma=new THREE.Mesh(new THREE.CylinderGeometry(9,9,1,48),new THREE.MeshStandardMaterial({color:'#234bbc',roughness:.55}));plataforma.position.y=.6;celebracion.add(plataforma);
  const borde=new THREE.Mesh(new THREE.TorusGeometry(8.8,.14,6,64),basic(COLOR.coral));borde.rotation.x=Math.PI/2;borde.position.y=1.15;celebracion.add(borde);
  const fiestaMixers=[];
  const nombres=['guia','robot','gato','perro','conejo'];
  await Promise.all(nombres.map(async (nombre,i)=>{const d=await cargarGLB(`./models/${nombre}.glb`);if(!d)throw new Error('No se pudo cargar un invitado.');const m=normalizar(d.scene,i>=2?1.1:2.15,{sombras:false});const g=new THREE.Group();g.add(m);const ang=Math.PI+(i/(nombres.length-1))*Math.PI;g.position.set(Math.cos(ang)*5,1.15,Math.sin(ang)*5);g.rotation.y=-ang+Math.PI/2;celebracion.add(g);if(d.animations.length){const mixer=new THREE.AnimationMixer(m);mixer.clipAction(d.animations[0]).play();fiestaMixers.push(mixer);}g.userData.base=i;}));progreso(.92);
  const cartelCanvas=document.createElement('canvas');cartelCanvas.width=1024;cartelCanvas.height=384;const cartelTex=new THREE.CanvasTexture(cartelCanvas);cartelTex.colorSpace=THREE.SRGBColorSpace;
  const cartel=new THREE.Mesh(new THREE.PlaneGeometry(10,3.75),new THREE.MeshBasicMaterial({map:cartelTex,transparent:true,side:THREE.DoubleSide}));cartel.position.set(-2.8,8,-5);celebracion.add(cartel);
  function letrero(nombre,puntos){const c=cartelCanvas.getContext('2d');c.clearRect(0,0,1024,384);c.fillStyle='#101e33';c.fillRect(5,5,1014,374);c.strokeStyle=COLOR.menta;c.lineWidth=7;c.strokeRect(8,8,1008,368);c.textAlign='center';c.fillStyle=COLOR.oro;c.font='bold 38px Trebuchet MS';c.fillText('¡LA CIUDAD CELEBRA CONTIGO!',512,83);c.fillStyle='#fff4d9';c.font=`bold ${nombre.length>18?45:66}px Trebuchet MS`;c.fillText(nombre,512,187,930);c.fillStyle=COLOR.menta;c.font='bold 35px Trebuchet MS';c.fillText(`${puntos} estrellas · ¡Gran equipo!`,512,285);cartelTex.needsUpdate=true;}
  function ciudad(x,final){entorno.actualizar(x,final);red.position.x=x;red.visible=!final;}
  function chispas(x,y,z){origen.set(x,y,z);destello=1;}
  const eje=new THREE.Vector3(0,1,0),vec=new THREE.Vector3(),a=new THREE.Vector3(),b=new THREE.Vector3();
  function actualizar(s,dt,tiempo,aros,fase,suave){
    const final=fase==='celebracion'||fase==='final',presentando=fase==='inicio';celebracion.visible=final;ancla.visible=!final&&!presentando;estela.visible=!final&&!presentando;
    const acompanantes=PERSONAJES.filter(id=>id!==elegido);
    for(const id of PERSONAJES){
      const actor=equipo[id];actor.root.visible=id===elegido||final||presentando;
      if(id===elegido)continue;
      const lado=acompanantes.indexOf(id)===0?-1:1;
      actor.root.position.set(s.x+lado*3,final?1.2:s.y,final?carril(s.x)+1.4:s.z-1.2);
      actor.root.rotation.set(0,presentando?-.4:0,0);actor.play(final?'wave':'idle');if(actor.root.visible)actor.update(dt);
    }
    ciudad(s.x,final);for(const v of arosVisuales)v.grupo.visible=!final&&!presentando;
    if(final){celebracion.position.set(s.x,0,carril(s.x));personaje.root.position.set(s.x,1.2,carril(s.x)+2);personaje.root.rotation.set(0,0,0);personaje.play('wave');for(const m of fiestaMixers)m.update(dt);}
    else{
      personaje.root.position.set(s.x,s.y,s.z);personaje.root.rotation.set(0,Math.PI/2,s.fase==='balanceo'?-s.theta*.4:Math.max(-.35,Math.min(.35,-s.vy*.025)));
      personaje.play(s.fase==='listo'?'idle':'jump',{speed:.65});
      if(s.fase==='balanceo'){const p=s.pivote;a.set(s.x,s.y+1.8,s.z);b.set(p.x,p.y+2,carril(p.x));vec.subVectors(b,a);cable.position.copy(a).add(b).multiplyScalar(.5);cable.scale.y=vec.length();cable.quaternion.setFromUnitVectors(eje,vec.normalize());cable.visible=true;ancla.position.copy(b);}else{cable.visible=false;ancla.position.set(s.x+6,s.y+7,carril(s.x+6));}
      anclaAro.rotation.z=tiempo*.8;anclaCentro.rotation.y=tiempo;
      const first=Math.max(0,Math.floor((s.x-18)/16));
      for(let k=0;k<arosVisuales.length;k++){const i=first+k;let ar=aros.find(o=>o.i===i);if(!ar){ar={i,x:18+i*16,y:aroY(i),hecho:false,acierto:false};aros.push(ar);}const v=arosVisuales[k];v.grupo.position.set(ar.x,ar.y,carril(ar.x));v.grupo.visible=!ar.hecho&&!presentando;v.centro.rotation.set(tiempo,tiempo*.8,0);}
      while(aros.length>70)aros.shift();
      if(dt>0){historial.unshift([s.x,s.y+.7,s.z]);if(historial.length>28)historial.pop();}
      for(let i=0;i<28;i++){const p=historial[i]||[s.x,s.y,s.z];dummy.position.set(...p);dummy.rotation.set(0,0,0);dummy.scale.setScalar((1-i/28)*.8);dummy.updateMatrix();estela.setMatrixAt(i,dummy.matrix);}estela.instanceMatrix.needsUpdate=true;
    }
    if(final)cable.visible=false;personaje.update(dt);
    destello=Math.max(0,destello-dt*.9);particulas.visible=destello>0&&!suave;
    for(let i=0;i<120;i++){const ang=i*2.399,dist=(1-destello)*7;partPos[i*3]=origen.x+Math.cos(ang)*dist;partPos[i*3+1]=origen.y+Math.sin(i*3.7)*dist+destello;partPos[i*3+2]=origen.z+Math.sin(ang)*dist;}
    partGeo.attributes.position.needsUpdate=true;
  }
  return {actualizar,chispas,letrero,elegir,celebracion,avatar:()=>elegido};
}
