// index.js para RunPod Serverless
const fs = require("fs");
const ffmpeg = require("fluent-ffmpeg");

// RunPod espera que exportemos una función handler
module.exports = async (event) => {
  try {
    // Recibir audio base64 desde input
    const audioBase64 = event.input.audio_base64;
    if (!audioBase64) {
      return { output: { ok: false, error: "No se recibió audio_base64" } };
    }

    // Guardar en archivo temporal
    const inputPath = "/tmp/input.wav";
    const outputPath = "/tmp/output.ogg";
    fs.writeFileSync(inputPath, Buffer.from(audioBase64, "base64"));

    // Ejecutar ffmpeg para recomprimir
    await new Promise((resolve, reject) => {
      ffmpeg(inputPath)
        .audioCodec("libopus")
        .audioChannels(1)
        .audioFrequency(16000)
        .audioBitrate("32k")
        .on("end", resolve)
        .on("error", reject)
        .save(outputPath);
    });

    // Leer archivo resultante
    const compressed = fs.readFileSync(outputPath);
    const base64Out = compressed.toString("base64");

    // Limpiar tmp
    fs.unlinkSync(inputPath);
    fs.unlinkSync(outputPath);

    return {
      output: {
        ok: true,
        audio_base64: base64Out,
        size_kb: Math.round(compressed.length / 1024),
      },
    };
  } catch (err) {
    return { output: { ok: false, error: err.message } };
  }
};