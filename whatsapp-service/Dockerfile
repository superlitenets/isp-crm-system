FROM node:20-slim

RUN apt-get update && apt-get install -y \
    chromium \
    curl \
    && rm -rf /var/lib/apt/lists/*

ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
ENV DOCKER_ENV=true
ENV WA_HOST=0.0.0.0

WORKDIR /app

COPY package*.json ./

RUN npm install --production

COPY index.js ./

RUN mkdir -p .wwebjs_auth .api_secret_dir

EXPOSE 3001

CMD ["node", "index.js"]
