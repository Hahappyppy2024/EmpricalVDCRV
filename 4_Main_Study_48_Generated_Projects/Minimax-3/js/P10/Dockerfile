FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev

FROM node:22-alpine AS runtime
ENV NODE_ENV=production \
    PORT=3000 \
    HOST=0.0.0.0
WORKDIR /app
RUN apk add --no-cache curl
COPY --from=deps /app/node_modules ./node_modules
COPY package.json ./
COPY src ./src
COPY public ./public
COPY scripts ./scripts
COPY .env.example ./.env.example
RUN mkdir -p data data/uploads
EXPOSE 3000
HEALTHCHECK --interval=20s --timeout=4s --start-period=15s --retries=5 \
    CMD curl -fsS http://127.0.0.1:3000/healthz || exit 1
CMD ["node", "scripts/start.js"]
