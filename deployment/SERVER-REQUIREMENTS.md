# 《攻城掠地》桌游项目 - 服务器要求文档

## 目录
1. [硬件要求](#硬件要求)
2. [操作系统要求](#操作系统要求)
3. [网络要求](#网络要求)
4. [软件环境要求](#软件环境要求)
5. [安全要求](#安全要求)
6. [推荐配置](#推荐配置)
7. [云服务商选择](#云服务商选择)
8. [成本估算](#成本估算)

## 硬件要求

### 1. 最低配置要求

#### 1.1 CPU
- **最低要求**: 2核心 2.0GHz
- **推荐配置**: 4核心 2.4GHz 或更高
- **架构**: x86_64 (AMD64)
- **特性**: 支持虚拟化技术

#### 1.2 内存 (RAM)
- **最低要求**: 4GB
- **推荐配置**: 8GB 或更高
- **类型**: DDR4 或更新
- **用途分配**:
  - 系统: 1GB
  - Node.js应用: 2-4GB
  - Nginx: 512MB
  - 数据库: 1-2GB
  - 监控系统: 1GB
  - 缓存: 512MB

#### 1.3 存储
- **最低要求**: 50GB SSD
- **推荐配置**: 100GB SSD 或更高
- **类型**: SSD (固态硬盘)
- **IOPS**: 最少3000 IOPS
- **用途分配**:
  - 系统: 20GB
  - 应用代码: 5GB
  - 日志文件: 10GB
  - 备份: 20GB
  - 数据库: 10GB
  - 临时文件: 5GB

#### 1.4 网络
- **带宽**: 最少100Mbps，推荐1Gbps
- **延迟**: <50ms (到主要用户群体)
- **可用性**: 99.9% 或更高

### 2. 生产环境推荐配置

#### 2.1 小型部署 (100-1000用户)
```
CPU: 4核心 2.4GHz
内存: 8GB DDR4
存储: 100GB SSD
网络: 100Mbps
```

#### 2.2 中型部署 (1000-10000用户)
```
CPU: 8核心 2.8GHz
内存: 16GB DDR4
存储: 200GB SSD
网络: 1Gbps
```

#### 2.3 大型部署 (10000+用户)
```
CPU: 16核心 3.0GHz
内存: 32GB DDR4
存储: 500GB SSD
网络: 10Gbps
负载均衡: 多服务器集群
```

## 操作系统要求

### 3. 支持的操作系统

#### 3.1 推荐系统 (按优先级排序)
1. **Ubuntu 20.04 LTS** ⭐ (强烈推荐)
   - 长期支持到2025年
   - 软件包丰富
   - 社区支持好
   - 安全更新及时

2. **Ubuntu 22.04 LTS**
   - 最新LTS版本
   - 支持到2027年
   - 更新的软件包

3. **CentOS 8 / Rocky Linux 8**
   - 企业级稳定性
   - 红帽生态系统
   - 适合企业环境

4. **Debian 11 (Bullseye)**
   - 极其稳定
   - 安全性好
   - 资源占用少

#### 3.2 系统要求
- **内核版本**: Linux 4.15 或更高
- **文件系统**: ext4, xfs, 或 btrfs
- **包管理器**: apt (Ubuntu/Debian) 或 yum/dnf (CentOS/Rocky)
- **systemd**: 支持systemd服务管理
- **防火墙**: ufw (Ubuntu) 或 firewalld (CentOS)

#### 3.3 不支持的系统
- Windows Server (不推荐，兼容性问题)
- 过时的Linux发行版 (如CentOS 6/7)
- 32位操作系统

### 4. 系统配置要求

#### 4.1 用户和权限
```bash
# 创建专用部署用户
useradd -m -s /bin/bash deploy
usermod -aG sudo deploy

# 配置sudo免密码
echo "deploy ALL=(ALL) NOPASSWD:ALL" >> /etc/sudoers.d/deploy
```

#### 4.2 系统限制
```bash
# /etc/security/limits.conf
deploy soft nofile 65536
deploy hard nofile 65536
deploy soft nproc 32768
deploy hard nproc 32768
```

#### 4.3 内核参数
```bash
# /etc/sysctl.conf
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 1200
vm.swappiness = 10
```

## 网络要求

### 5. 网络配置

#### 5.1 公网IP
- **要求**: 固定公网IP地址
- **IPv4**: 必须
- **IPv6**: 可选但推荐
- **反向DNS**: 推荐配置

#### 5.2 域名要求
- **主域名**: yourdomain.com
- **子域名**: 
  - www.yourdomain.com (网站访问)
  - api.yourdomain.com (API接口，可选)
  - admin.yourdomain.com (管理后台，可选)
- **SSL证书**: 支持通配符证书

#### 5.3 端口要求

##### 5.3.1 必须开放的端口
```
22/tcp   - SSH管理
80/tcp   - HTTP (重定向到HTTPS)
443/tcp  - HTTPS
```

##### 5.3.2 内部使用端口
```
8000/tcp - Node.js应用 (内部)
3000/tcp - Grafana监控 (内部)
9090/tcp - Prometheus (内部)
3306/tcp - MySQL (内部)
6379/tcp - Redis (内部)
```

#### 5.4 防火墙配置
```bash
# UFW防火墙规则
ufw enable
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow 80/tcp
ufw allow 443/tcp
```

#### 5.5 CDN要求 (可选)
- **推荐**: Cloudflare, AWS CloudFront, 阿里云CDN
- **功能**: 静态资源加速, DDoS防护, SSL终止
- **配置**: 支持回源到服务器

## 软件环境要求

### 6. 运行时环境

#### 6.1 Node.js
- **版本**: Node.js 18.x LTS (推荐)
- **最低版本**: Node.js 16.x
- **包管理器**: npm 8.x 或 yarn 1.22.x
- **全局包**: PM2 5.x

#### 6.2 Web服务器
- **Nginx**: 1.18.x 或更高
- **模块要求**:
  - http_ssl_module
  - http_v2_module
  - http_realip_module
  - http_gzip_static_module

#### 6.3 数据库 (可选)
- **MySQL**: 8.0.x (如果需要关系型数据库)
- **Redis**: 6.x 或 7.x (缓存和会话存储)

#### 6.4 监控系统
- **Prometheus**: 2.40.x 或更高
- **Grafana**: 9.x 或更高
- **Node Exporter**: 1.5.x (系统指标)

#### 6.5 其他工具
```bash
# 必需工具
curl, wget, git, vim, htop, unzip

# SSL证书
certbot, python3-certbot-nginx

# 备份工具
rsync, tar, gzip

# 监控工具
iostat, netstat, ss, lsof
```

### 7. 开发工具 (可选)
```bash
# 编译工具 (如果需要编译native模块)
build-essential, python3-dev

# 图像处理 (如果需要)
imagemagick, ffmpeg

# 数据库客户端
mysql-client, redis-tools
```

## 安全要求

### 8. 安全配置

#### 8.1 SSH安全
```bash
# /etc/ssh/sshd_config
Port 22
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
```

#### 8.2 防火墙和入侵检测
```bash
# 安装fail2ban
apt install fail2ban

# 配置fail2ban
# /etc/fail2ban/jail.local
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600
```

#### 8.3 SSL/TLS要求
- **协议**: TLS 1.2 或更高
- **证书**: Let's Encrypt 或商业证书
- **密码套件**: 现代安全套件
- **HSTS**: 启用HTTP严格传输安全

#### 8.4 系统安全
```bash
# 自动安全更新
apt install unattended-upgrades
dpkg-reconfigure unattended-upgrades

# 系统审计
apt install auditd
systemctl enable auditd
```

## 推荐配置

### 9. 云服务器推荐配置

#### 9.1 阿里云ECS
```
实例规格: ecs.c6.xlarge
CPU: 4核心
内存: 8GB
系统盘: 40GB ESSD
数据盘: 100GB ESSD
带宽: 5Mbps (按需升级)
地域: 根据用户分布选择
```

#### 9.2 腾讯云CVM
```
实例规格: S5.LARGE8
CPU: 4核心
内存: 8GB
系统盘: 50GB SSD云硬盘
数据盘: 100GB SSD云硬盘
带宽: 5Mbps
地域: 根据用户分布选择
```

#### 9.3 AWS EC2
```
实例类型: t3.large
CPU: 2核心
内存: 8GB
存储: 100GB gp3 EBS
网络: 最高5Gbps
地区: 根据用户分布选择
```

#### 9.4 Azure虚拟机
```
虚拟机大小: Standard_B2s
CPU: 2核心
内存: 4GB
存储: 100GB Premium SSD
网络: 中等网络性能
地区: 根据用户分布选择
```

### 10. 本地服务器配置

#### 10.1 硬件推荐
```
CPU: Intel Xeon E-2236 或 AMD EPYC 7232P
内存: 32GB DDR4 ECC
存储: 500GB NVMe SSD + 2TB HDD (备份)
网络: 千兆以太网
电源: 冗余电源 (可选)
```

#### 10.2 网络接入
- **带宽**: 企业级光纤接入
- **IP**: 固定公网IP
- **备份**: 双线接入 (可选)
- **防护**: 硬件防火墙

## 云服务商选择

### 11. 云服务商对比

#### 11.1 国内云服务商

| 服务商 | 优势 | 劣势 | 适用场景 |
|--------|------|------|----------|
| 阿里云 | 产品丰富、稳定性好 | 价格较高 | 企业级应用 |
| 腾讯云 | 游戏行业经验丰富 | 文档相对较少 | 游戏应用 |
| 华为云 | 技术实力强 | 生态相对较小 | 技术导向项目 |
| 百度云 | AI能力强 | 市场份额小 | AI相关应用 |

#### 11.2 国外云服务商

| 服务商 | 优势 | 劣势 | 适用场景 |
|--------|------|------|----------|
| AWS | 功能最全面 | 复杂度高、成本高 | 大型企业 |
| Azure | 微软生态好 | 学习曲线陡峭 | .NET应用 |
| Google Cloud | 技术先进 | 在中国服务有限 | 技术创新项目 |
| DigitalOcean | 简单易用、价格便宜 | 功能相对简单 | 小型项目 |

### 12. 选择建议

#### 12.1 选择因素
1. **用户地理分布**: 选择就近的数据中心
2. **预算考虑**: 平衡性能和成本
3. **技术支持**: 考虑服务商的技术支持质量
4. **合规要求**: 考虑数据安全和合规要求
5. **扩展性**: 考虑未来扩展的便利性

#### 12.2 推荐方案

**小型项目 (预算有限)**
- DigitalOcean Droplet
- Vultr VPS
- Linode VPS

**中型项目 (平衡性能和成本)**
- 阿里云ECS
- 腾讯云CVM
- AWS EC2 t3实例

**大型项目 (性能优先)**
- AWS EC2 c5实例
- 阿里云计算优化型
- 自建服务器集群

## 成本估算

### 13. 月度成本估算

#### 13.1 小型部署 (4核8GB)
```
阿里云ECS: ¥300-500/月
腾讯云CVM: ¥280-450/月
AWS EC2: $50-80/月
DigitalOcean: $40-60/月

额外成本:
- 域名: ¥50-100/年
- SSL证书: ¥0-500/年 (Let's Encrypt免费)
- 备份存储: ¥20-50/月
- CDN: ¥50-200/月 (可选)
```

#### 13.2 中型部署 (8核16GB)
```
阿里云ECS: ¥600-1000/月
腾讯云CVM: ¥550-900/月
AWS EC2: $100-150/月

额外成本:
- 负载均衡: ¥100-200/月
- 数据库: ¥200-500/月
- 监控服务: ¥100-300/月
- 专业SSL证书: ¥500-2000/年
```

#### 13.3 大型部署 (多服务器)
```
服务器集群: ¥2000-5000/月
负载均衡: ¥300-800/月
数据库集群: ¥1000-3000/月
CDN: ¥500-2000/月
专业运维: ¥5000-15000/月
```

### 14. 成本优化建议

#### 14.1 节省成本的方法
1. **预付费**: 年付通常有折扣
2. **竞价实例**: 适合非关键业务
3. **资源监控**: 及时调整配置
4. **CDN优化**: 减少带宽成本
5. **自动化**: 减少人工运维成本

#### 14.2 性能优化投资
1. **SSD存储**: 提升I/O性能
2. **内存升级**: 减少磁盘访问
3. **CDN服务**: 提升用户体验
4. **监控系统**: 预防故障
5. **备份策略**: 保障数据安全

---

## 检查清单

### 15. 部署前检查

- [ ] 服务器硬件配置满足要求
- [ ] 操作系统版本支持
- [ ] 网络连接和带宽充足
- [ ] 域名解析配置正确
- [ ] SSL证书准备就绪
- [ ] 防火墙规则配置完成
- [ ] 监控系统规划完成
- [ ] 备份策略制定完成
- [ ] 安全配置检查完成
- [ ] 成本预算确认

### 16. 部署后验证

- [ ] 所有服务正常启动
- [ ] 网站可以正常访问
- [ ] HTTPS证书工作正常
- [ ] 监控系统收集数据
- [ ] 备份任务执行正常
- [ ] 性能指标符合预期
- [ ] 安全扫描通过
- [ ] 日志记录正常

---

**注意**: 以上要求是基于《攻城掠地》桌游项目的特定需求制定的。实际部署时请根据具体情况调整配置。