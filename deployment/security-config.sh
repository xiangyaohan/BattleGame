#!/bin/bash

# 《攻城掠地》桌游项目 - 安全配置脚本
# 配置服务器安全设置

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 日志函数
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# 检查用户权限
check_permissions() {
    if [[ $EUID -ne 0 ]]; then
        log_error "此脚本需要root权限运行"
        log_info "请使用: sudo $0"
        exit 1
    fi
}

# 配置SSH安全
configure_ssh() {
    log_info "配置SSH安全设置..."
    
    # 备份原配置
    cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup.$(date +%Y%m%d_%H%M%S)
    
    # SSH安全配置
    cat >> /etc/ssh/sshd_config <<EOF

# 《攻城掠地》项目安全配置
# 禁用root登录
PermitRootLogin no

# 禁用密码认证 (建议使用密钥认证)
# PasswordAuthentication no

# 禁用空密码
PermitEmptyPasswords no

# 限制登录尝试
MaxAuthTries 3
MaxStartups 10:30:60

# 设置登录超时
LoginGraceTime 60

# 禁用X11转发
X11Forwarding no

# 禁用TCP转发
AllowTcpForwarding no

# 使用协议版本2
Protocol 2

# 限制用户
AllowUsers gameapp

# 客户端活跃检查
ClientAliveInterval 300
ClientAliveCountMax 2
EOF
    
    # 重启SSH服务
    systemctl restart sshd
    
    log_success "SSH安全配置完成"
}

# 配置防火墙
configure_firewall() {
    log_info "配置UFW防火墙..."
    
    # 重置防火墙规则
    ufw --force reset
    
    # 默认策略
    ufw default deny incoming
    ufw default allow outgoing
    
    # 允许SSH (限制连接数)
    ufw limit ssh
    
    # 允许HTTP和HTTPS
    ufw allow 80/tcp
    ufw allow 443/tcp
    
    # 允许应用端口 (仅本地)
    ufw allow from 127.0.0.1 to any port 8000
    
    # 允许PM2监控端口 (仅本地)
    ufw allow from 127.0.0.1 to any port 9615
    
    # 启用防火墙
    ufw --force enable
    
    log_success "防火墙配置完成"
}

# 配置Fail2Ban
configure_fail2ban() {
    log_info "配置Fail2Ban入侵防护..."
    
    # 安装fail2ban
    if ! command -v fail2ban-server &> /dev/null; then
        apt update
        apt install -y fail2ban
    fi
    
    # 创建本地配置
    cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
# 封禁时间 (秒)
bantime = 3600

# 查找时间窗口 (秒)
findtime = 600

# 最大重试次数
maxretry = 3

# 忽略IP (本地IP)
ignoreip = 127.0.0.1/8 ::1

# 邮件通知 (可选)
# destemail = your-email@example.com
# sender = fail2ban@yourdomain.com
# action = %(action_mwl)s

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
bantime = 3600

[nginx-http-auth]
enabled = true
filter = nginx-http-auth
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 3

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 3

[nginx-botsearch]
enabled = true
filter = nginx-botsearch
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2
EOF
    
    # 创建Nginx过滤器
    cat > /etc/fail2ban/filter.d/nginx-limit-req.conf <<EOF
[Definition]
failregex = limiting requests, excess: .* by zone .*, client: <HOST>
ignoreregex =
EOF
    
    cat > /etc/fail2ban/filter.d/nginx-botsearch.conf <<EOF
[Definition]
failregex = <HOST> .* "(GET|POST) .*(wp-admin|wp-login|xmlrpc|phpmyadmin|admin|login).*" (404|403|500)
ignoreregex =
EOF
    
    # 启动fail2ban
    systemctl enable fail2ban
    systemctl start fail2ban
    
    log_success "Fail2Ban配置完成"
}

