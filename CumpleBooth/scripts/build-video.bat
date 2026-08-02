@echo off
setlocal enabledelayedexpansion
set D=C:\wamp64\www\automatiza-tech\CumpleBooth\design
set M=%D%\video
set S=%D%\screens
set O=%D%\explicativo\overlays
set OUT=%D%\explicativo\video-explicativo.mp4
set TMP=%TEMP%\cc-video-segs
set FR=25

if not exist "%TMP%" mkdir "%TMP%"
del /q "%TMP%\seg*.mp4" 2>nul

echo Building segments...

:: seg0: clip-01 mp4 (3.5s)
ffmpeg -y -i "%M%\campania-fase1\v1-clip-fiesta-detenida.mp4" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=%FR%" -c:v libx264 -pix_fmt yuv420p -r %FR% -t 3.5 "%TMP%\seg0.mp4" 2>nul
echo seg0 done

:: seg1: same clip (3.5s) - "20 niños"
ffmpeg -y -i "%M%\campania-fase1\v1-clip-fiesta-detenida.mp4" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=%FR%" -c:v libx264 -pix_fmt yuv420p -r %FR% -t 3.5 "%TMP%\seg1.mp4" 2>nul
echo seg1 done

:: seg2: clip-01-kiosco (4s)
ffmpeg -y -i "%M%\clip-01-kiosco.mp4" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=%FR%" -c:v libx264 -pix_fmt yuv420p -r %FR% -t 4 "%TMP%\seg2.mp4" 2>nul
echo seg2 done

:: seg3: screen-01-intro zoom + t04 "Cada invitado toca su nombre"
ffmpeg -y -loop 1 -t 5 -i "%S%\screen-01-intro.png" -loop 1 -t 5 -i "%O%\t04.png" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,zoompan=z='min(zoom+0.0004,1.06)':d=125:s=1080x1920,fps=%FR%[base];[1:v]scale=1080:1920[ov];[base][ov]overlay=0:0:shortest=1" -c:v libx264 -pix_fmt yuv420p -r %FR% "%TMP%\seg3.mp4" 2>nul
echo seg3 done

:: seg4: screen-03-ruleta zoom + t05
ffmpeg -y -loop 1 -t 5 -i "%S%\screen-03-ruleta.png" -loop 1 -t 5 -i "%O%\t05.png" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,zoompan=z='min(zoom+0.0004,1.06)':d=125:s=1080x1920,fps=%FR%[base];[1:v]scale=1080:1920[ov];[base][ov]overlay=0:0:shortest=1" -c:v libx264 -pix_fmt yuv420p -r %FR% "%TMP%\seg4.mp4" 2>nul
echo seg4 done

:: seg5: clip-02-flash (5s)
ffmpeg -y -i "%M%\clip-02-flash.mp4" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=%FR%" -c:v libx264 -pix_fmt yuv420p -r %FR% -t 5 "%TMP%\seg5.mp4" 2>nul
echo seg5 done

:: seg6: screen-06-preview zoom + t08
ffmpeg -y -loop 1 -t 5 -i "%S%\screen-06-preview.png" -loop 1 -t 5 -i "%O%\t08.png" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,zoompan=z='min(zoom+0.0004,1.06)':d=125:s=1080x1920,fps=%FR%[base];[1:v]scale=1080:1920[ov];[base][ov]overlay=0:0:shortest=1" -c:v libx264 -pix_fmt yuv420p -r %FR% "%TMP%\seg6.mp4" 2>nul
echo seg6 done

:: seg7: screen-07-qr zoom + t09
ffmpeg -y -loop 1 -t 5 -i "%S%\screen-07-qr.png" -loop 1 -t 5 -i "%O%\t09.png" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,zoompan=z='min(zoom+0.0004,1.06)':d=125:s=1080x1920,fps=%FR%[base];[1:v]scale=1080:1920[ov];[base][ov]overlay=0:0:shortest=1" -c:v libx264 -pix_fmt yuv420p -r %FR% "%TMP%\seg7.mp4" 2>nul
echo seg7 done

:: seg8: screen-08-diploma zoom + t10
ffmpeg -y -loop 1 -t 5 -i "%S%\screen-08-diploma.png" -loop 1 -t 5 -i "%O%\t10.png" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,zoompan=z='min(zoom+0.0004,1.06)':d=125:s=1080x1920,fps=%FR%[base];[1:v]scale=1080:1920[ov];[base][ov]overlay=0:0:shortest=1" -c:v libx264 -pix_fmt yuv420p -r %FR% "%TMP%\seg8.mp4" 2>nul
echo seg8 done

:: seg9: carreras clip (3s)
ffmpeg -y -i "%M%\campania-fase1\v3a-clip-sala-carreras.mp4" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=%FR%" -c:v libx264 -pix_fmt yuv420p -r %FR% -t 3 "%TMP%\seg9.mp4" 2>nul
echo seg9 done

:: seg10: bluey clip (3s)
ffmpeg -y -i "%M%\campania-fase1\v3b-clip-sala-bluey.mp4" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=%FR%" -c:v libx264 -pix_fmt yuv420p -r %FR% -t 3 "%TMP%\seg10.mp4" 2>nul
echo seg10 done

:: seg11: endcard (8s)
ffmpeg -y -i "%M%\clip-03-endcard.mp4" -filter_complex "[0:v]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,fps=%FR%" -c:v libx264 -pix_fmt yuv420p -r %FR% -t 8 "%TMP%\seg11.mp4" 2>nul
echo seg11 done

echo All segments built. Concatenating...

:: Write concat list
(
echo file '%TMP%\seg0.mp4'
echo file '%TMP%\seg1.mp4'
echo file '%TMP%\seg2.mp4'
echo file '%TMP%\seg3.mp4'
echo file '%TMP%\seg4.mp4'
echo file '%TMP%\seg5.mp4'
echo file '%TMP%\seg6.mp4'
echo file '%TMP%\seg7.mp4'
echo file '%TMP%\seg8.mp4'
echo file '%TMP%\seg9.mp4'
echo file '%TMP%\seg10.mp4'
echo file '%TMP%\seg11.mp4'
) > "%TMP%\concat.txt"

ffmpeg -y -f concat -safe 0 -i "%TMP%\concat.txt" -c:v libx264 -pix_fmt yuv420p -movflags +faststart -r %FR% "%OUT%" 2>nul

if exist "%OUT%" (
    echo Video created!
    ffprobe -v error -show_entries stream=codec_name,width,height,pix_fmt,r_frame_rate -show_entries format=duration -of default=noprint_wrappers=1 "%OUT%"
) else (
    echo ERROR: concat failed
)
