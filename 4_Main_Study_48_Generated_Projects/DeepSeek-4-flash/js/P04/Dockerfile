FROM node:22-slim AS builder
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --omit=dev

FROM node:22-slim
WORKDIR /app
ENV NODE_ENV=production
ENV PORT=3000
ENV HOST=0.0.0.0
ENV DB_PATH=/app/data/hotel.db
COPY --from=builder /app/node_modules ./node_modules
COPY . .
RUN mkdir -p /app/data
EXPOSE 3000
CMD ["node", "src/server.js"]
