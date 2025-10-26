#!/bin/bash

# 《攻城掠地》桌游项目 - 服务器环境安装脚本
# 适用于 Ubuntu 20.04/22.04 LTS

set -e  # 遇到错误立即退出

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

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

# 检查是否为root用户
check_root() {
    if [[ $EUID -eq 0 ]]; then
        log_error "请不要使用root用户运行此脚本"
        log_info "请创建一个普通用户并添加到sudo组"
        exit 1
    fi
}

# 检查操作系统
check_os() {
    if [[ ! -f /etc/os-release ]]; then
        log_error "无法检测操作系统版本"
        exit 1
    fi
    
    . /etc/os-release
    if [[ "$ID" != "ubuntu" ]]; then
        log_warning "此脚本专为Ubuntu设计，其他系统可能需要调整"
    fi
    
    log_info "检测到操作系统: $PRETTY_NAME"
}

# 更新系统
update_system() {
    log_info "更新系统包..."
    sudo apt update
    sudo apt upgrade -y
    sudo apt autoremove -y
    log_success "系统更新完成"
}

# 安装基础工具
install_basic_tools() {
    log_info "安装基础工具..."
    sudo apt install -y \
        curl \
        wget \
        git \
        unzip \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        gnupg \
        lsb-release \
        htop \
        tree \
        vim \
        ufw \
        fail2ban
    log_success "基础工具安装完成"
}

# 安装Node.js
install_nodejs() {
    log_info "安装Node.js..."
    
    # 添加NodeSource仓库
    curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
    
    # 安装Node.js
    sudo apt install -y nodejs
    
    # 验证安装
    node_version=$(node --version)
    npm_version=$(npm --version)
    
    log_success "Node.js安装完成: $node_version"
    log_success "npm版本: $npm_version"
    
    # 安装PM2
    log_info "安装PM2进程管理器..."
    sudo npm install -g pm2
    pm2_version=$(pm2 --version)
    log_success "PM2安装完成: $pm2_version"
}

# 安装Nginx
install_nginx() {
    log_info "安装Nginx..."
    sudo apt install -y nginx
    
    # 启动并启用Nginx
    sudo systemctl start nginx
    sudo systemctl enable nginx
    
    nginx_version=$(nginx -v 2>&1 | cut -d' ' -f3)
    log_success "Nginx安装完成: $nginx_version"
}

# 配置防火墙
configure_firewall() {
    log_info "配置防火墙..."
    
    # 重置UFW规则
    sudo ufw --force reset
    
    # 设置默认策略
    sudo ufw default deny incoming
    sudo ufw default allow outgoing
    
    # 允许SSH
    sudo ufw allow ssh
    
    # 允许HTTP和HTTPS
    sudo ufw allow 'Nginx Full'
    
    # 启用防火墙
    sudo ufw --force enable
    
    log_success "防火墙配置完成"
    sudo ufw status
}

# 配置fail2ban
configure_fail2ban() {
    log_info "配置fail2ban..."
    
    # 创建本地配置文件
    sudo tee /etc/fail2ban/jail.local > /dev/null <<EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log
maxretry = 3

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 10
EOF

    # 启动并启用fail2ban
    sudo systemctl start fail2ban
    sudo systemctl enable fail2ban
    
    log_success "fail2ban配置完成"
}

# 创建应用用户
create_app_user() {
    local app_user="gameapp"
    
    log_info "创建应用用户: $app_user"
    
    # 检查用户是否已存在
    if id "$app_user" &>/dev/null; then
        log_warning "用户 $app_user 已存在"
    else
        sudo useradd -m -s /bin/bash "$app_user"
        sudo usermod -aG www-data "$app_user"
        log_success "用户 $app_user 创建完成"
    fi
    
    # 创建应用目录
    sudo mkdir -p /var/www/siege-game
    sudo chown -R "$app_user:www-data" /var/www/siege-game
    sudo chmod -R 755 /var/www/siege-game
    
    log_success "应用目录创建完成: /var/www/siege-game"
}

