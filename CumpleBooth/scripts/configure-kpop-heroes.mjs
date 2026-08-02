import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const target = path.join(root, 'public', 'data', 'themes.json')
const document = JSON.parse(fs.readFileSync(target, 'utf8'))
const themes = document.themes

const fallingGame = (label, emojis) => ({
  kind: 'copos',
  label,
  emojis,
  seconds: 15,
})
const puzzle = (base) => ({
  kind: 'fichas',
  label: '¡Arma la imagen!',
  image: `puzzle-${base}.jpg`,
  cols: 3,
  filas: 3,
})

themes.kpop = {
  nombre: 'Guerreras K-Pop',
  franquicia: 'KPop Demon Hunters',
  publico: 'mixto',
  diploma: 'Guerrera Legendaria del Escenario',
  transition: 'none',
  colors: {
    accent: '#e0218a',
    accentSoft: '#ffe3f3',
    yellow: '#ffd54f',
    ink: '#1a0b2e',
    bgLight1: '#fce4ff',
    bgLight2: '#e0f7ff',
    dark1: '#3d0b52',
    dark2: '#12043a',
    dark3: '#00d4ff',
  },
  confetti: ['#e0218a', '#00d4ff', '#ffd54f', '#a855f7', '#ffffff', '#ff6ec7'],
  personajes: [
    {
      emoji: '🎤',
      name: 'Rumi',
      img: 'rumi.jpg',
      game: [
        { kind: 'ritmo', label: '¡Sigue el ritmo!', image: 'fondo-juego-escenario.jpg', lanes: 4, seconds: 15 },
        puzzle('rumi'),
        fallingGame('¡Atrapa las estrellas!', ['⭐', '💫', '🎵', '💖']),
      ],
    },
    { emoji: '⚔️', name: 'Mira', img: 'mira.jpg', game: [puzzle('mira'), fallingGame('¡Atrapa las estrellas!', ['⭐', '💫', '🎵', '💖'])] },
    { emoji: '💜', name: 'Zoey', img: 'zoey.jpg', game: [puzzle('zoey'), fallingGame('¡Atrapa las estrellas!', ['⭐', '💫', '🎵', '💖'])] },
    { emoji: '🌟', name: 'Luna', img: 'luna.jpg', game: [puzzle('luna'), fallingGame('¡Atrapa las estrellas!', ['⭐', '💫', '🎵', '💖'])] },
    { emoji: '🐯', name: 'Derpy', img: 'derpy.jpg', game: [puzzle('derpy'), fallingGame('¡Atrapa las estrellas!', ['⭐', '💫', '🎵', '💖'])] },
    { emoji: '🎶', name: 'Sussie', img: 'sussie.jpg', game: [puzzle('sussie'), fallingGame('¡Atrapa las estrellas!', ['⭐', '💫', '🎵', '💖'])] },
  ],
  photoSession: {
    video: 'entrada-escenario.mp4',
    poster: 'entrada-escenario-poster.jpg',
    characters: ['Rumi', 'Mira', 'Zoey'],
    teaser: 'escenario-teaser.jpg',
    teaserLabel: 'El trío legendario',
  },
  videos: {
    welcome: 'welcome-kpop.mp4',
    revelacion: 'revelacion-kpop.mp4',
    despedida: 'despedida-kpop.mp4',
  },
  frameBox: { x: 0.3, y: 0.34, w: 0.4, h: 0.24 },
  musicaHint: 'Pop coreano energético / girl group',
}

const heroes = themes.heroes
if (!heroes || heroes.publico !== 'niño') {
  throw new Error('La entrada heroes no existe o cambió su público; se aborta para no recrearla.')
}
heroes.transition = 'none'
const heroEmojiSet = ['⚡', '🔨', '💥', '✨']
for (const character of heroes.personajes) {
  const base = path.parse(character.img).name
  const games = [
    puzzle(base),
    fallingGame('¡Atrapa los rayos!', heroEmojiSet),
  ]
  if (character.name === 'Capitán') {
    games.unshift({
      kind: 'escudo',
      label: '¡Activa el escudo!',
      image: 'fondo-juego-ciudad.jpg',
      seconds: 15,
    })
  }
  character.game = games
}
heroes.photoSession = {
  video: 'comic-cobra-vida.mp4',
  poster: 'comic-cobra-vida-poster.jpg',
  characters: ['Araña', 'Hombre de Hierro', 'Capitán'],
  teaser: 'comic-teaser.jpg',
  teaserLabel: 'Los héroes del cómic',
}
heroes.videos = {
  welcome: 'welcome-heroes.mp4',
  revelacion: 'revelacion-heroes.mp4',
  despedida: 'despedida-heroes.mp4',
}

fs.writeFileSync(target, `${JSON.stringify(document, null, 2)}\n`, 'utf8')
console.log('OK themes.json: kpop agregado y heroes extendido.')
