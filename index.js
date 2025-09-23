const express = require("express");
const multer = require("multer");
const fs = require("fs");
const ffmpeg = require("fluent-ffmpeg");
const path = require("path");

const app = express();
const upload = multer({ dest: "/tmp" });

app.post("/compress", upload.single("audio"), async (req, res) => {
  if (!req.file) {
    return res.status(400).json({ error: "No se envió archivo" });
  }

  const inputPath = req.file.path;
  const outputPath = inputPath + ".ogg";

  ffmpeg(inputPath)
    .audioCodec("libopus")
    .audioChannels(1)
    .audioFrequency(16000)
    .audioBitrate("32k")
    .on("end", () => {
      const result = fs.readFileSync(outputPath);
      const base64 = result.toString("base64");

      fs.unlinkSync(inputPath);
      fs.unlinkSync(outputPath);

      res.json({
        ok: true,
        size_kb: (result.length / 1024).toFixed(1),
        audio_base64: base64
      });
    })
    .on("error", (err) => {
      console.error("Error en ffmpeg:", err);
      res.status(500).json({ ok: false, error: err.message });
    })
    .save(outputPath);
});

app.listen(3000, () => {
  console.log("🚀 Servidor corriendo en puerto 3000");
});
