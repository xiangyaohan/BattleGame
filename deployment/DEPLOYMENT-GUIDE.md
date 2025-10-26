# 《攻城掠地》桌游项目 - 详细部署步骤指南

## 目录
1. [准备工作](#准备工作)
2. [服务器环境配置](#服务器环境配置)
3. [代码部署](#代码部署)
4. [环境配置](#环境配置)
5. [依赖安装](#依赖安装)
6. [数据库配置](#数据库配置)
7. [服务启动](#服务启动)
8. [网络配置](#网络配置)
9. [监控设置](#监控设置)
10. [测试验证](#测试验证)
11. [生产优化](#生产优化)

## 准备工作

### 1. 服务器准备

#### 1.1 购买服务器
- 推荐云服务商：阿里云、腾讯云、AWS、Azure
- 配置要求：4核8GB内存，50GB SSD存储
- 操作系统：Ubuntu 20.04 LTS

#### 1.2 域名准备
- 购买域名（如：yourdomain.com）
- 配置DNS解析指向服务器IP
- 准备SSL证书（推荐Let's Encrypt）

#### 1.3 本地准备
```bash
# 安装必要工具
sudo apt update
sudo apt install -y git curl wget vim

# 创建部署用户
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG sudo deploy
sudo su - deploy
```

## 服务器环境配置

### 2. 系统基础配置

#### 2.1 更新系统
```bash
# 更新包列表
sudo apt update && sudo apt upgrade -y

# 安装基础工具
sudo apt install -y \
    curl \
    wget \
    git \
    vim \
    htop \
    unzip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release
```

#### 2.2 配置防火墙
```bash
# 启用UFW防火墙
sudo ufw enable

# 配置基本规则
sudo ufw default deny incoming
sudo ufw default allow outgoing

# 允许SSH、HTTP、HTTPS
sudo ufw allow ssh
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# 查看状态
sudo ufw status
```

#### 2.3 配置时区和NTP
```bash
# 设置时区
sudo timedatectl set-timezone Asia/Shanghai

# 安装NTP
sudo apt install -y ntp
sudo systemctl enable ntp
sudo systemctl start ntp
```

### 3. 安装Node.js环境

#### 3.1 使用NodeSource安装
```bash
# 添加NodeSource仓库
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -

# 安装Node.js
sudo apt install -y nodejs

# 验证安装
node --version  # 应显示 v18.x.x
npm --version   # 应显示对应版本
```

#### 3.2 安装PM2
```bash
# 全局安装PM2
sudo npm install -g pm2

# 设置PM2开机启动
pm2 startup
sudo env PATH=$PATH:/usr/bin /usr/lib/node_modules/pm2/bin/pm2 startup systemd -u deploy --hp /home/deploy
```

### 4. 安装Nginx

#### 4.1 安装Nginx
```bash
# 安装Nginx
sudo apt install -y nginx

# 启动并设置开机启动
sudo systemctl start nginx
sudo systemctl enable nginx

# 检查状态
sudo systemctl status nginx
```

#### 4.2 基础配置
```bash
# 备份默认配置
sudo cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup

# 测试配置
sudo nginx -t

# 重载配置
sudo systemctl reload nginx
```

## 代码部署

### 5. 获取项目代码

#### 5.1 克隆代码仓库
```bash
# 切换到部署用户
sudo su - deploy

# 创建项目目录
mkdir -p /var/www
cd /var/www

# 克隆项目（替换为实际仓库地址）
sudo git clone https://github.com/your-username/siege-game.git
sudo chown -R deploy:deploy siege-game
cd siege-game
```

#### 5.2 设置Git配置
```bash
# 配置Git（用于后续更新）
git config --global user.name "Deploy User"
git config --global user.email "deploy@yourdomain.com"

# 设置远程仓库
git remote -v
```

### 6. 项目结构检查
```bash
# 检查项目结构
ls -la

# 应该包含以下文件/目录：
# - package.json
# - server.js 或 app.js
# - public/ (静态文件)
# - deployment/ (部署配置)
```

## 环境配置

### 7. 环境变量配置

#### 7.1 创建生产环境配置
```bash
# 复制环境变量模板
cp deployment/config/.env.production .env

# 编辑环境变量
vim .env
```

#### 7.2 配置内容示例
```bash
# 应用配置
NODE_ENV=production
PORT=8000
HOST=0.0.0.0

# 数据库配置（如果需要）
DB_HOST=localhost
DB_PORT=3306
DB_NAME=siege_game
DB_USER=siege_user
DB_PASSWORD=your_secure_password

# Redis配置（如果需要）
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your_redis_password

# 安全配置
JWT_SECRET=your_jwt_secret_key
SESSION_SECRET=your_session_secret

# 日志配置
LOG_LEVEL=info
LOG_FILE=/var/log/siege-game/app.log

# 其他配置
UPLOAD_PATH=/var/www/siege-game/uploads
MAX_FILE_SIZE=10485760
```

#### 7.3 设置文件权限
```bash
# 设置环境文件权限
chmod 600 .env
chown deploy:deploy .env
```

## 依赖安装

### 8. 安装项目依赖

#### 8.1 安装Node.js依赖
```bash
# 安装生产依赖
npm ci --only=production

# 或者使用yarn（如果项目使用yarn）
# yarn install --production
```

#### 8.2 创建必要目录
```bash
# 创建日志目录
sudo mkdir -p /var/log/siege-game
sudo chown deploy:deploy /var/log/siege-game

# 创建上传目录
mkdir -p uploads
chmod 755 uploads

# 创建临时目录
mkdir -p tmp
chmod 755 tmp
```

#### 8.3 安装系统依赖（如果需要）
```bash
# 如果项目需要额外的系统库
sudo apt install -y \
    imagemagick \
    ffmpeg \
    redis-server \
    mysql-server
```

## 数据库配置

### 9. 数据库设置（如果需要）

#### 9.1 MySQL配置
```bash
# 安装MySQL
sudo apt install -y mysql-server

# 安全配置
sudo mysql_secure_installation

# 创建数据库和用户
sudo mysql -u root -p
```

```sql
-- 在MySQL中执行
CREATE DATABASE siege_game CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'siege_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON siege_game.* TO 'siege_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 9.2 Redis配置
```bash
# 安装Redis
sudo apt install -y redis-server

# 配置Redis
sudo vim /etc/redis/redis.conf

# 修改以下配置：
# bind 127.0.0.1
# requirepass your_redis_password
# maxmemory 256mb
# maxmemory-policy allkeys-lru

# 重启Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server
```

#### 9.3 数据库迁移（如果有）
```bash
# 运行数据库迁移脚本
npm run migrate

# 或者手动执行SQL文件
mysql -u siege_user -p siege_game < database/schema.sql
```

## 服务启动

### 10. 配置PM2

#### 10.1 复制PM2配置
```bash
# 复制PM2配置文件
cp deployment/config/pm2.config.js .

# 编辑配置（如果需要）
vim pm2.config.js
```

#### 10.2 启动应用
```bash
# 使用PM2启动应用
pm2 start pm2.config.js

# 查看状态
pm2 status

# 查看日志
pm2 logs

# 保存PM2配置
pm2 save
```

#### 10.3 验证应用启动
```bash
# 检查应用是否正常运行
curl http://localhost:8000

# 检查进程
ps aux | grep node
```

### 11. 配置Nginx

#### 11.1 复制Nginx配置
```bash
# 复制Nginx配置文件
sudo cp deployment/config/nginx.conf /etc/nginx/sites-available/siege-game

# 创建软链接
sudo ln -s /etc/nginx/sites-available/siege-game /etc/nginx/sites-enabled/

# 删除默认配置
sudo rm /etc/nginx/sites-enabled/default
```

#### 11.2 测试Nginx配置
```bash
# 测试配置语法
sudo nginx -t

# 重载Nginx
sudo systemctl reload nginx
```

#### 11.3 验证Nginx
```bash
# 测试HTTP访问
curl http://your-domain.com

# 检查Nginx状态
sudo systemctl status nginx
```

## 网络配置

### 12. SSL证书配置

#### 12.1 安装Certbot
```bash
# 安装Certbot
sudo apt install -y certbot python3-certbot-nginx
```

#### 12.2 获取SSL证书
```bash
# 获取Let's Encrypt证书
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# 测试自动续期
sudo certbot renew --dry-run
```

#### 12.3 配置自动续期
```bash
# 添加到crontab
sudo crontab -e

# 添加以下行：
0 12 * * * /usr/bin/certbot renew --quiet --post-hook "systemctl reload nginx"
```

### 13. 域名配置

#### 13.1 DNS设置
```bash
# A记录设置
yourdomain.com.     A    your-server-ip
www.yourdomain.com. A    your-server-ip

# CNAME记录（可选）
api.yourdomain.com. CNAME yourdomain.com.
```

#### 13.2 验证DNS解析
```bash
# 检查DNS解析
nslookup yourdomain.com
dig yourdomain.com

# 检查SSL证书
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com
```

## 监控设置

### 14. 配置监控系统

#### 14.1 安装Prometheus
```bash
# 创建prometheus用户
sudo useradd --no-create-home --shell /bin/false prometheus

# 下载Prometheus
cd /tmp
wget https://github.com/prometheus/prometheus/releases/download/v2.40.0/prometheus-2.40.0.linux-amd64.tar.gz
tar xvf prometheus-2.40.0.linux-amd64.tar.gz

# 安装Prometheus
sudo mv prometheus-2.40.0.linux-amd64/prometheus /usr/local/bin/
sudo mv prometheus-2.40.0.linux-amd64/promtool /usr/local/bin/
sudo chown prometheus:prometheus /usr/local/bin/prometheus
sudo chown prometheus:prometheus /usr/local/bin/promtool

# 创建配置目录
sudo mkdir /etc/prometheus
sudo mkdir /var/lib/prometheus
sudo chown prometheus:prometheus /etc/prometheus
sudo chown prometheus:prometheus /var/lib/prometheus

# 复制配置文件
sudo cp /var/www/siege-game/deployment/monitoring/prometheus/prometheus.yml /etc/prometheus/
sudo chown prometheus:prometheus /etc/prometheus/prometheus.yml
```

#### 14.2 创建Prometheus服务
```bash
# 创建systemd服务文件
sudo vim /etc/systemd/system/prometheus.service
```

```ini
[Unit]
Description=Prometheus
Wants=network-online.target
After=network-online.target

[Service]
User=prometheus
Group=prometheus
Type=simple
ExecStart=/usr/local/bin/prometheus \
    --config.file /etc/prometheus/prometheus.yml \
    --storage.tsdb.path /var/lib/prometheus/ \
    --web.console.templates=/etc/prometheus/consoles \
    --web.console.libraries=/etc/prometheus/console_libraries \
    --web.listen-address=0.0.0.0:9090 \
    --web.enable-lifecycle

[Install]
WantedBy=multi-user.target
```

```bash
# 启动Prometheus
sudo systemctl daemon-reload
sudo systemctl start prometheus
sudo systemctl enable prometheus
```

#### 14.3 安装Grafana
```bash
# 添加Grafana仓库
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
echo "deb https://packages.grafana.com/oss/deb stable main" | sudo tee -a /etc/apt/sources.list.d/grafana.list

# 安装Grafana
sudo apt update
sudo apt install -y grafana

# 启动Grafana
sudo systemctl start grafana-server
sudo systemctl enable grafana-server
```

#### 14.4 配置Grafana
```bash
# 访问Grafana Web界面
# http://your-domain.com:3000
# 默认用户名/密码：admin/admin

# 导入仪表板配置
sudo cp /var/www/siege-game/deployment/monitoring/grafana/dashboards/*.json /var/lib/grafana/dashboards/
sudo chown grafana:grafana /var/lib/grafana/dashboards/*.json
```

### 15. 配置健康检查

#### 15.1 设置健康检查脚本
```bash
# 复制健康检查脚本
sudo cp /var/www/siege-game/deployment/health-check.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/health-check.sh

# 测试健康检查
/usr/local/bin/health-check.sh quick
```

#### 15.2 配置监控检查
```bash
# 复制监控检查脚本
sudo cp /var/www/siege-game/deployment/monitoring-check.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/monitoring-check.sh

# 测试监控检查
/usr/local/bin/monitoring-check.sh
```

#### 15.3 设置定时任务
```bash
# 复制cron配置
sudo cp /var/www/siege-game/deployment/cron-backup.conf /etc/cron.d/siege-game-backup
sudo chmod 644 /etc/cron.d/siege-game-backup

# 重启cron服务
sudo systemctl restart cron
```

## 测试验证

### 16. 功能测试

#### 16.1 基础功能测试
```bash
# 测试主页访问
curl -I http://yourdomain.com

# 测试HTTPS访问
curl -I https://yourdomain.com

# 测试API接口（如果有）
curl -X GET https://yourdomain.com/api/health

# 测试静态文件
curl -I https://yourdomain.com/static/css/style.css
```

#### 16.2 性能测试
```bash
# 安装Apache Bench
sudo apt install -y apache2-utils

# 进行压力测试
ab -n 1000 -c 10 https://yourdomain.com/

# 使用wrk进行测试
sudo apt install -y wrk
wrk -t12 -c400 -d30s https://yourdomain.com/
```

#### 16.3 安全测试
```bash
# 检查SSL配置
curl -I https://yourdomain.com | grep -i security

# 检查HTTP头
curl -I https://yourdomain.com

# 使用nmap扫描端口
nmap -sS yourdomain.com
```

### 17. 监控验证

#### 17.1 检查监控指标
```bash
# 访问Prometheus
curl http://localhost:9090/metrics

# 访问Grafana
curl http://localhost:3000/api/health

# 检查应用指标
curl http://localhost:8000/metrics
```

#### 17.2 日志检查
```bash
# 检查应用日志
tail -f /var/log/siege-game/app.log

# 检查Nginx日志
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# 检查系统日志
journalctl -u siege-game -f
```

## 生产优化

### 18. 性能优化

#### 18.1 系统参数优化
```bash
# 编辑系统限制
sudo vim /etc/security/limits.conf

# 添加以下内容：
deploy soft nofile 65536
deploy hard nofile 65536
deploy soft nproc 32768
deploy hard nproc 32768

# 编辑内核参数
sudo vim /etc/sysctl.conf

# 添加以下内容：
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 65535
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 1200
net.ipv4.tcp_max_tw_buckets = 5000

# 应用配置
sudo sysctl -p
```

#### 18.2 Nginx优化
```bash
# 编辑Nginx主配置
sudo vim /etc/nginx/nginx.conf

# 优化worker进程数
worker_processes auto;
worker_connections 1024;

# 启用gzip压缩
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

# 重载配置
sudo systemctl reload nginx
```

#### 18.3 PM2优化
```bash
# 编辑PM2配置
vim pm2.config.js

# 优化实例数量
instances: 'max',  // 使用所有CPU核心
exec_mode: 'cluster',  // 集群模式

# 重启应用
pm2 reload pm2.config.js
```

### 19. 安全加固

#### 19.1 系统安全
```bash
# 禁用root SSH登录
sudo vim /etc/ssh/sshd_config
# PermitRootLogin no
# PasswordAuthentication no

# 重启SSH服务
sudo systemctl restart ssh

# 安装fail2ban
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

#### 19.2 应用安全
```bash
# 设置文件权限
find /var/www/siege-game -type f -exec chmod 644 {} \;
find /var/www/siege-game -type d -exec chmod 755 {} \;
chmod +x /var/www/siege-game/deployment/*.sh

# 保护敏感文件
chmod 600 /var/www/siege-game/.env
```

### 20. 备份配置

#### 20.1 设置自动备份
```bash
# 复制备份脚本
sudo cp /var/www/siege-game/deployment/backup.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/backup.sh

# 测试备份
/usr/local/bin/backup.sh full

# 查看备份文件
ls -la /var/backups/siege-game/
```

#### 20.2 配置回滚机制
```bash
# 复制回滚脚本
sudo cp /var/www/siege-game/deployment/rollback.sh /usr/local/bin/
sudo chmod +x /usr/local/bin/rollback.sh

# 测试回滚（不要在生产环境执行）
# /usr/local/bin/rollback.sh --list
```

## 部署完成检查清单

### 21. 最终验证

- [ ] 应用正常启动并响应请求
- [ ] HTTPS证书配置正确
- [ ] 防火墙规则配置完成
- [ ] 监控系统正常运行
- [ ] 日志收集配置完成
- [ ] 备份机制设置完成
- [ ] 性能测试通过
- [ ] 安全检查通过
- [ ] 文档更新完成

### 22. 后续维护

#### 22.1 定期检查项目
- 每日：检查应用状态、查看监控指标
- 每周：检查日志、执行性能测试
- 每月：更新系统补丁、检查备份完整性

#### 22.2 应急响应
- 准备应急联系方式
- 制定故障处理流程
- 定期演练回滚操作

---

**部署完成！** 🎉

如果在部署过程中遇到问题，请参考 [TROUBLESHOOTING.md](TROUBLESHOOTING.md) 故障排除指南。