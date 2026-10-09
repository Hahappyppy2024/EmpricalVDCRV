FROM node:22-alpine

WORKDIR /app

RUN apk add --no-cache python3 make g++ sqlite

COPY package.json ./
RUN npm install --omit=dev

COPY src ./src
COPY public ./public
COPY .env.example ./.env.example

ENV NODE_ENV=production \
    PORT=3000 \
    DB_PATH=/app/data/cms.sqlite \
    UPLOAD_DIR=/app/uploads

RUN mkdir -p /app/data /app/uploads

EXPOSE 3000

CMD ["sh", "-c", "node src/db/reset.js && node src/server.js"]
