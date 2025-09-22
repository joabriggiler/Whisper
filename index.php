<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <!-- Texto editable principal -->
    <textarea id="enfermedad_actual" name="enfermedad_actual" rows="2" class="textarea-consulta"></textarea>

    <!-- ── Controles de grabación ── -->
    <div class="grabacion-row" style="display:flex;align-items:center;gap:8px;margin-top:6px;">
        <button type="button" id="btnGrabar"  class="btn btn-sm" onclick="startRecording()">
            🎤 Escuchar</button>

        <button type="button" id="btnDetener" class="btn btn-secondary btn-sm" onclick="stopRecording()" style="display:none;">
            ■ Detener</button>

        <span id="recordingIndicator" style="display:none;font-weight:bold;">● Escuchando…</span>
        <span id="recordingTimer" style="min-width:48px;">00:00</span>
        <span id="spinner" style="display:none;">⏳ Transcribiendo…</span>

        <button type="button" id="btnLimpiar" class="btn btn-secondary btn-sm" onclick="document.getElementById('enfermedad_actual').value='';document.getElementById('textoTranscripcionEnfAct').value='';">
            ✖️ Limpiar</button>
    </div>

    <!-- 1. Grabación Motivo / Enfermedad Actual -->
    <script>
    /* ==== variables globales ==== */
    let mediaRecorder,
        timerInterval,
        segmentTimeout,
        stream,
        startTime;
    let grabandoMotivo = false;   // controla si seguimos resegmentando
    const ind  = document.getElementById('recordingIndicator');
    const btnG = document.getElementById('btnGrabar');
    const btnS = document.getElementById('btnDetener');
    const tim  = document.getElementById('recordingTimer');

    // 🔹 AGREGADO: parámetro de solape
    const OVERLAP_MS = 500; // 1 segundo

    function startRecording() {
        grabandoMotivo = true;

        navigator.mediaDevices.getUserMedia({ audio:true })
        .then(str => {
            stream  = str;
            startTime = Date.now();
            tim.textContent = '00:00';
            ind.style.display = 'inline';
            btnG.style.display = 'none';
            btnS.style.display = 'inline';

            const tipos = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/ogg',
                'audio/mp4;codecs=mp4a.40.2',
                'audio/mp4'
            ];

            const iniciarSegmento = () => {
                const mime = tipos.find(t => MediaRecorder.isTypeSupported(t)) || '';
                const nuevo = t =>
                t ? new MediaRecorder(stream, { mimeType:t }) : new MediaRecorder(stream);

                let rec;
                try        { rec = nuevo(mime); rec.start(); }
                catch(e)   {
                console.warn('Fallo con', mime, e);
                try      { rec = nuevo(''); rec.start(); }
                catch(e2){ console.error('MediaRecorder no soportado', e2);
                            alert('El navegador no soporta la grabación de audio.');
                            stopRecording(); return; }
                }

                mediaRecorder = rec;

                rec.ondataavailable = async ev => {
                const blob = new Blob([ev.data], { type: rec.mimeType || ev.data.type });
                if (blob.size > 1000) await transcribirBlob(blob);
                };

                rec.onstop = () => {
                if (rec._nextStartId) clearTimeout(rec._nextStartId);
                if (grabandoMotivo && !rec._nextFired) segmentTimeout = setTimeout(iniciarSegmento, 400);
                };

                rec._nextStartId = setTimeout(() => {
                if (!grabandoMotivo) return;
                rec._nextFired = true;
                iniciarSegmento();
                }, Math.max(0, 30000 - OVERLAP_MS));

                setTimeout(() => rec.state==='recording' && rec.stop(), 30000);
            };

            iniciarSegmento();

            timerInterval = setInterval(() => {
                const t = Date.now() - startTime;
                const min = String(Math.floor(t / 60000)).padStart(2, '0');
                const sec = String(Math.floor((t % 60000) / 1000)).padStart(2, '0');
                tim.textContent = `${min}:${sec}`;
            }, 1000);
        })
        .catch(err => { console.error(err); alert('No se pudo acceder al micrófono'); });
    }

    function stopRecording() {
        grabandoMotivo = false;
        clearInterval(timerInterval);
        clearTimeout(segmentTimeout);
        if (mediaRecorder?.state==='recording') mediaRecorder.stop();
        stream?.getTracks().forEach(t => t.stop());
        ind.style.display = 'none';
        btnG.style.display = 'inline';
        btnS.style.display = 'none';
        tim.textContent = '00:00';
    }
    
    /* ─── TRANSCRIPCIÓN DE AUDIO PARA ENFERMEDAD ACTUAL ─── */
    async function transcribirBlob(blob){
        try{
            const fd = new FormData();
            fd.append('audio', blob, `chunk_${Date.now()}.webm`);

            const resp = await fetch('http://127.0.0.1:8000/transcribir', {
                method: 'POST',
                body: fd
            });

            const data = await resp.json();

            if (data.texto){
                const texto = data.texto.trim();
                console.log("Transcripción:", texto);
            }
        }
        catch(err){
            console.error('Error transcripción Enfermedad Actual:', err);
        }
    }

    /* ─── NUEVO: carga de audio manual ─── */
    async function cargarYTranscribirArchivo(){
        const input = document.getElementById('fileAudio');
        if (input.files.length === 0){
            alert("Selecciona un archivo de audio primero");
            return;
        }
        const archivo = input.files[0];
        await transcribirBlob(archivo);
    }
    </script>

    <!-- ── NUEVO APARTADO: Carga de audio manual ── -->
    <div style="margin-top:12px;">
        <input type="file" id="fileAudio" accept="audio/*">
        <button type="button" onclick="cargarYTranscribirArchivo()">📂 Transcribir Audio Cargado</button>
    </div>
</body>
</html>