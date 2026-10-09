FROM node:22-bookworm-slim

ENV NODE_ENV=production
ENV PORT=3000
ENV DB_PATH=/app/data/app.sqlite
ENV RECEIPT_STORAGE_DIR=/app/storage/receipts
ENV EXPORT_STORAGE_DIR=/app/storage/exports

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --omit=dev --no-audit --no-fund

COPY src ./src
COPY public ./public

RUN mkdir -p /app/data /app/storage/receipts /app/storage/exports

EXPOSE 3000

CMD ["sh", "-c", "node src/db/reset.js && node src/server.js"]
