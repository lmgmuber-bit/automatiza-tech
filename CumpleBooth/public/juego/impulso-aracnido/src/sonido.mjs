import { NARRACION } from './narracion.mjs';
const FRASES = Object.fromEntries(Object.entries(NARRACION).map(([k,v])=>[k,['alice-'+k,v]]));
export function crearSonido(subtitulo) {
  const musica = new Audio('./audio/reino.mp3'); musica.loop=true;musica.volume=.17;
  let activa=true, voz=null, contexto=null, timer=0, ultimaAyuda=0;const registro={completadas:[],fallos:0};
  function liberar(){if(!contexto){const C=window.AudioContext||window.webkitAudioContext;if(C)contexto=new C();}contexto?.resume().catch(()=>{});}
  function empezar(){liberar();if(activa)musica.play().catch(()=>{registro.fallos++;});}
  function pararVoz(){clearTimeout(timer);if(voz){voz.pause();voz=null;}subtitulo.textContent='';musica.volume=.17;}
  function decir(id){const ayuda=['red','enganche','perfecto'].includes(id);if(ayuda&&((voz&&!voz.paused)||performance.now()-ultimaAyuda<7000))return;if(ayuda)ultimaAyuda=performance.now();pararVoz();const f=FRASES[id];if(!f)return;subtitulo.textContent=f[1];timer=setTimeout(()=>subtitulo.textContent='',9500);if(!activa)return;voz=new Audio(`./audio/voces/${f[0]}.mp3`);musica.volume=.055;voz.addEventListener('ended',()=>{registro.completadas.push(id);subtitulo.textContent='';musica.volume=.17;voz=null;},{once:true});voz.play().catch(()=>{registro.fallos++;});}
  return {registro,empezar,decir,configurar(v){activa=v;if(!v){musica.pause();pararVoz();}},pausar(){musica.pause();pararVoz();contexto?.suspend().catch(()=>{});},fiesta(){musica.src='./audio/fiesta.mp3';empezar();},reiniciar(){musica.src='./audio/reino.mp3';},nota(cadena=1){if(!activa)return;liberar();if(!contexto)return;const osc=contexto.createOscillator(),gain=contexto.createGain();osc.type='sine';osc.frequency.setValueAtTime(390*Math.pow(1.12,Math.min(cadena,8)),contexto.currentTime);gain.gain.setValueAtTime(.065,contexto.currentTime);gain.gain.exponentialRampToValueAtTime(.0001,contexto.currentTime+.32);osc.connect(gain).connect(contexto.destination);osc.start();osc.stop(contexto.currentTime+.33);}};
}
