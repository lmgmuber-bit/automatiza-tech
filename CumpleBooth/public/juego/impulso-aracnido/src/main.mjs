import * as THREE from '../vendor/three.webgpu.js';
import { crearMotor } from './motor.mjs';
import { crearMundo } from './mundo.mjs';
import { nuevoVuelo, avanzar, resolverAros, carril } from './reglas.mjs';
import { parametros, guardar, cargar, borrar, regreso } from './datos.mjs';
import { crearSonido } from './sonido.mjs';
import { PERSONAJES, ROLES, personajeValido } from './avatares.mjs';
// Agregado por la integracion con CumpleClick: anota el puntaje en la tabla de la
// fiesta. Si falla no pasa nada visible; el juego no depende de esto.
import { anotar } from './posiciones.mjs';
const $=id=>document.getElementById(id), p=parametros(location.search), nacimiento=performance.now();
const ui={portada:$('portada'),panel:$('panel'),hud:$('hud'),boton:$('impulso'),mensaje:$('mensaje')};
const sonido=crearSonido($('subtitulo'));let motor,mundo,phase='cargando',s=nuevoVuelo(),aros=[],held=false,solto=false,pointer=null;
let modo='paseo',formato='individual',cantidad=1,turno=0,resultados=[],restante=90,puntos=0,cadena=0,totalAros=0,perfectos=0;
let suave=matchMedia('(prefers-reduced-motion: reduce)').matches,muted=false,tiempo=0,guardadoT=0,logroT=0,finT=0,prev=0,listoMs=0,contador=0,redesAvisadas=0;
let borrador=null;try{borrador=cargar(localStorage,p.fiesta);}catch{}
let avatar='heroe';
function aplicarAvatar(id){
  avatar=personajeValido(id);mundo?.elegir(avatar);document.body.dataset.avatar=avatar;
  for(const grupo of ['equipo','equipo-panel'])document.querySelector('#'+grupo+' input[value="'+avatar+'"]').checked=true;
  $('avatar-hud').src='./texturas/retrato-'+avatar+'.png';$('avatar-hud').alt=ROLES[avatar];
  $('avatar-elegido').textContent=ROLES[avatar];
}
function tarjetasEquipo(id,nombre){
  const grupo=$(id);
  for(const rol of PERSONAJES){
    const label=document.createElement('label'),input=document.createElement('input'),tarjeta=document.createElement('span');
    label.className='ficha-personaje '+rol;input.type='radio';input.name=nombre;input.value=rol;input.checked=rol==='heroe';
    const retrato=document.createElement('img');retrato.src='./texturas/retrato-'+rol+'.png';retrato.alt='';retrato.width=256;retrato.height=256;
    const texto=document.createElement('b');texto.textContent=ROLES[rol];const marca=document.createElement('i');marca.className='elegido';marca.textContent='✓';marca.setAttribute('aria-hidden','true');
    tarjeta.append(retrato,texto,marca);label.append(input,tarjeta);grupo.append(label);
  }
  grupo.addEventListener('change',e=>{aplicarAvatar(e.target.value);if(phase==='pausa'&&faseAntes==='jugando')persistir();if(phase==='final'&&turno+1<cantidad)guardarSiguiente();});
}
tarjetasEquipo('equipo','avatar');tarjetasEquipo('equipo-panel','avatar-panel');
$('fiesta').textContent=p.nombre==='Tu fiesta'?'UN VUELO PARA CELEBRAR':`La fiesta de ${p.nombre} · ${p.edad} años`;
$('suave').checked=suave;$('volver').href=regreso(p,location.href);$('salida-final').href=$('volver').href;
$('formato').addEventListener('change',()=>{$('cantidad-fila').hidden=$('formato').value!=='turnos';});
function soltarEntrada(){if(held)solto=true;held=false;pointer=null;ui.boton.classList.remove('activo');}
ui.boton.addEventListener('pointerdown',e=>{if(phase!=='jugando'||pointer!==null)return;e.preventDefault();pointer=e.pointerId;ui.boton.setPointerCapture(pointer);held=true;ui.boton.classList.add('activo');sonido.empezar();});
ui.boton.addEventListener('pointerup',e=>{if(e.pointerId===pointer)soltarEntrada();});
ui.boton.addEventListener('pointercancel',()=>pausar());ui.boton.addEventListener('lostpointercapture',()=>{if(held)soltarEntrada();});
addEventListener('keydown',e=>{if(e.code==='Escape'&&phase==='jugando'){pausar();return;}if(e.code==='Space'&&phase==='jugando'&&!e.repeat){e.preventDefault();held=true;ui.boton.classList.add('activo');sonido.empezar();}});
addEventListener('keyup',e=>{if(e.code==='Space')soltarEntrada();});
addEventListener('blur',()=>{if(phase==='jugando'||phase==='celebracion')pausar();else soltarEntrada();});
document.addEventListener('visibilitychange',()=>{if(document.hidden){pausar();sonido.pausar();}prev=performance.now();motor?.resetReloj();});
addEventListener('pagehide',()=>{if(phase==='jugando'||phase==='pausa')persistir();sonido.pausar();});
function estado(){return{phase,avatar,avatarVisible:mundo?.avatar(),modo,formato,cantidad,turno,resultados:[...resultados],restante,puntos,cadena,totalAros,perfectos,posicion:{...s},readyMs:listoMs,held,fiesta:p.fiesta,sonido:sonido.registro,metricas:motor?{...motor.metricas,muestras:undefined}:null};}
function persistir(){if(resultados.length!==turno)return;try{guardar(localStorage,p.fiesta,{avatar,modo,formato,cantidad,turno,resultados,restante,puntos,sonido:!muted,suave});}catch{}}
function guardarSiguiente(){if(turno+1>=cantidad)return;try{guardar(localStorage,p.fiesta,{avatar,modo,formato,cantidad,turno:turno+1,resultados,restante:60,puntos:0,sonido:!muted,suave});}catch{}}
function limpiarGuardado(){try{borrar(localStorage,p.fiesta);}catch{}borrador=null;$('continuar').hidden=true;}
function ocultarPanel(){ui.panel.hidden=true;}
let accion=null,faseAntes='jugando';
function panel(ceja,titulo,texto,boton,fn,opciones=true){soltarEntrada();ui.panel.hidden=false;$('ceja-panel').textContent=ceja;$('titulo-panel').textContent=titulo;$('texto-panel').textContent=texto;$('accion-panel').textContent=boton;accion=fn;$('inicio-panel').hidden=!opciones;$('resultados').replaceChildren();$('equipo-panel').hidden=!(phase==='pausa'||(phase==='final'&&turno+1<cantidad));}
$('accion-panel').addEventListener('click',()=>{sonido.empezar();accion?.();});
$('inicio-panel').addEventListener('click',()=>{ocultarPanel();phase='inicio';ui.portada.hidden=false;ui.hud.hidden=true;ui.boton.hidden=true;ui.mensaje.hidden=true;sonido.pausar();s=nuevoVuelo();borrador=null;try{borrador=cargar(localStorage,p.fiesta);}catch{}$('continuar').hidden=!borrador;});
function configurar(){aplicarAvatar(new FormData($('opciones')).get('avatar'));modo=new FormData($('opciones')).get('modo');formato=$('formato').value;cantidad=formato==='turnos'?Number($('cantidad').value):1;muted=!$('sonido').checked;suave=$('suave').checked;sonido.configurar(!muted);}
function iniciarRonda(reanudar=false){ocultarPanel();$('salida-final').hidden=true;$('accion-panel').hidden=false;$('pausar').hidden=false;ui.portada.hidden=true;ui.hud.hidden=false;ui.boton.hidden=false;ui.mensaje.hidden=false;$('tecla').hidden=false;phase='jugando';s=nuevoVuelo();aros=[];held=false;solto=false;cadena=0;totalAros=0;perfectos=0;contador=0;redesAvisadas=0;finT=0;
  if(!reanudar){restante=formato==='turnos'?60:90;puntos=0;}
  aplicarAvatar(avatar);sonido.reiniciar();sonido.empezar();sonido.decir(modo==='paseo'?'paseo':'tutorial');persistir();prev=performance.now();motor.resetReloj();
}
$('opciones').addEventListener('submit',e=>{e.preventDefault();if(!mundo)return;configurar();turno=0;resultados=[];limpiarGuardado();iniciarRonda();});
$('continuar').addEventListener('click',()=>{if(!borrador)return;({avatar,modo,formato,cantidad,turno,resultados,restante,puntos,suave}=borrador);muted=!borrador.sonido;sonido.configurar(!muted);iniciarRonda(true);});
function pausar(){if(phase!=='jugando'&&phase!=='celebracion')return;faseAntes=phase;phase='pausa';sonido.pausar();if(faseAntes==='jugando')persistir();panel('RESPIRA UN RATITO','PAUSA',p.fiesta?'Tu ronda queda guardada en este dispositivo.':'Tu ronda está pausada. Sin enlace de fiesta, no se conserva al cerrar.','SEGUIR JUGANDO',()=>{ocultarPanel();phase=faseAntes;prev=performance.now();motor.resetReloj();});if(!document.hidden)sonido.decir('pausa');}
$('pausar').addEventListener('click',pausar);
function logro(texto){$('logro').textContent=texto;logroT=1.45;}
function terminar(){phase='celebracion';soltarEntrada();ui.boton.hidden=true;ui.mensaje.hidden=true;$('tecla').hidden=true;finT=0;resultados.push(puntos);anotar(puntos,formato);mundo.letrero(p.nombre,resultados.reduce((a,b)=>a+b,0));sonido.fiesta();if(turno+1>=cantidad)sonido.decir('final');mundo.chispas(s.x,5,s.z);limpiarGuardado();guardarSiguiente();}
function mostrarFinal(){const ultimo=turno+1>=cantidad;phase='final';panel(ultimo?'¡LA CIUDAD SE ENCENDIÓ!':`TURNO ${turno+1} COMPLETADO`,ultimo?'¡GRAN VUELO!':'¡TE TOCA A TI!',ultimo?'Gracias por jugar. Ahora, ¡a disfrutar la fiesta!':`Pasa la tablet al participante ${turno+2}. La próxima ronda empieza cuando toque el botón.`,ultimo?'VER LA CELEBRACIÓN':`EMPEZAR TURNO ${turno+2}`,()=>{if(ultimo){ui.panel.hidden=true;$('salida-final').hidden=false;sonido.decir('regreso');}else{turno++;iniciarRonda();}},true);
  $('pausar').hidden=true;for(let i=0;i<resultados.length;i++){const el=document.createElement('span');el.textContent=`${formato==='turnos'?'Turno '+(i+1):'Mi ronda'} · ${resultados[i]} ★`;$('resultados').append(el);}if(!ultimo)sonido.decir('turno');
}
function actualizarUI(){
  $('marcador').textContent=`${puntos} ★`;$('etiqueta-turno').textContent=formato==='turnos'?`TURNO ${turno+1} / ${cantidad}`:'MI RONDA';
  const segundos=Math.ceil(restante);$('tiempo').textContent=`${Math.floor(segundos/60)}:${String(segundos%60).padStart(2,'0')}`;
  const district=Math.floor(s.x/180)%3;$('sector').textContent=['DISTRITO 01 / DESPEGUE','DISTRITO 02 / LUCES DEL CANAL','DISTRITO 03 / LA GRAN RED'][district];
  const verde=s.fase==='balanceo'&&s.theta>=.2&&s.theta<=.8&&s.omega>0;ui.boton.classList.toggle('listo',verde);
  $('consejo').textContent=s.fase==='listo'?'MANTÉN PARA BALANCEARTE':s.fase==='balanceo'?(verde?'¡SUELTA Y SAL VOLANDO!':'MANTÉN… TOMA IMPULSO'):s.evento==='red'?'¡LA RED TE AYUDA!':'¡VUELA! VUELVE A MANTENER';
  $('texto-impulso').textContent=verde?'¡SUELTA!':'MANTÉN';$('ayuda-impulso').textContent=s.fase==='balanceo'?'PARA TOMAR IMPULSO':'PARA ENGANCHARTE';
  $('medidor').hidden=s.fase!=='balanceo';$('medidor').lastElementChild.style.left=`${Math.max(0,Math.min(100,(s.theta+1)/2.2*100))}%`;
}
const camPos=new THREE.Vector3(),look=new THREE.Vector3();
function frame(now){const wallDt=Math.max(0,(now-prev)/1000),dt=Math.min(.1,wallDt);prev=now;if(!document.hidden){tiempo+=dt;
  if(phase==='jugando'){
    if(s.fase!=='listo'){restante=Math.max(0,restante-wallDt);guardadoT+=dt;if(guardadoT>=1){persistir();guardadoT=0;}}
    let queda=dt;while(queda>0){const paso=Math.min(1/90,queda),anterior={x:s.x,y:s.y};avanzar(s,paso,held,modo,solto);solto=false;const r=resolverAros(anterior,s,aros,modo,cadena);puntos+=r.puntos;cadena=r.cadena;totalAros+=r.aciertos;if(r.aciertos){sonido.nota(cadena);logro(cadena>1?`¡CADENA ×${cadena}!`:'¡BUEN VUELO!');mundo.chispas(s.x,s.y,s.z);}if(s.evento==='perfecto'){perfectos++;puntos+=5;logro('¡IMPULSO PERFECTO!');sonido.nota(5);}queda-=paso;}
    if(perfectos>contador){contador=perfectos;sonido.decir('perfecto');}if(s.rebotes>redesAvisadas){redesAvisadas=s.rebotes;sonido.decir('red');}if(s.fase==='vuelo'&&s.tiempoVuelo>1.2&&modo!=='paseo')sonido.decir('enganche');actualizarUI();if(restante<=0)terminar();
  }else if(phase==='celebracion'){finT+=dt;if(finT>=5)mostrarFinal();}
  if(logroT>0){logroT-=dt;if(logroT<=0)$('logro').textContent='';}
  const portada=phase==='inicio'||phase==='cargando',final=phase==='celebracion'||phase==='final';const aspecto=innerWidth/innerHeight;
  if(portada){camPos.set(-10,14,25);look.set(5,8,0);}
  else if(final){camPos.set(s.x+13,11,23+carril(s.x));look.set(s.x,4,carril(s.x));}
  else{camPos.set(s.x-(aspecto<1?10:12),suave?14:13+Math.min(3,s.y*.12),s.z+(aspecto<1?22:20));look.set(s.x+(aspecto<1?1:5),suave?9:9+s.y*.08,s.z);}
  motor.camera.position.lerp(camPos,1-Math.exp(-dt*(suave?3:5)));motor.camera.lookAt(look);
  mundo.actualizar(s,phase==='pausa'?0:dt,tiempo,aros,phase,suave);motor.render(wallDt,phase);
  if(p.debug)$('telemetria').textContent=`${motor.metricas.backend} · ${motor.metricas.fps.toFixed(1)} fps · ${motor.metricas.calls} llamadas · ${motor.metricas.triangles} triángulos · ${phase}`;
}requestAnimationFrame(frame);}
async function inicio(){
  try{motor=await crearMotor($('ciudad'),p.webgl);mundo=await crearMundo(motor.scene,v=>{$('avance-carga').style.width=`${Math.round(v*100)}%`;});motor.camera.position.set(-10,14,25);motor.camera.lookAt(5,8,0);mundo.actualizar(s,0,0,aros,'inicio',suave);motor.renderer.render(motor.scene,motor.camera);
    aplicarAvatar(avatar);phase='inicio';listoMs=performance.now()-nacimiento;$('jugar').disabled=false;$('jugar').textContent='¡VAMOS A VOLAR! ↗';$('estado-carga').textContent=p.fiesta?'Tu progreso se guarda solo para esta fiesta.':'Sin enlace de fiesta: esta ronda no se guardará.';$('avance-carga').style.width='100%';$('continuar').hidden=!borrador;
    if(p.debug){$('telemetria').hidden=false;window.__impulso={state:estado,metrics:()=>motor.metricas.muestras.slice()};}prev=performance.now();requestAnimationFrame(frame);
  }catch(e){phase='error';$('estado-carga').textContent='No pudimos abrir la ciudad. Revisa la conexión y recarga la página.';$('jugar').textContent='NO SE PUDO CARGAR';console.error(e);}
}
inicio();
