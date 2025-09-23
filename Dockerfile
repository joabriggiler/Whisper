FROM node:18

# Instalar ffmpeg
RUN apt-get update && apt-get install -y ffmpeg && rm -rf /var/lib/apt/lists/*

# Crear directorio de la app
WORKDIR /app

# Copiar archivos
COPY package.json .
RUN npm install
COPY . .

CMD ["node", "-e", "require('./index.js')"]
