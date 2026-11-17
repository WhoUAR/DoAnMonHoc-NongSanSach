import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
// CHỨC NĂNG: Cấu hình máy chủ phát triển của Vite.
//   - Cổng 5173 khớp với FRONTEND_URL trong .env của Backend (dùng cho CORS).
//   - Proxy /api sang Laravel để tránh vấn đề CORS khi phát triển.
export default defineConfig({
  plugins: [react()],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
})
