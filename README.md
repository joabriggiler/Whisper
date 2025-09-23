# Audio Compressor API

Microservicio simple en Node.js para comprimir audio usando ffmpeg.
Convierte cualquier archivo a OGG/Opus (32 kbps, mono, 16 kHz).

### Ejemplo de uso

```bash
curl -X POST http://localhost:3000/compress \
  -F "audio=@mi_audio.wav"
