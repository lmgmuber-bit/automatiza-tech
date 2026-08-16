FROM mcr.microsoft.com/playwright:v1.47.0-jammy
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev
COPY src ./src
RUN mkdir -p /app/public
ENV PORT=3000
ENV PUBLIC_DIR=/app/public
EXPOSE 3000
CMD ["node", "src/index.js"]
