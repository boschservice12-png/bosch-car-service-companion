# Frontend Next.js — build de PRODUCȚIE (next build + next start).
# Context de build = directorul aplicației (apps/customer-web sau apps/service-admin);
# ambele au aceeași structură, deci un singur Dockerfile le acoperă pe amândouă.
FROM node:20-bookworm-slim AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund
COPY . .
# NEXT_PUBLIC_API_BASE se citește la runtime de rewrite-ul /api din next.config;
# îl setăm și la build ca valoare implicită sănătoasă.
ARG NEXT_PUBLIC_API_BASE=http://api
ENV NEXT_PUBLIC_API_BASE=${NEXT_PUBLIC_API_BASE}
RUN npm run build

FROM node:20-bookworm-slim AS run
WORKDIR /app
ENV NODE_ENV=production
# Copiem build-ul complet (node_modules incluse) — imagine simplă, fără `standalone`.
COPY --from=build /app ./
EXPOSE 3000
# Portul se poate suprascrie din compose (-p). Ascultăm pe toate interfețele.
CMD ["npx", "next", "start", "-H", "0.0.0.0", "-p", "3000"]
