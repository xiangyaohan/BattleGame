# 《攻城掠地》桌游

一个基于 Web 的策略卡牌游戏，忠实还原经典桌游《攻城掠地》的游戏机制。

## 🎮 游戏特色

- **忠实还原**：严格按照原版桌游规则实现
- **智能 AI**：具有策略思维的 AI 对手
- **完整流程**：从阵营选择到游戏结束的完整体验
- **现代界面**：清晰直观的用户界面
- **响应式设计**：支持各种设备访问

## 🚀 快速开始

### 本地运行

#### 方法 1：直接运行（推荐）

```bash
# 克隆项目
git clone <repository-url>
cd siege-board-game

# 启动服务器
node server.js
```

访问 `http://localhost:8000` 开始游戏。

#### 方法 2：使用 npm

```bash
# 安装依赖（可选，本项目无外部依赖）
npm install

# 启动服务器
npm start

# 开发模式
npm run dev
```

### 🐳 Docker 部署

#### 使用 Docker

```bash
# 构建镜像
docker build -t siege-board-game .

# 运行容器
docker run -p 8000:8000 siege-board-game
```

#### 使用 Docker Compose（推荐）

```bash
# 生产环境
docker-compose up -d

# 开发环境
docker-compose --profile dev up -d

# 带 Nginx 反向代理
docker-compose --profile with-nginx up -d
```

## ☁️ 云平台部署

### Vercel 部署

1. 将项目推送到 GitHub
2. 在 [Vercel](https://vercel.com) 导入项目
3. 设置构建命令：`echo "Static files ready"`
4. 设置输出目录：`./`
5. 部署完成

### Netlify 部署

1. 将项目推送到 GitHub
2. 在 [Netlify](https://netlify.com) 导入项目
3. 设置构建命令：`npm run build`
4. 设置发布目录：`./`
5. 部署完成

### Railway 部署

1. 在 [Railway](https://railway.app) 创建新项目
2. 连接 GitHub 仓库
3. Railway 会自动检测 Node.js 项目并部署
4. 设置环境变量（如需要）

### 传统服务器部署

```bash
# 在服务器上克隆项目
git clone <repository-url>
cd siege-board-game

# 安装 Node.js（如果未安装）
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# 启动服务器
node server.js

# 使用 PM2 进程管理（推荐）
npm install -g pm2
pm2 start server.js --name "siege-game"
pm2 startup
pm2 save
```

## ⚙️ 环境变量配置

创建 `.env` 文件（可选）：

```env
# 服务器配置
PORT=8000
HOST=localhost
NODE_ENV=production

# 其他配置
# 添加其他需要的环境变量
```

支持的环境变量：

- `PORT`: 服务器端口（默认：8000）
- `HOST`: 服务器主机（默认：localhost）
- `NODE_ENV`: 运行环境（development/production）

## 📁 项目结构

```
siege-board-game/
├── index.html              # 主页面
├── game.js                 # 游戏逻辑
├── server.js               # Web 服务器
├── package.json            # 项目配置
├── Dockerfile              # Docker 配置
├── docker-compose.yml      # Docker Compose 配置
├── .gitignore             # Git 忽略文件
├── README.md              # 项目说明
└── 《攻城掠地》桌游规则书.pdf  # 游戏规则
```

## 🎯 游戏规则

详细游戏规则请参考项目中的《攻城掠地》桌游规则书.pdf 文件。

### 基本流程

1. **阵营选择**：选择你的阵营（魏、蜀、吴）
2. **决斗阶段**：通过决斗决定首轮攻守
3. **战斗阶段**：进行三轮攻守战斗
4. **计分阶段**：根据战斗结果计分
5. **游戏结束**：达到胜利条件或回合结束

### 核心机制

- **手牌限制**：每回合最多 5 张手牌
- **战斗力计算**：卡牌数值总和
- **牌数惩罚**：攻方牌数少于守方时扣除战斗力
- **骰子决胜**：平局时投掷骰子决定胜负

## 🛠️ 开发

### 本地开发

```bash
# 启动开发服务器
npm run dev

# 或直接运行
node server.js
```

### 代码结构

- `index.html`: 游戏界面和样式
- `game.js`: 游戏逻辑、AI 算法、状态管理
- `server.js`: 静态文件服务器

### 技术栈

- **前端**: HTML5, CSS3, JavaScript (ES6+)
- **后端**: Node.js (原生 HTTP 模块)
- **部署**: Docker, Docker Compose
- **云平台**: Vercel, Netlify, Railway

## 🔧 故障排除

### 常见问题

1. **端口被占用**
   ```bash
   # 查找占用端口的进程
   netstat -ano | findstr :8000
   # 或使用其他端口
   PORT=3000 node server.js
   ```

2. **Docker 构建失败**
   ```bash
   # 清理 Docker 缓存
   docker system prune -a
   # 重新构建
   docker build --no-cache -t siege-board-game .
   ```

3. **文件权限问题**（Linux/Mac）
   ```bash
   chmod +x server.js
   ```

### 性能优化

- 启用 gzip 压缩
- 设置适当的缓存头
- 使用 CDN 加速静态资源
- 配置负载均衡

## 📝 更新日志

### v1.0.0
- 初始版本发布
- 完整的游戏功能实现
- 支持多种部署方式
- 添加 Docker 支持

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

MIT License

## 🎮 开始游戏

现在就访问 [http://localhost:8000](http://localhost:8000) 开始你的《