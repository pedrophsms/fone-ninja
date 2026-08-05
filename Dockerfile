# Production image for the Fone Ninja SPA.
#
# Two stages: a Node build producing the static bundle, then nginx serving it
# with a reverse proxy for /api -> backend service. Same-origin requests, so
# no CORS is needed in production.

# --- Build stage ---
FROM node:22-alpine AS build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
ARG VITE_API_BASE_URL=/api
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
RUN npm run build

# --- Serve stage ---
FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
