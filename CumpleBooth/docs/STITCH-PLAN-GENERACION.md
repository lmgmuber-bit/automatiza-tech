# Stitch (tropical) — Plan de generación para ejecutar en Higgsfield o delegar a Claude

## Estado: directorios creados
- `public/themes/tropical/` ✅
- `public/themes/tropical/roulette/` ✅
- themes.json ya tiene la entrada `tropical` (línea 580-643) con 6 personajes camuflados

## FASE 1: 6 personajes individuales (JPG, pose neutra, fondo claro)

Generar con `nano_banana_2` (1.5 cr c/u, 9 cr total). Estilo: "high-end 3D collectible vinyl toy, soft studio lighting, Pixar-quality, full body, centered, plain light cream background, no text no logos".

### 1. alienazul.jpg → Stitch
```
A small blue koala-like alien creature, big floppy ears, large black shiny eyes, four arms with retracted claws, short fuzzy blue fur all over, mischievous grin showing small sharp teeth, two small antennae, standing in a friendly welcoming pose. High-end 3D collectible vinyl toy style, soft studio lighting, Pixar-quality, full body centered on plain cream background.
```

### 2. alienrosa.jpg → Angel
```
A small pink koala-like alien creature, big floppy ears, large black shiny eyes, four arms with retracted claws, short fuzzy pink fur all over, sweet gentle smile, two small antennae with a tiny flower decoration, feminine pose. High-end 3D collectible vinyl toy style, soft studio lighting, Pixar-quality, full body centered on plain cream background.
```

### 3. hawaiana.jpg → Lilo
```
A small cheerful girl around 6 years old, tan skin, long wavy black hair, wearing a red Hawaiian dress with white leaf pattern, barefoot, big friendly smile, holding one hand up in a shaka sign. High-end 3D collectible vinyl toy style, soft studio lighting, Pixar-quality, full body centered on plain cream background.
```

### 4. surfista.jpg → David/Nani's friend
```
A tall friendly teen boy, tan skin, short blonde wavy hair, muscular build, wearing a blue Hawaiian shirt open over a white tank top, khaki shorts, flip-flops, relaxed surfer pose, warm smile. High-end 3D collectible vinyl toy style, soft studio lighting, Pixar-quality, full body centered on plain cream background.
```

### 5. tortugamar.jpg → Jumba (manteniendo camuflaje)
```
A large round alien scientist, four eyes with small glasses, dark blue-gray skin, short stubby legs, three-fingered hands, wearing a white lab coat, friendly gruff expression. High-end 3D collectible vinyl toy style, soft studio lighting, Pixar-quality, full body centered on plain cream background.
```

### 6. loro.jpg → Pleakley (manteniendo camuflaje)
```
A tall thin alien creature with one large eye in the center of the face, pale green skin, wearing a bright Hawaiian shirt and a straw hat, long thin arms, friendly nervous smile. High-end 3D collectible vinyl toy style, soft studio lighting, Pixar-quality, full body centered on plain cream background.
```

## FASE 2: Recortes PNG (remove background)
Subir cada JPG a Higgsfield → `remove_background` (media_type: image). Nombrar: alienazul-cut.png, alienrosa-cut.png, hawaiana-cut.png, etc.

## FASE 3: 6 saludos individuales (MP4, 5s, mudo)
Usar `cinematic_studio_video_v2` (5 cr c/u, 30 cr total). start_image = cada JPG de la fase 1. Prompt genérico:
```
The character waves hello to the camera with a warm friendly smile, gentle bounce, tropical breeze effect, simple clean background, 5 seconds, no text.
```

## FASE 4: Fondos

### fondo-banner.jpg (1080×1920, 9:16, sin texto)
```
Tropical Hawaiian beach at golden sunset, palm trees silhouettes, gentle ocean waves, colorful hibiscus flowers in the foreground, a clean empty wooden sign board in the center for text overlay, warm golden light, photorealistic, no text no logos, 9:16 vertical.
```

### fondo-sala.jpg (9:16)
```
Tropical party decoration, bamboo arch with colorful flowers and palm leaves, tiki torches lit, wooden floor, empty golden ornate photo frame in the center, festive Hawaiian atmosphere, photorealistic, warm sunset lighting, no text no logos, 9:16 vertical.
```

## FASE 5: Música y grupo

### musica-fondo.mp3
Usar `generate_music` con modelo `mureka-v9` (60 cr, instrumental):
```
Upbeat Hawaiian tropical instrumental music, ukulele, steel drums, happy party vibe, 60 seconds loop, children's birthday party atmosphere, no vocals.
```

### grupo-personajes.png
Usar los 6 JPG como input_images con `nano_banana_2`:
```
All 6 characters standing together as a group photo on a tropical beach at sunset, cheerful poses, family photo style, palm trees background, warm lighting. High-end 3D collectible vinyl toy style, Pixar-quality.
```

## FASE 6: Ruleta
### roulette/roulette-background-v1.png (1:1)
```
Tropical Hawaiian pattern background, palm leaves, hibiscus flowers, bamboo texture, vibrant turquoise and coral colors, 1:1 square format, seamless tileable, clean enough for text overlay on top, no text no logos.
```

---

## Costo total estimado
| Fase | Items | Créditos c/u | Total |
|------|-------|-------------|-------|
| Personajes JPG | 6 | 1.5 cr (nano_banana_2) | 9 cr |
| Recortes PNG | 6 | gratuito (remove_background) | 0 cr |
| Saludos MP4 | 6 | 5 cr (cinematic_studio_v2) | 30 cr |
| Fondos JPG | 2 | 1.5 cr | 3 cr |
| Música | 1 | 60 cr (mureka-v9) | 60 cr |
| Grupo PNG | 1 | 1.5 cr | 1.5 cr |
| Ruleta PNG | 1 | 1.5 cr | 1.5 cr |
| **TOTAL** | | | **~105 cr** |

## Orden de ejecución
1. Primero TODOS los JPG (personajes + fondos + grupo + ruleta) → revisar y aprobar visualmente
2. Luego los recortes PNG (remove_background)
3. Luego los saludos MP4 (usando los JPG aprobados)
4. Finalmente la música
5. Copiar todo a `public/themes/tropical/`
6. Verificar en admin: `?view=tema&slug=tropical` debe mostrar "Lista"
7. `npm run build` + tests

---

## Si BudgetPixel vuelve a funcionar:
Modelos equivalentes BudgetPixel → Higgsfield:
- `nano_banana_2` (65 cr) ≈ Higgsfield nano_banana_2 (1.5 cr)
- `seedance-2.0` video ≈ Higgsfield cinematic_studio_video_v2 (5 cr)
- `mureka-v9` música (60 cr) ≈ Higgsfield generate_music

Usarían ~430 créditos BudgetPixel vs ~105 créditos Higgsfield. Higgsfield es más barato.