# 配置系统安全
configure_system_security() {
    log_info "配置系统安全设置..."
    
    # 禁用不必要的服务
    local services_to_disable=("telnet" "rsh" "rlogin" "vsftpd" "apache2")
    for service in "${services_to_disable[@]}"; do
        if systemctl is-enabled "$service" &>/dev/null; then
            systemctl disable "$service"
            systemctl stop "$service"
            log_info "已禁用服务: $service"
        fi
    done
    
    # 设置文件权限
    chmod 700 /root
    chmod 644 /etc/passwd
    chmod 600 /etc/shadow
    chmod 644 /etc/group
    
    # 禁用核心转储
    echo "* hard core 0" >> /etc/security/limits.conf
    echo "fs.suid_dumpable = 0" >> /etc/sysctl.conf
    
    # 网络安全参数
    cat >> /etc/sysctl.conf <<EOF

# 《攻城掠地》项目网络安全配置
# 禁用IP转发
net.ipv4.ip_forward = 0

# 禁用源路由
net.ipv4.conf.all.accept_source_route = 0
net.ipv4.conf.default.accept_source_route = 0

# 禁用ICMP重定向
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.default.accept_redirects = 0
net.ipv4.conf.all.send_redirects = 0

# 启用反向路径过滤
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# 忽略ICMP ping
net.ipv4.icmp_echo_ignore_all = 1

# 防止SYN洪水攻击
net.ipv4.tcp_syncookies = 1
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.tcp_synack_retries = 2
net.ipv4.tcp_syn_retries = 5

# TCP安全参数
net.ipv4.tcp_timestamps = 0
net.ipv4.tcp_sack = 0

# 内核安全
kernel.dmesg_restrict = 1
kernel.kptr_restrict = 2
EOF
    
    # 应用系统参数
    sysctl -p
    
    log_success "系统安全配置完成"
}

# 配置文件权限
configure_file_permissions() {
    log_info "配置文件权限..."
    
    local app_dir="/var/www/siege-game"
    local app_user="gameapp"
    
    if [[ -d "$app_dir" ]]; then
        # 设置应用目录权限
        chown -R "$app_user:www-data" "$app_dir"
        find "$app_dir" -type d -exec chmod 755 {} \;
        find "$app_dir" -type f -exec chmod 644 {} \;
        
        # 设置可执行文件权限
        if [[ -f "$app_dir/server.js" ]]; then
            chmod 755 "$app_dir/server.js"
        fi
        
        # 保护敏感文件
        if [[ -f "$app_dir/.env" ]]; then
            chmod 600 "$app_dir/.env"
            chown "$app_user:$app_user" "$app_dir/.env"
        fi
        
        # 保护配置文件
        find "$app_dir" -name "*.config.js" -exec chmod 600 {} \;
        
        log_success "应用文件权限配置完成"
    else
        log_warning "应用目录不存在，跳过文件权限配置"
    fi
}

# 配置日志安全
configure_log_security() {
    log_info "配置日志安全..."
    
    # 创建日志目录
    mkdir -p /var/log/siege-game
    chown gameapp:adm /var/log/siege-game
    chmod 750 /var/log/siege-game
    
    # 配置logrotate
    cat > /etc/logrotate.d/siege-game <<EOF
/var/log/siege-game/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 640 gameapp adm
    postrotate
        systemctl reload nginx
        sudo -u gameapp pm2 reloadLogs
    endscript
}
EOF
    
    # 配置rsyslog安全
    if [[ -f /etc/rsyslog.conf ]]; then
        # 禁用远程日志接收
        sed -i 's/^#\?\$ModLoad imudp/#\$ModLoad imudp/' /etc/rsyslog.conf
        sed -i 's/^#\?\$UDPServerRun/#\$UDPServerRun/' /etc/rsyslog.conf
        sed -i 's/^#\?\$ModLoad imtcp/#\$ModLoad imtcp/' /etc/rsyslog.conf
        sed -i 's/^#\?\$InputTCPServerRun/#\$InputTCPServerRun/' /etc/rsyslog.conf
        
        systemctl restart rsyslog
    fi
    
    log_success "日志安全配置完成"
}

