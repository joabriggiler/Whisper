FROM node:18

# Instalar ffmpeg
RUN apt-get update && apt-get install -y ffmpeg && rm -rf /var/lib/apt/lists/*

# Crear directorio de la app
WORKDIR /app

# Copiar package.json e instalar dependencias
COPY package.json .
RUN npm install

# Copiar el resto del código
COPY . .

EXPOSE 3000
CMD ["npm", "start"]
