# Next.js frontend — PRODUCTION build (next build + next start).
#
# Build context = the application directory (apps/customer-web or
# apps/service-admin); both have the same structure, so a single Dockerfile
# covers them. The node base is pinned for the same reason as the PHP one —
# see docs/TROUBLESHOOTING.md.
FROM node:20-bookworm-slim AS build
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund
COPY . .
# NEXT_PUBLIC_API_BASE is read at runtime by the /api rewrite in next.config;
# we also set it at build time as a sane default.
ARG NEXT_PUBLIC_API_BASE=http://api
ENV NEXT_PUBLIC_API_BASE=${NEXT_PUBLIC_API_BASE}
RUN npm run build

FROM node:20-bookworm-slim AS run
WORKDIR /app
ENV NODE_ENV=production
# Copy the complete build (node_modules included) — a simple image, no `standalone` output.
COPY --from=build /app ./
EXPOSE 3000
# The port can be overridden from compose (-p). We listen on all interfaces.
CMD ["npx", "next", "start", "-H", "0.0.0.0", "-p", "3000"]
