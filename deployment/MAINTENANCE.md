# 《攻城掠地》桌游项目 - 维护操作手册

## 目录
1. [日常维护](#日常维护)
2. [定期维护](#定期维护)
3. [系统更新](#系统更新)
4. [备份管理](#备份管理)
5. [性能优化](#性能优化)
6. [安全维护](#安全维护)
7. [监控管理](#监控管理)
8. [日志管理](#日志管理)
9. [应急响应](#应急响应)
10. [维护记录](#维护记录)

## 日常维护

### 1. 每日检查清单

#### 1.1 系统健康检查
```bash
# 执行完整健康检查
/usr/local/bin/health-check.sh full

# 检查关键服务状态
sudo systemctl status nginx
sudo systemctl status mysql
sudo systemctl status redis-server
pm2 status

# 检查系统资源
free -h
df -h
uptime
```

#### 1.2 应用状态检查
```bash
# 检查应用响应
curl -I https://yourdomain.com
curl -I http://localhost:8000

# 检查PM2进程
pm2 monit
pm2 logs --lines 50

# 检查错误日志
tail -20 /var/log/siege-game/app.log | grep -i error
tail -20 /var/log/nginx/error.log
```

#### 1.3 监控指标检查
```bash
# 访问Grafana仪表板
# https://yourdomain.com:3000

# 检查关键指标：
# - CPU使用率 < 80%
# - 内存使用率 < 85%
# - 磁盘使用率 < 90%
# - 响应时间 < 2秒
# - 错误率 < 1%

# 命令行检查Prometheus指标
curl -s http://localhost:9090/api/v1/query?query=up | jq
```

#### 1.4 备份验证
```bash
# 检查昨日备份
ls -la /var/backups/siege-game/ | tail -5

# 验证备份完整性
/usr/local/bin/backup.sh verify

# 检查备份日志
tail -20 /var/log/siege-game/backup/backup.log
```

### 2. 每日维护脚本

创建每日维护自动化脚本：

```bash
# 创建每日维护脚本
sudo vim /usr/local/bin/daily-maintenance.sh
```

```bash
#!/bin/bash

# 《攻城掠地》桌游项目 - 每日维护脚本

set -euo pipefail

LOG_FILE="/var/log/siege-game/maintenance.log"
DATE=$(date '+%Y-%m-%d %H:%M:%S')

log() {
    echo "[$DATE] $1" | tee -a "$LOG_FILE"
}

# 1. 健康检查
log "开始每日健康检查..."
if /usr/local/bin/health-check.sh quick; then
    log "健康检查通过"
else
    log "健康检查失败，需要人工介入"
    # 发送告警邮件
    echo "健康检查失败，请立即检查系统状态" | mail -s "[ALERT] Siege Game Health Check Failed" admin@yourdomain.com
fi

# 2. 清理临时文件
log "清理临时文件..."
find /tmp -name "siege-game-*" -mtime +1 -delete 2>/dev/null || true
find /var/www/siege-game/tmp -name "*" -mtime +1 -delete 2>/dev/null || true

# 3. 检查磁盘空间
log "检查磁盘空间..."
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 85 ]; then
    log "警告：磁盘使用率达到 ${DISK_USAGE}%"
    echo "磁盘使用率达到 ${DISK_USAGE}%，请及时清理" | mail -s "[WARNING] High Disk Usage" admin@yourdomain.com
fi

# 4. 检查内存使用
log "检查内存使用..."
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
if (( $(echo "$MEMORY_USAGE > 90" | bc -l) )); then
    log "警告：内存使用率达到 ${MEMORY_USAGE}%"
    echo "内存使用率达到 ${MEMORY_USAGE}%，可能需要重启应用" | mail -s "[WARNING] High Memory Usage" admin@yourdomain.com
fi

# 5. 检查SSL证书
log "检查SSL证书..."
CERT_DAYS=$(echo | openssl s_client -servername yourdomain.com -connect yourdomain.com:443 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2 | xargs -I {} date -d {} +%s)
CURRENT_DAYS=$(date +%s)
DAYS_LEFT=$(( (CERT_DAYS - CURRENT_DAYS) / 86400 ))

if [ "$DAYS_LEFT" -lt 30 ]; then
    log "警告：SSL证书将在 ${DAYS_LEFT} 天后过期"
    echo "SSL证书将在 ${DAYS_LEFT} 天后过期，请及时续期" | mail -s "[WARNING] SSL Certificate Expiring" admin@yourdomain.com
fi

# 6. 生成每日报告
log "生成每日报告..."
cat > /tmp/daily-report.txt << EOF
《攻城掠地》桌游项目 - 每日维护报告
日期：$(date '+%Y-%m-%d')

=== 系统状态 ===
CPU负载：$(uptime | awk -F'load average:' '{print $2}')
内存使用：${MEMORY_USAGE}%
磁盘使用：${DISK_USAGE}%
SSL证书：还有 ${DAYS_LEFT} 天过期

=== 服务状态 ===
$(pm2 jlist | jq -r '.[] | "\(.name): \(.pm2_env.status)"')

=== 今日访问统计 ===
总访问量：$(grep "$(date '+%d/%b/%Y')" /var/log/nginx/access.log | wc -l)
错误请求：$(grep "$(date '+%d/%b/%Y')" /var/log/nginx/access.log | grep -E " [45][0-9][0-9] " | wc -l)

=== 备份状态 ===
最新备份：$(ls -t /var/backups/siege-game/ | head -1)
备份大小：$(du -sh /var/backups/siege-game/ | cut -f1)
EOF

# 发送每日报告（可选）
# mail -s "Siege Game Daily Report - $(date '+%Y-%m-%d')" admin@yourdomain.com < /tmp/daily-report.txt

log "每日维护完成"
```

```bash
# 设置执行权限
sudo chmod +x /usr/local/bin/daily-maintenance.sh

# 添加到crontab
sudo crontab -e
# 添加：0 6 * * * /usr/local/bin/daily-maintenance.sh
```

## 定期维护

### 3. 每周维护清单

#### 3.1 系统更新检查
```bash
# 检查可用更新
sudo apt update
sudo apt list --upgradable

# 更新安全补丁
sudo apt upgrade -y

# 检查需要重启的服务
sudo checkrestart
```

#### 3.2 性能分析
```bash
# 分析一周的性能数据
# 在Grafana中查看：
# - 平均响应时间趋势
# - 错误率趋势  
# - 资源使用趋势
# - 用户访问模式

# 生成性能报告
curl -s "http://localhost:9090/api/v1/query_range?query=avg_over_time(http_request_duration_seconds[7d])&start=$(date -d '7 days ago' +%s)&end=$(date +%s)&step=3600" | jq
```

#### 3.3 日志分析
```bash
# 分析一周的错误日志
grep -i error /var/log/siege-game/app.log | grep "$(date -d '7 days ago' '+%Y-%m-%d')" | wc -l

# 分析访问模式
awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -nr | head -20

# 分析慢请求
awk '$NF > 2 {print $0}' /var/log/nginx/access.log | tail -20
```

#### 3.4 安全检查
```bash
# 检查失败登录尝试
sudo grep "Failed password" /var/log/auth.log | tail -20

# 检查异常网络连接
sudo netstat -tulnp | grep ESTABLISHED | wc -l

# 检查文件权限
find /var/www/siege-game -type f -perm /o+w -ls

# 更新fail2ban规则
sudo fail2ban-client status
sudo fail2ban-client status sshd
```

### 4. 每月维护清单

#### 4.1 深度系统清理
```bash
# 清理包缓存
sudo apt autoremove -y
sudo apt autoclean

# 清理日志文件
sudo journalctl --vacuum-time=30d

# 清理临时文件
sudo find /tmp -type f -atime +30 -delete
sudo find /var/tmp -type f -atime +30 -delete

# 清理旧的备份文件
find /var/backups/siege-game -name "*.tar.gz" -mtime +90 -delete
```

#### 4.2 数据库维护
```bash
# MySQL优化
mysql -u root -p << EOF
USE siege_game;
OPTIMIZE TABLE table_name;
ANALYZE TABLE table_name;
CHECK TABLE table_name;
EOF

# Redis内存优化
redis-cli BGREWRITEAOF
redis-cli info memory
```

#### 4.3 SSL证书续期
```bash
# 检查证书状态
sudo certbot certificates

# 测试续期
sudo certbot renew --dry-run

# 强制续期（如果需要）
sudo certbot renew --force-renewal
```

## 系统更新

### 5. 应用更新流程

#### 5.1 准备更新
```bash
# 1. 创建更新前备份
/usr/local/bin/backup.sh full

# 2. 检查当前版本
cd /var/www/siege-game
git log -1 --oneline

# 3. 检查系统状态
/usr/local/bin/health-check.sh full
```

#### 5.2 执行更新
```bash
# 1. 拉取最新代码
git fetch origin
git checkout main
git pull origin main

# 2. 检查依赖变化
npm audit
npm ci --only=production

# 3. 运行数据库迁移（如果有）
npm run migrate

# 4. 重启应用
pm2 reload siege-game

# 5. 验证更新
curl -I https://yourdomain.com
/usr/local/bin/health-check.sh app
```

#### 5.3 回滚计划
```bash
# 如果更新失败，立即回滚
/usr/local/bin/rollback.sh

# 检查回滚后状态
/usr/local/bin/health-check.sh full
```

### 6. 系统软件更新

#### 6.1 Node.js更新
```bash
# 检查当前版本
node --version

# 更新到新的LTS版本
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
sudo apt-get install -y nodejs

# 重新安装全局包
sudo npm install -g pm2@latest

# 重启应用
pm2 update
pm2 restart siege-game
```

#### 6.2 Nginx更新
```bash
# 备份配置
sudo cp -r /etc/nginx /etc/nginx.backup.$(date +%Y%m%d)

# 更新Nginx
sudo apt update
sudo apt upgrade nginx

# 测试配置
sudo nginx -t

# 重启服务
sudo systemctl restart nginx
```

#### 6.3 数据库更新
```bash
# MySQL更新
sudo apt update
sudo apt upgrade mysql-server

# 检查数据库完整性
mysql -u root -p -e "CHECK TABLE mysql.user;"

# Redis更新
sudo apt update
sudo apt upgrade redis-server
sudo systemctl restart redis-server
```

## 备份管理

### 7. 备份策略

#### 7.1 备份类型和频率
- **完整备份**: 每日凌晨2点
- **增量备份**: 每6小时
- **配置备份**: 每小时
- **数据库备份**: 每周日凌晨1点

#### 7.2 备份验证
```bash
# 每周验证备份完整性
/usr/local/bin/backup.sh verify

# 测试恢复流程（在测试环境）
/usr/local/bin/rollback.sh --test-mode

# 检查备份存储空间
du -sh /var/backups/siege-game/
df -h /var/backups/
```

#### 7.3 备份清理策略
```bash
# 自动清理旧备份
find /var/backups/siege-game -name "daily-*" -mtime +30 -delete
find /var/backups/siege-game -name "weekly-*" -mtime +90 -delete
find /var/backups/siege-game -name "monthly-*" -mtime +365 -delete
```

### 8. 异地备份

#### 8.1 配置云存储备份
```bash
# 安装rclone
curl https://rclone.org/install.sh | sudo bash

# 配置云存储
rclone config

# 创建同步脚本
cat > /usr/local/bin/cloud-backup.sh << 'EOF'
#!/bin/bash
# 同步备份到云存储
rclone sync /var/backups/siege-game/ remote:siege-game-backups/
EOF

chmod +x /usr/local/bin/cloud-backup.sh

# 添加到crontab
# 0 4 * * * /usr/local/bin/cloud-backup.sh
```

## 性能优化

### 9. 定期性能优化

#### 9.1 数据库优化
```bash
# MySQL性能调优
mysql -u root -p << EOF
-- 查看慢查询
SELECT * FROM mysql.slow_log ORDER BY start_time DESC LIMIT 10;

-- 优化表
OPTIMIZE TABLE siege_game.table_name;

-- 更新统计信息
ANALYZE TABLE siege_game.table_name;

-- 检查索引使用
SHOW INDEX FROM siege_game.table_name;
EOF

# Redis优化
redis-cli info memory
redis-cli config get maxmemory-policy
redis-cli config set maxmemory-policy allkeys-lru
```

#### 9.2 应用性能优化
```bash
# 分析Node.js性能
pm2 monit

# 检查内存泄漏
node --inspect server.js &
# 在Chrome中打开 chrome://inspect

# 优化PM2配置
vim pm2.config.js
# 调整实例数量和内存限制
pm2 reload pm2.config.js
```

#### 9.3 系统性能优化
```bash
# 调整系统参数
sudo vim /etc/sysctl.conf

# 网络优化
net.core.somaxconn = 65535
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 65535

# 文件描述符限制
sudo vim /etc/security/limits.conf
deploy soft nofile 65536
deploy hard nofile 65536

# 应用配置
sudo sysctl -p
```

## 安全维护

### 10. 安全检查和加固

#### 10.1 定期安全扫描
```bash
# 检查开放端口
nmap -sS localhost

# 检查系统漏洞
sudo apt install lynis
sudo lynis audit system

# 检查文件完整性
sudo aide --check
```

#### 10.2 访问日志分析
```bash
# 分析异常访问
awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -nr | head -20

# 检查404错误
grep " 404 " /var/log/nginx/access.log | tail -20

# 检查可疑请求
grep -E "(union|select|script|alert)" /var/log/nginx/access.log
```

#### 10.3 防火墙维护
```bash
# 检查防火墙规则
sudo ufw status numbered

# 更新fail2ban配置
sudo vim /etc/fail2ban/jail.local

# 重启fail2ban
sudo systemctl restart fail2ban
sudo fail2ban-client status
```

### 11. 密码和密钥管理

#### 11.1 定期更换密码
```bash
# 更换数据库密码
mysql -u root -p << EOF
ALTER USER 'siege_user'@'localhost' IDENTIFIED BY 'new_secure_password';
FLUSH PRIVILEGES;
EOF

# 更新应用配置
vim /var/www/siege-game/.env
# DB_PASSWORD=new_secure_password

# 重启应用
pm2 restart siege-game
```

#### 11.2 SSH密钥管理
```bash
# 检查SSH密钥
ls -la ~/.ssh/

# 生成新的SSH密钥（如果需要）
ssh-keygen -t rsa -b 4096 -C "deploy@yourdomain.com"

# 更新authorized_keys
vim ~/.ssh/authorized_keys
```

## 监控管理

### 12. 监控系统维护

#### 12.1 Prometheus维护
```bash
# 检查Prometheus存储
du -sh /var/lib/prometheus/

# 清理旧数据
prometheus --storage.tsdb.retention.time=30d

# 更新配置
sudo vim /etc/prometheus/prometheus.yml
sudo systemctl reload prometheus
```

#### 12.2 Grafana维护
```bash
# 备份Grafana配置
sudo cp -r /var/lib/grafana /var/backups/grafana.$(date +%Y%m%d)

# 更新仪表板
# 通过Web界面导入新的仪表板配置

# 清理旧数据
sudo grafana-cli admin data-migration cleanup
```

#### 12.3 告警规则维护
```bash
# 更新告警规则
sudo vim /etc/prometheus/alert_rules.yml

# 验证规则语法
promtool check rules /etc/prometheus/alert_rules.yml

# 重载配置
curl -X POST http://localhost:9090/-/reload
```

## 日志管理

### 13. 日志轮转和清理

#### 13.1 配置日志轮转
```bash
# 检查logrotate配置
sudo vim /etc/logrotate.d/siege-game

# 手动执行日志轮转
sudo logrotate -f /etc/logrotate.d/siege-game

# 检查轮转状态
sudo logrotate -d /etc/logrotate.d/siege-game
```

#### 13.2 日志分析和归档
```bash
# 压缩旧日志
gzip /var/log/siege-game/*.log.1

# 归档到长期存储
tar -czf /var/backups/logs/siege-game-logs-$(date +%Y%m).tar.gz /var/log/siege-game/*.gz

# 清理归档日志
find /var/log/siege-game -name "*.gz" -mtime +30 -delete
```

## 应急响应

### 14. 应急响应流程

#### 14.1 故障响应步骤
1. **立即评估**: 确定故障影响范围
2. **快速诊断**: 使用健康检查脚本
3. **临时修复**: 重启服务或切换到备用
4. **根因分析**: 查找问题根本原因
5. **永久修复**: 实施长期解决方案
6. **文档记录**: 更新故障处理文档

#### 14.2 应急联系流程
```bash
# 发送紧急通知
echo "系统故障：$(date)" | mail -s "[EMERGENCY] Siege Game System Down" emergency@yourdomain.com

# 启动应急响应
/usr/local/bin/emergency-response.sh

# 通知相关人员
# - 技术负责人
# - 运维团队
# - 产品负责人
```

### 15. 灾难恢复

#### 15.1 完整系统恢复
```bash
# 1. 准备新服务器
# 2. 安装基础环境
# 3. 恢复应用代码
git clone https://github.com/your-repo/siege-game.git

# 4. 恢复配置文件
/usr/local/bin/rollback.sh --config-only

# 5. 恢复数据库
/usr/local/bin/rollback.sh --database-only

# 6. 启动所有服务
sudo systemctl start nginx
pm2 start pm2.config.js

# 7. 验证恢复
/usr/local/bin/health-check.sh full
```

## 维护记录

### 16. 维护日志模板

#### 16.1 维护记录表格

| 日期 | 维护类型 | 执行人 | 操作内容 | 结果 | 备注 |
|------|----------|--------|----------|------|------|
| 2024-01-01 | 日常维护 | 张三 | 系统健康检查 | 正常 | 无异常 |
| 2024-01-02 | 系统更新 | 李四 | 更新Node.js到v18.19.0 | 成功 | 应用重启正常 |
| 2024-01-03 | 安全维护 | 王五 | 更新SSL证书 | 成功 | 证书有效期延长到2025年 |

#### 16.2 维护报告模板
```markdown
# 维护报告 - YYYY-MM-DD

## 维护概要
- 维护类型：[日常/定期/紧急]
- 维护时间：YYYY-MM-DD HH:MM - HH:MM
- 执行人员：[姓名]
- 影响范围：[无/最小/中等/重大]

## 执行的操作
1. 操作1描述
2. 操作2描述
3. ...

## 遇到的问题
- 问题1：描述及解决方案
- 问题2：描述及解决方案

## 维护结果
- [ ] 所有服务正常运行
- [ ] 性能指标正常
- [ ] 备份验证通过
- [ ] 监控告警正常

## 后续行动
- 行动1：负责人，截止时间
- 行动2：负责人，截止时间

## 附件
- 维护前后性能对比
- 相关日志文件
- 配置变更记录
```

### 17. 维护自动化

#### 17.1 创建维护仪表板
```bash
# 创建维护状态页面
cat > /var/www/siege-game/public/maintenance-status.html << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Siege Game - 维护状态</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .status { padding: 20px; margin: 10px 0; border-radius: 5px; }
        .ok { background-color: #d4edda; color: #155724; }
        .warning { background-color: #fff3cd; color: #856404; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <h1>《攻城掠地》系统维护状态</h1>
    <div id="status-container">
        <div class="status ok">系统正常运行</div>
    </div>
    <script>
        // 定期更新状态
        setInterval(function() {
            fetch('/api/health')
                .then(response => response.json())
                .then(data => {
                    // 更新状态显示
                });
        }, 30000);
    </script>
</body>
</html>
EOF
```

---

## 维护最佳实践

### 18. 维护原则

1. **预防优于治疗**: 定期维护比应急修复更有效
2. **文档先行**: 所有操作都要有详细记录
3. **测试验证**: 维护后必须验证系统功能
4. **备份保险**: 重要操作前必须备份
5. **监控告警**: 建立完善的监控体系
6. **团队协作**: 维护工作需要团队配合

### 19. 维护工具推荐

- **系统监控**: htop, iotop, nethogs
- **日志分析**: grep, awk, sed, jq
- **性能分析**: ab, wrk, siege
- **安全扫描**: nmap, lynis, fail2ban
- **备份工具**: rsync, rclone, tar
- **自动化**: cron, systemd, ansible

记住：**良好的维护习惯是系统稳定运行的基础**。