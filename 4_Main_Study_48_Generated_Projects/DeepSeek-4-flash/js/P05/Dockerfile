FROM node:22-slim

WORKDIR /app

# Install dependencies first for better layer caching
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Copy application source
COPY src ./src
COPY scripts ./scripts
COPY .env.example ./

# Create data directories
RUN mkdir -p /app/data/uploads /app/data/exports

ENV NODE_ENV=production
ENV PORT=3000
ENV HOST=0.0.0.0
ENV DB_PATH=./data/cms.db
ENV UPLOAD_DIR=./data/uploads
ENV EXPORT_DIR=./data/exports

EXPOSE 3000

CMD ["node", "src/server.js"]
