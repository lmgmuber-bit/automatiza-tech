$ErrorActionPreference = 'Continue'
$D = 'C:\wamp64\www\automatiza-tech\CumpleBooth\design'
$M = "$D\video"
$S = "$D\screens"
$O = "$D\explicativo\overlays"
$OUT = "$D\explicativo\video-explicativo.mp4"
$TMP = "$env:TEMP\cc-video-segs"
New-Item -ItemType Directory -Force -Path $TMP | Out-Null

$FR = 25

function MakePngSeg($src, $dur, $ovName, $segNum) {
  $outFile = "$TMP\seg$segNum.mp4"
  # Simple approach: scale PNG to 1080x1920, then zoompan for push-in effect
  $vfilter = "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,zoompan=z='min(zoom+0.0004,1.06)':d=$([int]($dur*$FR)):s=1080x1920,fps=$FR"
  
  if ($ovName -and (Test-Path "$O\$ovName.png")) {
    & ffmpeg -y -loop 1 -t $dur -i $src -loop 1 -t $dur -i "$O\$ovName.png" `
      -filter_complex "[0:v]$vfilter[base];[1:v]scale=1080:1920[ov];[base][ov]overlay=0:0:shortest=1" `
      -c:v libx264 -pix_fmt yuv420p -r $FR "$outFile" 2>$null
  } else {
    & ffmpeg -y -loop 1 -t $dur -i $src `
      -filter_complex "[0:v]$vfilter" `
      -c:v libx264 -pix_fmt yuv420p -r $FR "$outFile" 2>$null
  }
  if ((Get-Item "$outFile").Length -gt 1000) {
    Write-Output "  seg$segNum ✓"
  } else {
    Write-Output "  seg$segNum ✗"
  }
}

function MakeMp4Seg($src, $dur, $ovName, $segNum) {
  $outFile = "$TMP\seg$segNum.mp4"
  $vfilter = "scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=$FR"
  
  if ($ovName -and (Test-Path "$O\$ovName.png")) {
    & ffmpeg -y -i $src -loop 1 -t $dur -i "$O\$ovName.png" `
      -filter_complex "[0:v]$vfilter[base];[1:v]scale=1080:1920[ov];[base][ov]overlay=0:0:shortest=1" `
      -c:v libx264 -pix_fmt yuv420p -r $FR -t $dur "$outFile" 2>$null
  } else {
    & ffmpeg -y -i $src -filter_complex "[0:v]$vfilter" `
      -c:v libx264 -pix_fmt yuv420p -r $FR -t $dur "$outFile" 2>$null
  }
  if ((Get-Item "$outFile").Length -gt 1000) {
    Write-Output "  seg$segNum ✓"
  } else {
    Write-Output "  seg$segNum ✗"
  }
}

# Remove old segs
Remove-Item "$TMP\seg*.mp4" -Force -ErrorAction SilentlyContinue
Remove-Item "$TMP\concat.txt" -Force -ErrorAction SilentlyContinue

Write-Output "Building 12 segments..."
$files = @()
$total = 0

# seg0
$files += MakeMp4Seg "$M\campania-fase1\v1-clip-fiesta-detenida.mp4" 3.5 $null 0; $total += 3.5
# seg1
$files += MakeMp4Seg "$M\campania-fase1\v1-clip-fiesta-detenida.mp4" 3.5 $null 1; $total += 3.5
# seg2
$files += MakeMp4Seg "$M\clip-01-kiosco.mp4" 4.0 $null 2; $total += 4
# seg3
$files += MakePngSeg "$S\screen-01-intro.png" 5.0 't04' 3; $total += 5
# seg4
$files += MakePngSeg "$S\screen-03-ruleta.png" 5.0 't05' 4; $total += 5
# seg5
$files += MakeMp4Seg "$M\clip-02-flash.mp4" 5.0 $null 5; $total += 5
# seg6
$files += MakePngSeg "$S\screen-06-preview.png" 5.0 't08' 6; $total += 5
# seg7
$files += MakePngSeg "$S\screen-07-qr.png" 5.0 't09' 7; $total += 5
# seg8
$files += MakePngSeg "$S\screen-08-diploma.png" 5.0 't10' 8; $total += 5
# seg9
$files += MakeMp4Seg "$M\campania-fase1\v3a-clip-sala-carreras.mp4" 3.0 $null 9; $total += 3
# seg10
$files += MakeMp4Seg "$M\campania-fase1\v3b-clip-sala-bluey.mp4" 3.0 $null 10; $total += 3
# seg11
$files += MakeMp4Seg "$M\clip-03-endcard.mp4" 8.0 $null 11; $total += 8

Write-Output "Total: ${total}s"

# Concat
$concatList = "$TMP\concat.txt"
$files | ForEach-Object { "file '$_'" } | Out-File -FilePath $concatList -Encoding ascii

Write-Output "Concatenating..."
& ffmpeg -y -f concat -safe 0 -i "$concatList" -c:v libx264 -pix_fmt yuv420p -movflags +faststart -r $FR "$OUT" 2>$null

if ((Test-Path "$OUT") -and (Get-Item "$OUT").Length -gt 10000) {
  Write-Output "✓ Video creado"
  & ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt,r_frame_rate -show_entries format=duration -of default=noprint_wrappers=1 "$OUT"
  $mb = [math]::Round((Get-Item "$OUT").Length / 1MB, 1)
  Write-Output "Tamaño: ${mb} MB"
} else {
  Write-Output "ERROR: concat failed"
}
