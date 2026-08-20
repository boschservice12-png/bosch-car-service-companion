/** @type {import('next').NextConfig} */
const nextConfig = {
  /* A verification build must not write into the directory the dev server is
     serving from. When it does, both processes write .next concurrently and the
     running app dies with "__webpack_modules__[moduleId] is not a function"
     until the dev server is restarted and .next is deleted.

     Default is unchanged, so `npm run build` and the Docker builds behave
     exactly as before; set BUILD_DIST_DIR to build alongside a live dev server:
       BUILD_DIST_DIR=.next-verify npx next build
     Note that `--distDir` is not a CLI flag on this Next version. */
  distDir: process.env.BUILD_DIST_DIR || '.next',
  reactStrictMode: true,
  async rewrites() {
    const api = process.env.NEXT_PUBLIC_API_BASE ?? 'http://localhost:8080';
    return [{ source: '/api/:path*', destination: `${api}/api/:path*` }];
  },
};

export default nextConfig;
