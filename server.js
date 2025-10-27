const http = require('http');
const fs = require('fs');
const path = require('path');

// 配置
const config = {
    port: process.env.PORT || process.env.NODE_PORT || 8000,
    host: process.env.HOST || 'localhost',
    env: process.env.NODE_ENV || 'development'
};

// MIME 类型映射
const mimeTypes = {
    '.html': 'text/html',
    '.js': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.png': 'image/png',
    '.jpg': 'image/jpg',
    '.jpeg': 'image/jpeg',
    '.gif': 'image/gif',
    '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon',
    '.wav': 'audio/wav',
    '.mp3': 'audio/mpeg',
    '.mp4': 'video/mp4',
    '.woff': 'application/font-woff',
    '.woff2': 'application/font-woff2',
    '.ttf': 'application/font-ttf',
    '.eot': 'application/vnd.ms-fontobject',
    '.otf': 'application/font-otf',
    '.wasm': 'application/wasm',
    '.pdf': 'application/pdf'
};

// 日志函数
function log(message) {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] ${message}`);
}

// 创建服务器
const server = http.createServer((req, res) => {
    // 添加 CORS 头部支持跨域
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

    // 处理 OPTIONS 请求
    if (req.method === 'OPTIONS') {
        res.writeHead(200);
        res.end();
        return;
    }

    // 解析请求路径
    let filePath = '.' + req.url;
    if (filePath === './') {
        filePath = './index.html';
    }

    // 安全检查：防止目录遍历攻击
    const normalizedPath = path.normalize(filePath);
    if (normalizedPath.includes('..')) {
        res.writeHead(403, { 'Content-Type': 'text/html' });
        res.end('<h1>403 Forbidden</h1>', 'utf-8');
        log(`Blocked directory traversal attempt: ${req.url}`);
        return;
    }

    const extname = String(path.extname(filePath)).toLowerCase();
    const contentType = mimeTypes[extname] || 'application/octet-stream';

    // 记录请求
    if (config.env === 'development') {
        log(`${req.method} ${req.url} - ${req.headers['user-agent']}`);
    }

    // 读取并返回文件
    fs.readFile(filePath, (error, content) => {
        if (error) {
            if (error.code === 'ENOENT') {
                res.writeHead(404, { 'Content-Type': 'text/html' });
                res.end(`
                    <html>
                        <head><title>404 Not Found</title></head>
                        <body>
                            <h1>404 - 页面未找到</h1>
                            <p>请求的文件 "${req.url}" 不存在。</p>
                            <a href="/">返回首页</a>
                        </body>
                    </html>
                `, 'utf-8');
                log(`404 Not Found: ${req.url}`);
            } else {
                res.writeHead(500, { 'Content-Type': 'text/html' });
                res.end(`
                    <html>
                        <head><title>500 Server Error</title></head>
                        <body>
                            <h1>500 - 服务器错误</h1>
                            <p>服务器遇到错误：${error.code}</p>
                        </body>
                    </html>
                `, 'utf-8');
                log(`Server Error: ${error.code} for ${req.url}`);
            }
        } else {
            // 设置缓存头部（生产环境）
            if (config.env === 'production') {
                const maxAge = extname === '.html' ? 300 : 86400; // HTML 5分钟，其他文件 1天
                res.setHeader('Cache-Control', `public, max-age=${maxAge}`);
            }

            res.writeHead(200, { 'Content-Type': contentType });
            res.end(content, 'utf-8');
        }
    });
});

// 优雅关闭处理
process.on('SIGTERM', () => {
    log('收到 SIGTERM 信号，正在关闭服务器...');
    server.close(() => {
        log('服务器已关闭');
        process.exit(0);
    });
});

process.on('SIGINT', () => {
    log('收到 SIGINT 信号，正在关闭服务器...');
    server.close(() => {
        log('服务器已关闭');
        process.exit(0);
    });
});

// 启动服务器
server.listen(config.port, config.host, () => {
    log(`《攻城掠地》桌游服务器启动成功！`);
    log(`访问地址: http://${config.host}:${config.port}/`);
    log(`环境: ${config.env}`);
    log(`Node.js 版本: ${process.version}`);
});