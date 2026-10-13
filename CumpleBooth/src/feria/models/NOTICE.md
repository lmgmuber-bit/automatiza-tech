# Modelo de segmentación

MediaPipe Selfie Segmenter, float16, versión 1. Se ejecuta en la tablet; no envía imágenes a Google.
Fuente oficial: https://storage.googleapis.com/mediapipe-models/image_segmenter/selfie_segmenter/float16/1/selfie_segmenter.tflite
SHA-256: 191ac9529ae506ee0beefa6b2c945a172dab9d07d1e802a290a4e4038226658b
Guía: https://developers.google.com/edge/mediapipe/solutions/vision/image_segmenter/web_js
Ficha: https://storage.googleapis.com/mediapipe-assets/Model%20Card%20MediaPipe%20Selfie%20Segmentation.pdf
Licencia de MediaPipe: Apache-2.0 (https://github.com/google-ai-edge/mediapipe/blob/master/LICENSE).

La fuente binaria vive en src/feria/models para respetar el reparto de archivos del ticket. Vite la publica en vendor/mediapipe/selfie_segmenter.tflite, junto al WASM existente. El adaptador del worker copia el SDK instalado de @mediapipe/tasks-vision, sin cambiar la versión fijada del proyecto.
