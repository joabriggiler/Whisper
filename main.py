from fastapi import FastAPI, UploadFile, Form
from fastapi.responses import JSONResponse
from fastapi.middleware.cors import CORSMiddleware
import whisper
import torch
import uvicorn
import os
import tempfile
import subprocess
import traceback

app = FastAPI()

# 🔹 Agregar justo después de crear app
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],       # o lista específica de dominios
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

device = "cuda" if torch.cuda.is_available() else "cpu"
print(f"Usando dispositivo: {device}")
print("torch:", torch.__version__, "cuda available:", torch.cuda.is_available(), "cuda ver:", torch.version.cuda)

# Cargar modelo una vez (mantener en CPU/GPU según disponibilidad)
model = whisper.load_model("medium").to(device)

@app.post("/transcribir")
async def transcribir(audio: UploadFile, idioma: str = Form("es")):
    try:
        # Guardar entrada
        with tempfile.NamedTemporaryFile(delete=False, suffix=".webm") as temp_in:
            temp_in.write(await audio.read())
            temp_in_path = temp_in.name

        # Convertir a wav (verificar returncode)
        temp_wav_path = temp_in_path + ".wav"
        proc = subprocess.run([
            "ffmpeg", "-y", "-i", temp_in_path,
            "-ar", "16000", "-ac", "1", temp_wav_path
        ], stdout=subprocess.PIPE, stderr=subprocess.PIPE)

        if proc.returncode != 0 or not os.path.exists(temp_wav_path) or os.path.getsize(temp_wav_path) < 1000:
            err = proc.stderr.decode(errors="ignore")
            raise RuntimeError(f"ffmpeg falló o archivo inválido. rc={proc.returncode} stderr={err[:200]}")

        # Intento de transcripción. Desactivar fp16 para evitar NaN en GPU.
        try:
            result = model.transcribe(temp_wav_path, language=idioma, fp16=False, temperature=0)
        except Exception as e_gpu:
            # si falla en GPU, intentar en CPU (fallback)
            print("Transcribe falló en GPU, reintentando en CPU:", repr(e_gpu))
            traceback.print_exc()
            torch.cuda.empty_cache()
            model_cpu = model.to("cpu")
            result = model_cpu.transcribe(temp_wav_path, language=idioma, fp16=False, temperature=0)
            model.to(device)  # si quieres devolver modelo al device original

        # Limpieza
        os.remove(temp_in_path)
        os.remove(temp_wav_path)

        return JSONResponse(content={"texto": result["text"]})

    except Exception as e:
        traceback.print_exc()
        # devolver error claro al frontend
        return JSONResponse(content={"error": str(e)})

if __name__ == "__main__":
    uvicorn.run(app, host="127.0.0.1", port=8000)