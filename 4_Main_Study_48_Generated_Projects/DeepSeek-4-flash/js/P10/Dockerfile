FROM node:22-slim

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

ENV NODE_ENV=production
ENV PORT=3000
ENV DATABASE_PATH=./data/app.db
ENV UPLOAD_DIR=./data/uploads

EXPOSE 3000

# Seeds the database on first start (idempotent) and launches the server.
CMD ["sh", "-c", "npm run db:seed && npm start"]
