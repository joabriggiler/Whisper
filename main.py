from fastapi import FastAPI
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import whisper
import torch
import uvicorn
import os
import tempfile
import subprocess
import traceback
import base64

app = FastAPI()

# Middleware CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

device = "cuda" if torch.cuda.is_available() else "cpu"
print(f"Usando dispositivo: {device}")
print("torch:", torch.__version__, "cuda available:", torch.cuda.is_available(), "cuda ver:", torch.version.cuda)

# Cargar modelo una vez
model = whisper.load_model("medium").to(device)

@app.post("/transcribir")
async def transcribir(request: dict):
    try:
        # Extraer datos del JSON
        data = request.get("input", {})
        audio_b64 = data.get("audio_base64")
        idioma = data.get("idioma", "es")

        if not audio_b64:
            return JSONResponse(content={"error": "audio_base64 es requerido"}, status_code=400)

        # Guardar archivo temporal desde base64
        audio_bytes = base64.b64decode(audio_b64)
        with tempfile.NamedTemporaryFile(delete=False, suffix=".webm") as temp_in:
            temp_in.write(audio_bytes)
            temp_in_path = temp_in.name

        # Convertir a wav
        temp_wav_path = temp_in_path + ".wav"
        proc = subprocess.run([
            "ffmpeg", "-y", "-i", temp_in_path,
            "-ar", "16000", "-ac", "1", temp_wav_path
        ], stdout=subprocess.PIPE, stderr=subprocess.PIPE)

        if proc.returncode != 0 or not os.path.exists(temp_wav_path) or os.path.getsize(temp_wav_path) < 1000:
            err = proc.stderr.decode(errors="ignore")
            raise RuntimeError(f"ffmpeg falló o archivo inválido. rc={proc.returncode} stderr={err[:200]}")

        # Transcribir
        try:
            result = model.transcribe(temp_wav_path, language=idioma, fp16=False, temperature=0)
        except Exception as e_gpu:
            print("Transcribe falló en GPU, reintentando en CPU:", repr(e_gpu))
            traceback.print_exc()
            torch.cuda.empty_cache()
            model_cpu = model.to("cpu")
            result = model_cpu.transcribe(temp_wav_path, language=idioma, fp16=False, temperature=0)
            model.to(device)

        # Limpieza
        os.remove(temp_in_path)
        os.remove(temp_wav_path)

        return JSONResponse(content={"texto": result["text"]})

    except Exception as e:
        traceback.print_exc()
        return JSONResponse(content={"error": str(e)})

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)