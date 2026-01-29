FROM node:20-slim

RUN apt-get update && apt-get install -y \
    curl \
    && rm -rf /var/lib/apt/lists/*

ENV DOCKER_ENV=true
ENV WA_HOST=0.0.0.0

WORKDIR /app

COPY package*.json ./

RUN npm install --production

COPY index.js ./

RUN mkdir -p .baileys_auth .api_secret_dir

EXPOSE 3001

CMD ["node", "index.js"]
