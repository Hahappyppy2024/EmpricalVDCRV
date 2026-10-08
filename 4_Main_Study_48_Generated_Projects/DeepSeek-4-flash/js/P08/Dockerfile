FROM node:22-alpine

WORKDIR /app

COPY package*.json ./
RUN npm ci --omit=dev

COPY . .

RUN mkdir -p /app/data/uploads

ENV PORT=3000
ENV HOST=0.0.0.0
ENV DB_PATH=/app/data/app.db
ENV DATA_DIR=/app/data
ENV UPLOAD_DIR=/app/data/uploads
ENV NODE_ENV=production

EXPOSE 3000

CMD ["sh", "-c", "node scripts/db-reset.js && node src/server.js"]
