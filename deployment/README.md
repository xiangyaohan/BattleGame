# 《攻城掠地》桌游项目 - 生产环境部署指南

## 项目概述

《攻城掠地》是一个基于Node.js的桌游项目，包含静态文件服务器和WordPress插件支持。本文档提供完整的生产环境部署方案。

## 目录结构

```
deployment/
├── README.md                    # 主要部署文档
├── DEPLOYMENT-GUIDE.md         # 详细部署步骤
├── TROUBLESHOOTING.md          # 故障排除指南
├── MAINTENANCE.md              # 维护操作手册
├── server-requirements.md      # 服务器要求
├── scripts/                    # 部署脚本
│   ├── server-setup.sh         # 服务器环境配置
│   ├── install-nodejs.sh       # Node.js安装
│   ├── install-dependencies.sh # 系统依赖安装
│   └── deploy.sh              # 自动化部署脚本
├── config/                     # 配置文件
│   ├── .env.production        # 生产环境变量
│   ├── pm2.config.js          # PM2配置
│   ├── nginx.conf             # Nginx配置
│   └── ssl/                   # SSL证书配置
├── docker/                     # Docker配置
│   ├── Dockerfile.production  # 生产Dockerfile
│   ├── docker-compose.production.yml
│   └── docker-deploy.sh       # Docker部署脚本
├── monitoring/                 # 监控配置
│   ├── prometheus/            # Prometheus配置
│   ├── grafana/              # Grafana配置
│   └── health-check.sh       # 健康检查脚本
├── logging/                    # 日志配置
│   ├── logrotate.conf         # 日志轮转
│   └── rsyslog.conf          # 系统日志
├── backup.sh                   # 备份脚本
├── rollback.sh                # 回滚脚本
├── monitoring-check.sh        # 监控检查脚本
└── cron-backup.conf           # 自动备份配置
```

## 快速开始

### 1. 服务器要求

- **操作系统**: Ubuntu 20.04 LTS 或更高版本
- **CPU**: 最少2核，推荐4核
- **内存**: 最少4GB，推荐8GB
- **存储**: 最少50GB SSD
- **网络**: 公网IP，开放80/443端口

详细要求请参考 [server-requirements.md](server-requirements.md)

### 2. 一键部署

```bash
# 下载部署脚本
wget https://raw.githubusercontent.com/your-repo/siege-game/main/deployment/scripts/deploy.sh

# 设置执行权限
chmod +x deploy.sh

# 执行部署
./deploy.sh
```

### 3. Docker部署

```bash
# 克隆项目
git clone https://github.com/your-repo/siege-game.git
cd siege-game/deployment

# 执行Docker部署
./docker-deploy.sh deploy
```

## 部署方式

### 方式一：传统部署 (推荐)

使用PM2进程管理器和Nginx反向代理的传统部署方式。

**优点**:
- 性能优异
- 配置灵活
- 易于调试
- 资源占用少

**适用场景**:
- 单服务器部署
- 对性能要求高
- 需要精细控制

### 方式二：Docker部署

使用Docker容器化部署，支持多服务编排。

**优点**:
- 环境一致性
- 易于扩展
- 隔离性好
- 便于迁移

**适用场景**:
- 多环境部署
- 微服务架构
- 容器化基础设施

## 核心组件

### 1. 应用服务器
- **Node.js**: v18.x LTS
- **PM2**: 进程管理
- **Express**: Web框架

### 2. 反向代理
- **Nginx**: 负载均衡、SSL终止
- **配置**: Gzip压缩、缓存、安全头

### 3. 监控系统
- **Prometheus**: 指标收集
- **Grafana**: 可视化监控
- **健康检查**: 自动化监控

### 4. 日志系统
- **Winston**: 应用日志
- **Logrotate**: 日志轮转
- **Rsyslog**: 系统日志

### 5. 备份系统
- **自动备份**: 代码、配置、数据库
- **定时任务**: Cron调度
- **回滚机制**: 快速恢复

## 安全配置

### 1. 防火墙设置
```bash
# 基本防火墙规则
ufw enable
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
```

### 2. SSL证书
```bash
# 使用Let's Encrypt
certbot --nginx -d yourdomain.com
```

### 3. 安全头配置
- HTTPS强制
- HSTS启用
- XSS保护
- CSRF防护

## 性能优化

### 1. 应用层优化
- 静态文件缓存
- Gzip压缩
- 连接池配置
- 内存管理

### 2. 数据库优化
- 连接池配置
- 查询优化
- 索引优化
- 缓存策略

### 3. 系统层优化
- 文件描述符限制
- 内核参数调优
- 网络参数优化
- 磁盘I/O优化

## 监控指标

### 1. 应用指标
- 响应时间
- 错误率
- 吞吐量
- 内存使用

### 2. 系统指标
- CPU使用率
- 内存使用率
- 磁盘使用率
- 网络流量

### 3. 业务指标
- 用户访问量
- 游戏会话数
- 错误日志
- 性能瓶颈

## 维护操作

### 1. 日常维护
```bash
# 检查服务状态
./health-check.sh full

# 查看日志
tail -f /var/log/siege-game/app.log

# 重启服务
pm2 restart siege-game
```

### 2. 备份操作
```bash
# 手动备份
./backup.sh full

# 查看备份
./backup.sh list

# 恢复备份
./rollback.sh
```

### 3. 更新部署
```bash
# 拉取最新代码
git pull origin main

# 安装依赖
npm install --production

# 重启应用
pm2 restart siege-game
```

## 故障排除

### 1. 常见问题
- 应用无法启动
- 响应时间过长
- 内存泄漏
- 数据库连接失败

### 2. 诊断工具
- 健康检查脚本
- 日志分析
- 性能监控
- 错误追踪

详细故障排除请参考 [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

## 扩展部署

### 1. 负载均衡
```nginx
upstream siege_game {
    server 127.0.0.1:8000;
    server 127.0.0.1:8001;
    server 127.0.0.1:8002;
}
```

### 2. 数据库集群
- 主从复制
- 读写分离
- 连接池配置
- 故障转移

### 3. CDN配置
- 静态资源加速
- 图片优化
- 缓存策略
- 边缘节点

## 联系支持

- **技术支持**: tech-support@yourdomain.com
- **运维支持**: ops@yourdomain.com
- **紧急联系**: +86-xxx-xxxx-xxxx

## 版本历史

- **v1.0.0**: 初始版本
- **v1.1.0**: 添加Docker支持
- **v1.2.0**: 增强监控功能
- **v1.3.0**: 优化性能配置

## 许可证

本项目采用 MIT 许可证，详情请参考 LICENSE 文件。

---

**注意**: 部署前请仔细阅读所有文档，确保理解每个配置项的作用。如有疑问，请联系技术支持团队。