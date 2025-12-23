FROM node:20-alpine

WORKDIR /app

RUN apk add --no-cache net-snmp net-snmp-tools

COPY package*.json ./
RUN npm install --production

COPY . .

EXPOSE 3001

CMD ["node", "index.js"]
