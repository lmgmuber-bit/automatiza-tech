#!/usr/bin/env node
import { existsSync, mkdirSync, statSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { spawnSync } from 'node:child_process'

const args = process.argv.slice(2)
const allowSilent = args.includes('--allow-silent')
const positional = args.filter((arg) => !arg.startsWith('--'))

if (positional.length !== 2) {
  console.error('Uso: node scripts/normalize-event-profile-video.mjs <entrada.mp4> <salida.mp4> [--allow-silent]')
  process.exit(2)
}

const input = resolve(positional[0])
const output = resolve(positional[1])
const ffmpeg = process.env.CC_FFMPEG_PATH || 'ffmpeg'
const ffprobe = process.env.CC_FFPROBE_PATH || 'ffprobe'

if (!existsSync(input)) {
  throw new Error(`No existe el video de entrada: ${input}`)
}

function run(binary, binaryArgs, label) {
  const result = spawnSync(binary, binaryArgs, { encoding: 'utf8', windowsHide: true })
  if (result.error) throw new Error(`${label}: ${result.error.message}`)
  if (result.status !== 0) throw new Error(`${label}: ${(result.stderr || result.stdout || '').trim()}`)
  return result.stdout
}

function probe(path) {
  return JSON.parse(run(ffprobe, [
    '-v', 'error', '-show_format', '-show_streams', '-of', 'json', path,
  ], 'ffprobe'))
}

const source = probe(input)
const sourceVideo = source.streams?.find((stream) => stream.codec_type === 'video')
const sourceAudio = source.streams?.find((stream) => stream.codec_type === 'audio')
const sourceDuration = Number(source.format?.duration || sourceVideo?.duration || 0)

if (!sourceVideo || !Number.isFinite(sourceDuration) || sourceDuration < 4) {
  throw new Error('El video debe incluir imagen y durar al menos 4 segundos.')
}
if (!sourceAudio && !allowSilent) {
  throw new Error('El video no contiene audio. Repite con --allow-silent solo si Luis aprobó una versión muda.')
}

mkdirSync(dirname(output), { recursive: true })
const ffmpegArgs = [
  '-y', '-v', 'error', '-i', input,
  '-t', '6',
  '-map', '0:v:0',
  ...(sourceAudio ? ['-map', '0:a:0'] : []),
  '-vf', 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2:color=black,setsar=1,fps=30',
  '-c:v', 'libx264', '-preset', 'medium', '-crf', '21', '-profile:v', 'high',
  '-pix_fmt', 'yuv420p', '-movflags', '+faststart',
  ...(sourceAudio ? ['-c:a', 'aac', '-b:a', '128k', '-ar', '48000'] : ['-an']),
  output,
]
run(ffmpeg, ffmpegArgs, 'ffmpeg')

const normalized = probe(output)
const video = normalized.streams?.find((stream) => stream.codec_type === 'video')
const audio = normalized.streams?.find((stream) => stream.codec_type === 'audio')
const duration = Number(normalized.format?.duration || video?.duration || 0)
const bytes = statSync(output).size
const errors = []

if (video?.codec_name !== 'h264') errors.push('codec de video distinto de H.264')
if (video?.pix_fmt !== 'yuv420p') errors.push('pixel format distinto de yuv420p')
if (Number(video?.width) !== 720 || Number(video?.height) !== 1280) errors.push('resolución distinta de 720x1280')
if (duration < 4 || duration > 6.15) errors.push('duración fuera de 4–6 segundos')
if (!audio && !allowSilent) errors.push('audio ausente')
if (bytes > 8 * 1024 * 1024) errors.push('peso mayor a 8 MiB')

const report = {
  ok: errors.length === 0,
  output,
  duration_seconds: Number(duration.toFixed(3)),
  width: Number(video?.width || 0),
  height: Number(video?.height || 0),
  aspect_ratio: '9:16',
  video_codec: video?.codec_name || null,
  pixel_format: video?.pix_fmt || null,
  has_audio: Boolean(audio),
  audio_codec: audio?.codec_name || null,
  byte_size: bytes,
  errors,
}

console.log(JSON.stringify(report, null, 2))
if (!report.ok) process.exit(1)