# 配置自动更新
configure_auto_updates() {
    log_info "配置自动安全更新..."
    
    # 安装unattended-upgrades
    apt update
    apt install -y unattended-upgrades apt-listchanges
    
    # 配置自动更新
    cat > /etc/apt/apt.conf.d/50unattended-upgrades <<EOF
Unattended-Upgrade::Allowed-Origins {
    "\${distro_id}:\${distro_codename}-security";
    "\${distro_id}ESMApps:\${distro_codename}-apps-security";
    "\${distro_id}ESM:\${distro_codename}-infra-security";
};

Unattended-Upgrade::Package-Blacklist {
    // "vim";
    // "libc6-dev";
    // "nginx";
    // "nodejs";
};

Unattended-Upgrade::DevRelease "false";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Remove-New-Unused-Dependencies "true";
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Automatic-Reboot-Time "02:00";

Unattended-Upgrade::Mail "root";
Unattended-Upgrade::MailOnlyOnError "true";
EOF
    
    # 启用自动更新
    cat > /etc/apt/apt.conf.d/20auto-upgrades <<EOF
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Download-Upgradeable-Packages "1";
APT::Periodic::AutocleanInterval "7";
APT::Periodic::Unattended-Upgrade "1";
EOF
    
    # 启动服务
    systemctl enable unattended-upgrades
    systemctl start unattended-upgrades
    
    log_success "自动更新配置完成"
}

# 安装安全工具
install_security_tools() {
    log_info "安装安全工具..."
    
    # 更新包列表
    apt update
    
    # 安装安全工具
    local tools=("rkhunter" "chkrootkit" "lynis" "aide" "clamav" "clamav-daemon")
    for tool in "${tools[@]}"; do
        if ! dpkg -l | grep -q "^ii  $tool "; then
            apt install -y "$tool"
            log_info "已安装: $tool"
        fi
    done
    
    # 配置rkhunter
    if command -v rkhunter &> /dev/null; then
        rkhunter --update
        rkhunter --propupd
        
        # 创建定期扫描任务
        cat > /etc/cron.weekly/rkhunter-scan <<EOF
#!/bin/bash
/usr/bin/rkhunter --cronjob --update --quiet
EOF
        chmod +x /etc/cron.weekly/rkhunter-scan
    fi
    
    # 配置ClamAV
    if command -v clamscan &> /dev/null; then
        # 更新病毒库
        freshclam
        
        # 创建定期扫描任务
        cat > /etc/cron.daily/clamav-scan <<EOF
#!/bin/bash
/usr/bin/clamscan -r /var/www/siege-game --quiet --infected --remove
EOF
        chmod +x /etc/cron.daily/clamav-scan
    fi
    
    log_success "安全工具安装完成"
}

