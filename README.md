# Audio Compressor API (RunPod Serverless)

Microservicio en Node.js para comprimir audio usando ffmpeg.  
Convierte cualquier archivo a **OGG/Opus (32 kbps, mono, 16 kHz)**.  

Se ejecuta como **RunPod Serverless Endpoint**, por lo que no necesita estar corriendo todo el tiempo:  
solo consume créditos mientras procesa.

---

## 🚀 Uso

### Request
Hacés un POST a la API de RunPod:

```bash
curl -X POST https://api.runpod.ai/v2/<ENDPOINT_ID>/run \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -d '{
    "input": {
      "audio_base64": "<AUDIO_EN_BASE64>"
    }
  }'
