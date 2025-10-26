# 《攻城掠地》桌游项目 - 故障排除指南

## 目录
1. [常见问题诊断](#常见问题诊断)
2. [应用服务问题](#应用服务问题)
3. [Nginx问题](#nginx问题)
4. [数据库问题](#数据库问题)
5. [性能问题](#性能问题)
6. [网络问题](#网络问题)
7. [SSL证书问题](#ssl证书问题)
8. [监控问题](#监控问题)
9. [日志分析](#日志分析)
10. [应急处理](#应急处理)

## 常见问题诊断

### 1. 快速诊断工具

#### 1.1 健康检查脚本
```bash
# 执行完整健康检查
/usr/local/bin/health-check.sh full

# 快速检查
/usr/local/bin/health-check.sh quick

# 检查特定组件
/usr/local/bin/health-check.sh app
/usr/local/bin/health-check.sh system
```

#### 1.2 服务状态检查
```bash
# 检查所有相关服务状态
sudo systemctl status nginx
sudo systemctl status prometheus
sudo systemctl status grafana-server
pm2 status

# 检查端口占用
sudo netstat -tlnp | grep -E ':(80|443|8000|3000|9090)'
sudo ss -tlnp | grep -E ':(80|443|8000|3000|9090)'
```

#### 1.3 资源使用检查
```bash
# 检查CPU和内存
top
htop
free -h
df -h

# 检查磁盘I/O
iostat -x 1
iotop

# 检查网络
iftop
netstat -i
```

### 2. 日志查看命令

#### 2.1 应用日志
```bash
# 实时查看应用日志
tail -f /var/log/siege-game/app.log

# 查看PM2日志
pm2 logs
pm2 logs siege-game

# 查看最近的错误
grep -i error /var/log/siege-game/app.log | tail -20
```

#### 2.2 系统日志
```bash
# 查看系统日志
journalctl -f
journalctl -u nginx
journalctl -u prometheus

# 查看Nginx日志
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log
```

## 应用服务问题

### 3. 应用无法启动

#### 3.1 症状
- PM2显示应用状态为stopped或errored
- 无法访问应用端口8000
- 应用日志显示启动错误

#### 3.2 诊断步骤
```bash
# 1. 检查PM2状态
pm2 status

# 2. 查看详细错误信息
pm2 logs siege-game --lines 50

# 3. 检查应用配置
cat /var/www/siege-game/.env

# 4. 检查端口占用
sudo lsof -i :8000

# 5. 手动启动测试
cd /var/www/siege-game
node server.js
```

#### 3.3 常见原因和解决方案

**原因1：端口被占用**
```bash
# 查找占用进程
sudo lsof -i :8000
sudo kill -9 <PID>

# 或者修改应用端口
vim .env
# PORT=8001
pm2 restart siege-game
```

**原因2：环境变量错误**
```bash
# 检查环境变量
cat .env

# 常见错误：
# - 数据库连接信息错误
# - 缺少必要的环境变量
# - 路径配置错误

# 修复示例
vim .env
# 确保所有必要变量都已设置
pm2 restart siege-game
```

**原因3：依赖包问题**
```bash
# 重新安装依赖
cd /var/www/siege-game
rm -rf node_modules package-lock.json
npm install --production

# 检查Node.js版本兼容性
node --version
npm --version
```

**原因4：文件权限问题**
```bash
# 修复文件权限
sudo chown -R deploy:deploy /var/www/siege-game
chmod 644 /var/www/siege-game/.env
chmod +x /var/www/siege-game/server.js
```

### 4. 应用响应缓慢

#### 4.1 症状
- 页面加载时间超过5秒
- API响应时间过长
- 用户反馈系统卡顿

#### 4.2 诊断步骤
```bash
# 1. 检查应用响应时间
curl -w "@curl-format.txt" -o /dev/null -s "http://localhost:8000"

# 创建curl-format.txt
cat > curl-format.txt << 'EOF'
     time_namelookup:  %{time_namelookup}\n
        time_connect:  %{time_connect}\n
     time_appconnect:  %{time_appconnect}\n
    time_pretransfer:  %{time_pretransfer}\n
       time_redirect:  %{time_redirect}\n
  time_starttransfer:  %{time_starttransfer}\n
                     ----------\n
          time_total:  %{time_total}\n
EOF

# 2. 检查系统资源
top -p $(pgrep -f "node.*server.js")
free -h
iostat -x 1 5

# 3. 检查数据库连接（如果使用）
mysql -u siege_user -p -e "SHOW PROCESSLIST;"
redis-cli info stats
```

#### 4.3 解决方案

**CPU使用率过高**
```bash
# 增加PM2实例数
pm2 scale siege-game +2

# 或者修改PM2配置
vim pm2.config.js
# instances: 'max'  // 使用所有CPU核心
pm2 reload pm2.config.js
```

**内存不足**
```bash
# 检查内存使用
free -h
pm2 monit

# 增加系统内存或优化应用
# 重启应用释放内存
pm2 restart siege-game
```

**数据库查询慢**
```bash
# MySQL慢查询日志
sudo vim /etc/mysql/mysql.conf.d/mysqld.cnf
# slow_query_log = 1
# slow_query_log_file = /var/log/mysql/slow.log
# long_query_time = 2

sudo systemctl restart mysql
tail -f /var/log/mysql/slow.log
```

### 5. 内存泄漏问题

#### 5.1 症状
- 应用内存使用持续增长
- 系统可用内存逐渐减少
- 应用最终崩溃或被系统杀死

#### 5.2 诊断步骤
```bash
# 1. 监控内存使用趋势
pm2 monit

# 2. 使用Node.js内存分析
node --inspect server.js
# 然后在Chrome中打开 chrome://inspect

# 3. 检查系统内存
cat /proc/meminfo
vmstat 1 10
```

#### 5.3 解决方案
```bash
# 1. 设置Node.js内存限制
vim pm2.config.js
# node_args: '--max-old-space-size=2048'

# 2. 定期重启应用
pm2 install pm2-auto-pull
# 或者设置cron任务定期重启

# 3. 代码层面修复
# - 检查事件监听器泄漏
# - 检查定时器清理
# - 检查闭包引用
```

## Nginx问题

### 6. Nginx无法启动

#### 6.1 症状
- systemctl status nginx显示failed
- 无法访问80/443端口
- nginx -t显示配置错误

#### 6.2 诊断步骤
```bash
# 1. 检查配置语法
sudo nginx -t

# 2. 查看详细错误
sudo systemctl status nginx -l
journalctl -u nginx --no-pager

# 3. 检查端口占用
sudo lsof -i :80
sudo lsof -i :443
```

#### 6.3 常见问题解决

**配置语法错误**
```bash
# 检查配置文件
sudo nginx -t

# 常见错误：
# - 缺少分号
# - 括号不匹配
# - 指令拼写错误

# 恢复备份配置
sudo cp /etc/nginx/nginx.conf.backup /etc/nginx/nginx.conf
sudo systemctl restart nginx
```

**端口被占用**
```bash
# 查找占用进程
sudo lsof -i :80
sudo kill -9 <PID>

# 或者修改Nginx端口
sudo vim /etc/nginx/sites-available/siege-game
# listen 8080;
sudo systemctl restart nginx
```

**SSL证书问题**
```bash
# 检查证书文件
sudo ls -la /etc/letsencrypt/live/yourdomain.com/

# 重新生成证书
sudo certbot --nginx -d yourdomain.com --force-renewal

# 临时禁用SSL
sudo vim /etc/nginx/sites-available/siege-game
# 注释掉SSL相关配置
sudo nginx -t && sudo systemctl reload nginx
```

### 7. Nginx 502 Bad Gateway

#### 7.1 症状
- 浏览器显示502错误
- Nginx错误日志显示upstream错误
- 应用服务正常但无法通过Nginx访问

#### 7.2 诊断步骤
```bash
# 1. 检查上游服务
curl http://localhost:8000

# 2. 查看Nginx错误日志
tail -f /var/log/nginx/error.log

# 3. 检查Nginx配置
sudo nginx -t
cat /etc/nginx/sites-available/siege-game
```

#### 7.3 解决方案
```bash
# 1. 检查upstream配置
sudo vim /etc/nginx/sites-available/siege-game

# 确保upstream地址正确
upstream siege_game {
    server 127.0.0.1:8000;  # 确保端口正确
}

# 2. 检查应用是否监听正确地址
netstat -tlnp | grep 8000

# 3. 重启服务
pm2 restart siege-game
sudo systemctl reload nginx
```

## 数据库问题

### 8. 数据库连接失败

#### 8.1 症状
- 应用日志显示数据库连接错误
- 数据库相关功能无法使用
- 连接超时错误

#### 8.2 MySQL问题诊断
```bash
# 1. 检查MySQL服务状态
sudo systemctl status mysql

# 2. 测试连接
mysql -u siege_user -p -h localhost siege_game

# 3. 检查用户权限
mysql -u root -p
```

```sql
-- 检查用户权限
SELECT User, Host FROM mysql.user WHERE User = 'siege_user';
SHOW GRANTS FOR 'siege_user'@'localhost';

-- 重新授权
GRANT ALL PRIVILEGES ON siege_game.* TO 'siege_user'@'localhost';
FLUSH PRIVILEGES;
```

#### 8.3 Redis问题诊断
```bash
# 1. 检查Redis服务
sudo systemctl status redis-server

# 2. 测试连接
redis-cli ping

# 3. 检查配置
sudo vim /etc/redis/redis.conf

# 4. 查看Redis日志
sudo tail -f /var/log/redis/redis-server.log
```

### 9. 数据库性能问题

#### 9.1 MySQL性能优化
```bash
# 1. 启用慢查询日志
sudo vim /etc/mysql/mysql.conf.d/mysqld.cnf

# 添加以下配置：
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# 2. 重启MySQL
sudo systemctl restart mysql

# 3. 分析慢查询
sudo mysqldumpslow /var/log/mysql/slow.log

# 4. 检查索引使用
mysql -u siege_user -p siege_game
```

```sql
-- 分析查询执行计划
EXPLAIN SELECT * FROM your_table WHERE condition;

-- 检查索引
SHOW INDEX FROM your_table;

-- 创建索引
CREATE INDEX idx_column_name ON your_table(column_name);
```

#### 9.2 Redis性能优化
```bash
# 1. 检查Redis信息
redis-cli info memory
redis-cli info stats

# 2. 监控Redis性能
redis-cli monitor

# 3. 优化配置
sudo vim /etc/redis/redis.conf

# 关键配置：
# maxmemory 256mb
# maxmemory-policy allkeys-lru
# save 900 1
# save 300 10
# save 60 10000
```

## 性能问题

### 10. 高负载问题

#### 10.1 症状
- 系统负载平均值持续高于CPU核心数
- 响应时间明显增加
- 用户体验下降

#### 10.2 诊断步骤
```bash
# 1. 检查系统负载
uptime
top
htop

# 2. 分析负载来源
# CPU密集型
top -o %CPU

# I/O密集型
iostat -x 1
iotop

# 内存问题
free -h
vmstat 1

# 3. 检查进程状态
ps aux --sort=-%cpu | head -10
ps aux --sort=-%mem | head -10
```

#### 10.3 解决方案

**CPU负载高**
```bash
# 1. 增加PM2实例
pm2 scale siege-game +2

# 2. 优化代码
# - 减少CPU密集型操作
# - 使用异步处理
# - 添加缓存

# 3. 升级硬件
# - 增加CPU核心数
# - 使用更快的CPU
```

**I/O负载高**
```bash
# 1. 优化数据库查询
# - 添加索引
# - 优化查询语句
# - 使用连接池

# 2. 使用SSD存储
# 3. 增加内存缓存
```

### 11. 内存不足问题

#### 11.1 症状
- 系统可用内存不足
- 出现OOM Killer
- 应用被系统杀死

#### 11.2 诊断步骤
```bash
# 1. 检查内存使用
free -h
cat /proc/meminfo

# 2. 查看内存使用排行
ps aux --sort=-%mem | head -10

# 3. 检查OOM日志
dmesg | grep -i "killed process"
journalctl | grep -i "out of memory"

# 4. 分析内存分布
cat /proc/meminfo
slabtop
```

#### 11.3 解决方案
```bash
# 1. 增加系统内存
# 2. 优化应用内存使用
vim pm2.config.js
# node_args: '--max-old-space-size=1024'

# 3. 启用swap（临时解决）
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# 4. 添加到fstab
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

## 网络问题

### 12. 网络连接问题

#### 12.1 症状
- 无法访问网站
- 连接超时
- 间歇性网络中断

#### 12.2 诊断步骤
```bash
# 1. 检查网络接口
ip addr show
ifconfig

# 2. 检查路由
ip route show
route -n

# 3. 测试网络连通性
ping google.com
ping 8.8.8.8

# 4. 检查DNS解析
nslookup yourdomain.com
dig yourdomain.com

# 5. 检查防火墙
sudo ufw status
sudo iptables -L
```

#### 12.3 解决方案

**DNS解析问题**
```bash
# 1. 检查DNS配置
cat /etc/resolv.conf

# 2. 修改DNS服务器
sudo vim /etc/resolv.conf
# nameserver 8.8.8.8
# nameserver 8.8.4.4

# 3. 刷新DNS缓存
sudo systemctl restart systemd-resolved
```

**防火墙问题**
```bash
# 1. 检查UFW规则
sudo ufw status numbered

# 2. 添加必要规则
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# 3. 检查iptables
sudo iptables -L -n
```

### 13. CDN和缓存问题

#### 13.1 静态资源缓存
```bash
# 1. 检查Nginx缓存配置
sudo vim /etc/nginx/sites-available/siege-game

# 添加缓存头
location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}

# 2. 重载Nginx
sudo nginx -t && sudo systemctl reload nginx
```

#### 13.2 应用缓存
```bash
# 1. 检查Redis缓存
redis-cli info memory
redis-cli keys "*"

# 2. 清理缓存
redis-cli flushall

# 3. 重启应用缓存
pm2 restart siege-game
```

## SSL证书问题

### 14. SSL证书错误

#### 14.1 症状
- 浏览器显示证书错误
- HTTPS无法访问
- 证书过期警告

#### 14.2 诊断步骤
```bash
# 1. 检查证书状态
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com

# 2. 检查证书文件
sudo ls -la /etc/letsencrypt/live/yourdomain.com/

# 3. 检查证书有效期
openssl x509 -in /etc/letsencrypt/live/yourdomain.com/cert.pem -text -noout | grep -A 2 "Validity"

# 4. 测试证书链
openssl s_client -connect yourdomain.com:443 -showcerts
```

#### 14.3 解决方案

**证书过期**
```bash
# 1. 手动续期
sudo certbot renew --force-renewal

# 2. 检查自动续期
sudo certbot renew --dry-run

# 3. 重启Nginx
sudo systemctl reload nginx
```

**证书配置错误**
```bash
# 1. 重新生成证书
sudo certbot --nginx -d yourdomain.com

# 2. 检查Nginx配置
sudo vim /etc/nginx/sites-available/siege-game

# 确保SSL配置正确：
ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
```

## 监控问题

### 15. Prometheus问题

#### 15.1 Prometheus无法启动
```bash
# 1. 检查服务状态
sudo systemctl status prometheus

# 2. 查看日志
journalctl -u prometheus --no-pager

# 3. 检查配置
sudo /usr/local/bin/promtool check config /etc/prometheus/prometheus.yml

# 4. 检查权限
sudo chown -R prometheus:prometheus /etc/prometheus
sudo chown -R prometheus:prometheus /var/lib/prometheus
```

#### 15.2 指标收集问题
```bash
# 1. 检查目标状态
curl http://localhost:9090/api/v1/targets

# 2. 测试应用指标端点
curl http://localhost:8000/metrics

# 3. 检查网络连通性
telnet localhost 8000
```

### 16. Grafana问题

#### 16.1 Grafana无法访问
```bash
# 1. 检查服务状态
sudo systemctl status grafana-server

# 2. 查看日志
sudo tail -f /var/log/grafana/grafana.log

# 3. 检查端口
sudo lsof -i :3000

# 4. 重置管理员密码
sudo grafana-cli admin reset-admin-password admin
```

#### 16.2 数据源连接问题
```bash
# 1. 测试Prometheus连接
curl http://localhost:9090/api/v1/query?query=up

# 2. 检查Grafana配置
sudo vim /etc/grafana/grafana.ini

# 3. 重启Grafana
sudo systemctl restart grafana-server
```

## 日志分析

### 17. 日志分析技巧

#### 17.1 应用日志分析
```bash
# 1. 查看错误日志
grep -i error /var/log/siege-game/app.log | tail -20

# 2. 统计错误类型
grep -i error /var/log/siege-game/app.log | awk '{print $4}' | sort | uniq -c

# 3. 查看特定时间段日志
sed -n '/2024-01-01 10:00/,/2024-01-01 11:00/p' /var/log/siege-game/app.log

# 4. 实时监控错误
tail -f /var/log/siege-game/app.log | grep -i error
```

#### 17.2 Nginx日志分析
```bash
# 1. 分析访问日志
tail -f /var/log/nginx/access.log

# 2. 统计状态码
awk '{print $9}' /var/log/nginx/access.log | sort | uniq -c

# 3. 查找慢请求
awk '$NF > 1 {print $0}' /var/log/nginx/access.log

# 4. 分析IP访问
awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -nr | head -10
```

#### 17.3 系统日志分析
```bash
# 1. 查看系统错误
journalctl -p err --no-pager

# 2. 查看特定服务日志
journalctl -u nginx --since "1 hour ago"

# 3. 查看内核日志
dmesg | tail -20

# 4. 查看认证日志
sudo tail -f /var/log/auth.log
```

## 应急处理

### 18. 紧急故障处理

#### 18.1 服务完全不可用
```bash
# 1. 立即检查
./health-check.sh quick

# 2. 重启所有服务
sudo systemctl restart nginx
pm2 restart all
sudo systemctl restart redis-server

# 3. 检查系统资源
free -h
df -h
top

# 4. 如果问题严重，执行回滚
./rollback.sh
```

#### 18.2 数据库故障
```bash
# 1. 检查数据库状态
sudo systemctl status mysql
sudo systemctl status redis-server

# 2. 尝试重启数据库
sudo systemctl restart mysql
sudo systemctl restart redis-server

# 3. 检查数据完整性
mysql -u root -p -e "CHECK TABLE siege_game.table_name;"

# 4. 如果数据损坏，从备份恢复
./rollback.sh --database-only
```

#### 18.3 安全事件处理
```bash
# 1. 检查异常登录
sudo tail -f /var/log/auth.log | grep -i failed

# 2. 检查网络连接
sudo netstat -tulnp | grep ESTABLISHED

# 3. 临时阻止可疑IP
sudo ufw deny from suspicious_ip

# 4. 检查文件完整性
find /var/www/siege-game -type f -name "*.js" -exec ls -la {} \;
```

### 19. 性能紧急优化

#### 19.1 快速减负
```bash
# 1. 增加应用实例
pm2 scale siege-game +2

# 2. 启用Nginx缓存
sudo vim /etc/nginx/sites-available/siege-game
# 添加缓存配置
sudo systemctl reload nginx

# 3. 清理系统缓存
sudo sync
echo 3 | sudo tee /proc/sys/vm/drop_caches

# 4. 临时增加swap
sudo fallocate -l 1G /tmp/swapfile
sudo chmod 600 /tmp/swapfile
sudo mkswap /tmp/swapfile
sudo swapon /tmp/swapfile
```

### 20. 联系支持

#### 20.1 收集诊断信息
```bash
# 创建诊断报告
cat > /tmp/diagnostic-report.txt << EOF
=== 系统信息 ===
$(uname -a)
$(lsb_release -a)
$(uptime)

=== 服务状态 ===
$(sudo systemctl status nginx --no-pager)
$(pm2 status)
$(sudo systemctl status mysql --no-pager)

=== 资源使用 ===
$(free -h)
$(df -h)
$(top -bn1 | head -20)

=== 网络状态 ===
$(sudo netstat -tlnp | grep -E ':(80|443|8000|3000|9090)')

=== 最近错误 ===
$(tail -20 /var/log/siege-game/app.log)
$(tail -20 /var/log/nginx/error.log)
EOF

# 发送报告
mail -s "Siege Game Emergency Report" tech-support@yourdomain.com < /tmp/diagnostic-report.txt
```

#### 20.2 紧急联系方式
- **技术支持**: tech-support@yourdomain.com
- **运维支持**: ops@yourdomain.com  
- **紧急热线**: +86-xxx-xxxx-xxxx
- **Slack频道**: #siege-game-support

---

## 预防措施

### 21. 定期维护检查清单

#### 21.1 每日检查
- [ ] 执行健康检查脚本
- [ ] 查看监控指标
- [ ] 检查错误日志
- [ ] 验证备份完成

#### 21.2 每周检查  
- [ ] 更新系统补丁
- [ ] 检查磁盘空间
- [ ] 分析性能趋势
- [ ] 测试回滚流程

#### 21.3 每月检查
- [ ] 更新SSL证书
- [ ] 检查安全日志
- [ ] 优化数据库
- [ ] 更新文档

记住：**预防胜于治疗**。定期维护和监控可以避免大多数紧急故障。