# 使用官方 Node.js 运行时作为基础镜像
FROM node:18-alpine

# 设置工作目录
WORKDIR /app

# 复制 package.json 文件
COPY package*.json ./

# 安装依赖（如果有的话）
RUN npm ci --only=production || echo "No dependencies to install"

# 复制应用程序文件
COPY . .

# 创建非 root 用户
RUN addgroup -g 1001 -S nodejs && \
    adduser -S nextjs -u 1001

# 更改文件所有权
RUN chown -R nextjs:nodejs /app
USER nextjs

# 暴露端口
EXPOSE 8000

# 设置环境变量
ENV NODE_ENV=production
ENV PORT=8000
ENV HOST=0.0.0.0

# 健康检查
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD node -e "const http = require('http'); \
    const options = { host: 'localhost', port: process.env.PORT || 8000, timeout: 2000 }; \
    const request = http.request(options, (res) => { \
        console.log('Health check passed'); \
        process.exit(res.statusCode === 200 ? 0 : 1); \
    }); \
    request.on('error', () => { \
        console.log('Health check failed'); \
        process.exit(1); \
    }); \
    request.end();"

# 启动应用
CMD ["node", "server.js"]