# 创建安全监控脚本
create_security_monitor() {
    log_info "创建安全监控脚本..."
    
    cat > /usr/local/bin/security-monitor.sh <<EOF
#!/bin/bash

# 《攻城掠地》项目安全监控脚本

LOG_FILE="/var/log/security-monitor.log"
EMAIL="root@localhost"

# 检查失败的登录尝试
check_failed_logins() {
    local failed_logins=\$(grep "Failed password" /var/log/auth.log | wc -l)
    if [[ \$failed_logins -gt 10 ]]; then
        echo "\$(date): 检测到 \$failed_logins 次失败登录尝试" >> "\$LOG_FILE"
    fi
}

# 检查磁盘使用率
check_disk_usage() {
    local usage=\$(df / | awk 'NR==2 {print \$5}' | sed 's/%//')
    if [[ \$usage -gt 80 ]]; then
        echo "\$(date): 磁盘使用率过高: \$usage%" >> "\$LOG_FILE"
    fi
}

# 检查内存使用率
check_memory_usage() {
    local usage=\$(free | awk 'NR==2{printf "%.0f", \$3*100/\$2}')
    if [[ \$usage -gt 90 ]]; then
        echo "\$(date): 内存使用率过高: \$usage%" >> "\$LOG_FILE"
    fi
}

# 检查异常进程
check_processes() {
    local suspicious_processes=\$(ps aux | grep -E "(nc|netcat|nmap|wget|curl)" | grep -v grep | wc -l)
    if [[ \$suspicious_processes -gt 0 ]]; then
        echo "\$(date): 检测到可疑进程" >> "\$LOG_FILE"
        ps aux | grep -E "(nc|netcat|nmap|wget|curl)" | grep -v grep >> "\$LOG_FILE"
    fi
}

# 检查网络连接
check_network() {
    local connections=\$(netstat -tn | grep ESTABLISHED | wc -l)
    if [[ \$connections -gt 100 ]]; then
        echo "\$(date): 网络连接数过多: \$connections" >> "\$LOG_FILE"
    fi
}

# 主函数
main() {
    check_failed_logins
    check_disk_usage
    check_memory_usage
    check_processes
    check_network
}

main
EOF
    
    chmod +x /usr/local/bin/security-monitor.sh
    
    # 创建定期监控任务
    cat > /etc/cron.d/security-monitor <<EOF
# 安全监控任务 - 每5分钟执行一次
*/5 * * * * root /usr/local/bin/security-monitor.sh
EOF
    
    log_success "安全监控脚本创建完成"
}

# 显示安全状态
show_security_status() {
    log_info "显示安全状态..."
    
    echo
    echo "=== 安全配置状态 ==="
    echo "SSH配置: $(systemctl is-active sshd)"
    echo "防火墙状态: $(ufw status | head -1)"
    echo "Fail2Ban状态: $(systemctl is-active fail2ban)"
    echo "自动更新: $(systemctl is-active unattended-upgrades)"
    echo
    
    echo "=== 防火墙规则 ==="
    ufw status numbered
    echo
    
    echo "=== Fail2Ban状态 ==="
    fail2ban-client status
    echo
    
    echo "=== 系统安全检查 ==="
    echo "最近登录失败次数: $(grep "Failed password" /var/log/auth.log | tail -10 | wc -l)"
    echo "当前活跃连接数: $(netstat -tn | grep ESTABLISHED | wc -l)"
    echo "磁盘使用率: $(df / | awk 'NR==2 {print $5}')"
    echo "内存使用率: $(free | awk 'NR==2{printf "%.0f%%", $3*100/$2}')"
    echo
}

# 显示帮助信息
show_help() {
    echo "《攻城掠地》桌游项目 - 安全配置脚本"
    echo
    echo "用法: $0 [选项]"
    echo
    echo "选项:"
    echo "  install   执行完整安全配置 (默认)"
    echo "  ssh       仅配置SSH安全"
    echo "  firewall  仅配置防火墙"
    echo "  fail2ban  仅配置Fail2Ban"
    echo "  system    仅配置系统安全"
    echo "  tools     仅安装安全工具"
    echo "  monitor   仅创建监控脚本"
    echo "  status    显示安全状态"
    echo "  help      显示此帮助信息"
    echo
}

# 主函数
main() {
    local action="${1:-install}"
    
    case "$action" in
        "install")
            log_info "开始安全配置..."
            check_permissions
            configure_ssh
            configure_firewall
            configure_fail2ban
            configure_system_security
            configure_file_permissions
            configure_log_security
            configure_auto_updates
            install_security_tools
            create_security_monitor
            show_security_status
            log_success "安全配置完成！"
            ;;
        "ssh")
            check_permissions
            configure_ssh
            ;;
        "firewall")
            check_permissions
            configure_firewall
            ;;
        "fail2ban")
            check_permissions
            configure_fail2ban
            ;;
        "system")
            check_permissions
            configure_system_security
            ;;
        "tools")
            check_permissions
            install_security_tools
            ;;
        "monitor")
            check_permissions
            create_security_monitor
            ;;
        "status")
            show_security_status
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            log_error "未知操作: $action"
            show_help
            exit 1
            ;;
    esac
}

# 运行主函数
main "$@"