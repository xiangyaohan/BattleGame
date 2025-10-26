# 《攻城掠地》桌游项目 - Vercel部署指南

## 📋 概述

本指南将帮助您将《攻城掠地》桌游项目部署到Vercel平台，实现在线访问。

## 🚀 快速部署

### 方法一：使用部署脚本（推荐）

1. **Windows批处理脚本**：
   ```bash
   deploy-vercel.bat
   ```

2. **PowerShell脚本**：
   ```powershell
   .\deploy-vercel.ps1
   ```

### 方法二：手动部署

1. **安装Vercel CLI**：
   ```bash
   npm install -g vercel
   ```

2. **登录Vercel账户**：
   ```bash
   vercel login
   ```

3. **部署项目**：
   ```bash
   # 部署预览版本
   vercel
   
   # 部署生产版本
   vercel --prod
   ```

## 📁 项目结构

```
BattelGame/
├── index.html              # 主页面
├── game.js                 # 游戏逻辑
├── vercel.json             # Vercel配置文件
├── .vercelignore           # 部署忽略文件
├── package.json            # 项目配置
├── deploy-vercel.bat       # Windows部署脚本
├── deploy-vercel.ps1       # PowerShell部署脚本
└── VERCEL-DEPLOYMENT.md    # 本文档
```

## ⚙️ 配置说明

### vercel.json 配置

```json
{
  "version": 2,
  "name": "siege-board-game",
  "builds": [
    {
      "src": "index.html",
      "use": "@vercel/static"
    }
  ],
  "routes": [
    {
      "src": "/",
      "dest": "/index.html"
    }
  ]
}
```

### 主要配置项：

- **静态文件服务**：支持HTML、CSS、JS、图片等静态资源
- **路由配置**：设置了友好的URL路由
- **安全头**：添加了基本的安全HTTP头
- **MIME类型**：正确配置了文件类型

## 🌐 部署后访问

部署成功后，您将获得：

1. **预览URL**：`https://your-project-name-xxx.vercel.app`
2. **生产URL**：`https://your-project-name.vercel.app`

### 访问路径：

- **主页**：`/` 或 `/game`
- **游戏规则**：`/rules`
- **静态资源**：直接访问文件路径

## 🔧 常用命令

```bash
# 查看部署列表
vercel ls

# 查看项目信息
vercel inspect

# 删除部署
vercel rm [deployment-url]

# 查看域名
vercel domains

# 添加自定义域名
vercel domains add your-domain.com
```

## 📊 环境变量配置

如果需要配置环境变量：

1. **通过CLI**：
   ```bash
   vercel env add [name]
   ```

2. **通过Dashboard**：
   - 访问 [Vercel Dashboard](https://vercel.com/dashboard)
   - 选择项目 → Settings → Environment Variables

## 🔒 域名和SSL配置

### 添加自定义域名

1. **通过CLI**：
   ```bash
   vercel domains add yourdomain.com
   ```

2. **通过Dashboard**：
   - 项目设置 → Domains
   - 添加域名并配置DNS

### SSL证书

Vercel自动为所有域名提供免费SSL证书，无需额外配置。

## 🚨 故障排除

### 常见问题

1. **部署失败**：
   - 检查网络连接
   - 确保已正确登录Vercel
   - 检查项目配置文件

2. **静态文件无法访问**：
   - 检查`.vercelignore`文件
   - 确认文件路径正确
   - 检查`vercel.json`路由配置

3. **游戏无法正常运行**：
   - 检查JavaScript文件路径
   - 确认所有依赖文件已部署
   - 查看浏览器控制台错误

### 调试命令

```bash
# 查看部署日志
vercel logs [deployment-url]

# 本地预览
vercel dev

# 检查配置
vercel inspect
```

## 📈 性能优化

### 建议优化项：

1. **文件压缩**：Vercel自动启用Gzip压缩
2. **CDN加速**：全球CDN自动分发
3. **缓存策略**：静态文件自动缓存
4. **图片优化**：考虑使用WebP格式

## 🔄 持续部署

### Git集成

1. **连接GitHub**：
   - 在Vercel Dashboard中导入GitHub仓库
   - 每次推送代码自动部署

2. **分支部署**：
   - `main`分支 → 生产环境
   - 其他分支 → 预览环境

### 部署钩子

```bash
# 设置部署钩子
vercel env add DEPLOY_HOOK [webhook-url]
```

## 📞 支持和帮助

- **Vercel文档**：https://vercel.com/docs
- **社区支持**：https://github.com/vercel/vercel/discussions
- **状态页面**：https://vercel-status.com

## 📝 更新日志

- **v1.0.0**：初始Vercel部署配置
- 支持静态文件服务
- 配置路由和安全头
- 添加部署脚本和文档

---

🎮 **祝您游戏愉快！《攻城掠地》桌游现已在线可用！**