# 安装Docker (可选)
install_docker() {
    read -p "是否安装Docker? (y/N): " install_docker_choice
    if [[ $install_docker_choice =~ ^[Yy]$ ]]; then
        log_info "安装Docker..."
        
        # 添加Docker官方GPG密钥
        curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
        
        # 添加Docker仓库
        echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
        
        # 安装Docker
        sudo apt update
        sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
        
        # 将当前用户添加到docker组
        sudo usermod -aG docker $USER
        
        # 启动并启用Docker
        sudo systemctl start docker
        sudo systemctl enable docker
        
        docker_version=$(docker --version)
        log_success "Docker安装完成: $docker_version"
        log_info "请重新登录以使docker组权限生效"
    fi
}

# 安装SSL证书工具
install_certbot() {
    log_info "安装Certbot (Let's Encrypt SSL证书工具)..."
    sudo apt install -y certbot python3-certbot-nginx
    log_success "Certbot安装完成"
}

# 优化系统参数
optimize_system() {
    log_info "优化系统参数..."
    
    # 增加文件描述符限制
    sudo tee -a /etc/security/limits.conf > /dev/null <<EOF

# 《攻城掠地》桌游项目优化
* soft nofile 65536
* hard nofile 65536
* soft nproc 65536
* hard nproc 65536
EOF

    # 优化网络参数
    sudo tee /etc/sysctl.d/99-siege-game.conf > /dev/null <<EOF
# 《攻城掠地》桌游项目网络优化
net.core.somaxconn = 65536
net.core.netdev_max_backlog = 5000
net.ipv4.tcp_max_syn_backlog = 65536
net.ipv4.tcp_fin_timeout = 30
net.ipv4.tcp_keepalive_time = 1200
net.ipv4.tcp_max_tw_buckets = 5000
EOF

    sudo sysctl -p /etc/sysctl.d/99-siege-game.conf
    
    log_success "系统参数优化完成"
}

# 创建日志目录
create_log_directories() {
    log_info "创建日志目录..."
    
    sudo mkdir -p /var/log/siege-game
    sudo chown gameapp:www-data /var/log/siege-game
    sudo chmod 755 /var/log/siege-game
    
    # 配置logrotate
    sudo tee /etc/logrotate.d/siege-game > /dev/null <<EOF
/var/log/siege-game/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 gameapp www-data
    postrotate
        pm2 reload all
    endscript
}
EOF

    log_success "日志目录和轮转配置完成"
}

# 显示安装总结
show_summary() {
    log_success "服务器环境安装完成！"
    echo
    echo "=== 安装总结 ==="
    echo "操作系统: $(lsb_release -d | cut -f2)"
    echo "Node.js: $(node --version)"
    echo "npm: $(npm --version)"
    echo "PM2: $(pm2 --version)"
    echo "Nginx: $(nginx -v 2>&1 | cut -d' ' -f3)"
    echo "应用目录: /var/www/siege-game"
    echo "应用用户: gameapp"
    echo
    echo "=== 下一步操作 ==="
    echo "1. 配置域名DNS解析到此服务器"
    echo "2. 运行部署脚本上传应用代码"
    echo "3. 配置SSL证书"
    echo "4. 启动应用服务"
    echo
    echo "=== 重要提醒 ==="
    echo "- 请保存好SSH密钥"
    echo "- 定期更新系统补丁"
    echo "- 监控服务器资源使用情况"
    echo "- 定期备份重要数据"
}

# 主函数
main() {
    log_info "开始安装《攻城掠地》桌游项目服务器环境..."
    
    check_root
    check_os
    update_system
    install_basic_tools
    install_nodejs
    install_nginx
    configure_firewall
    configure_fail2ban
    create_app_user
    install_docker
    install_certbot
    optimize_system
    create_log_directories
    show_summary
    
    log_success "所有安装步骤完成！"
}

# 运行主函数
main "$@"