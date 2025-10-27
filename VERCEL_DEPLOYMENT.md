# 《攻城掠地》桌游 - Vercel 部署指南

## 项目概述

《攻城掠地》是一个基于Web的策略卡牌游戏，现已配置为可在Vercel平台上部署的静态网站。

## 部署配置

### 文件结构
```
BattelKingGame/
├── index.html          # 主页面
├── game.js            # 游戏逻辑
├── card_image/        # 卡牌图片资源
├── vercel.json        # Vercel部署配置
├── package.json       # 项目配置
└── README.md          # 项目说明
```

### Vercel配置说明

项目已配置`vercel.json`文件，包含以下特性：

1. **静态文件服务**: 将项目作为静态网站部署
2. **路由配置**: 
   - 根路径 `/` 指向 `index.html`
   - 静态资源路径映射
   - SPA路由支持（所有未匹配路径回退到index.html）
3. **CORS支持**: 配置跨域访问头部
4. **缓存优化**: 
   - HTML文件：5分钟缓存
   - JS/CSS文件：1天缓存
   - 图片文件：1年缓存

## 部署步骤

### 方法一：通过Vercel CLI部署

1. **安装Vercel CLI**
   ```bash
   npm install -g vercel
   ```

2. **登录Vercel**
   ```bash
   vercel login
   ```

3. **部署项目**
   ```bash
   vercel
   ```
   
   首次部署时会询问项目配置，按提示操作即可。

4. **生产环境部署**
   ```bash
   vercel --prod
   ```

### 方法二：通过Git集成部署

1. **推送代码到Git仓库**
   ```bash
   git add .
   git commit -m "Add Vercel deployment configuration"
   git push origin main
   ```

2. **在Vercel Dashboard中导入项目**
   - 访问 [vercel.com](https://vercel.com)
   - 点击 "New Project"
   - 选择你的Git仓库
   - Vercel会自动检测配置并部署

### 方法三：通过拖拽部署

1. 将项目文件夹直接拖拽到 [vercel.com/new](https://vercel.com/new)
2. Vercel会自动上传并部署

## 环境变量

当前项目为纯静态网站，无需配置环境变量。

## 域名配置

部署成功后，Vercel会提供一个默认域名（如：`your-project.vercel.app`）。

如需自定义域名：
1. 在Vercel Dashboard中进入项目设置
2. 点击 "Domains" 标签
3. 添加自定义域名并按提示配置DNS

## 性能优化

项目已包含以下优化：

1. **静态资源缓存**: 通过HTTP头部设置合理的缓存策略
2. **图片优化**: 建议使用WebP格式以减少文件大小
3. **CDN加速**: Vercel自动提供全球CDN加速

## 监控和分析

Vercel提供内置的分析功能：
- 访问量统计
- 性能监控
- 错误追踪

可在Vercel Dashboard的Analytics标签中查看。

## 故障排除

### 常见问题

1. **图片无法显示**
   - 检查`card_image/`目录是否正确上传
   - 确认图片路径在代码中是否正确

2. **404错误**
   - 检查`vercel.json`中的路由配置
   - 确认文件路径大小写是否正确

3. **CORS错误**
   - 检查`vercel.json`中的headers配置
   - 确认跨域请求设置是否正确

### 调试方法

1. **查看部署日志**
   ```bash
   vercel logs
   ```

2. **本地测试**
   ```bash
   vercel dev
   ```

## 更新部署

### 自动部署
如果使用Git集成，每次推送到主分支都会自动触发部署。

### 手动部署
```bash
vercel --prod
```

## 成本说明

- **Hobby计划**: 免费，适合个人项目
- **Pro计划**: 付费，提供更多功能和资源

当前项目在免费计划下完全可用。

## 技术支持

- [Vercel官方文档](https://vercel.com/docs)
- [Vercel社区](https://github.com/vercel/vercel/discussions)

---

**注意**: 部署前请确保所有文件路径正确，特别是图片资源路径。