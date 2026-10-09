FROM node:22-alpine
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev
COPY src ./src
COPY public ./public
COPY scripts ./scripts
ENV NODE_ENV=production
ENV PORT=3000
EXPOSE 3000
RUN node src/db/init.js && node src/db/seed.js
CMD ["node", "src/server.js"]