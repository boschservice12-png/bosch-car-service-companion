/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  async rewrites() {
    // Proxy API către backend (evită probleme CORS în dev).
    const api = process.env.NEXT_PUBLIC_API_BASE ?? 'http://localhost:8080';
    return [{ source: '/api/:path*', destination: `${api}/api/:path*` }];
  },
};

export default nextConfig;
