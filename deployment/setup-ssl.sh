#!/bin/bash

# 《攻城掠地》桌游项目 - SSL证书配置脚本
# 使用Let's Encrypt免费SSL证书

set -e

# 配置变量
DOMAIN="yourdomain.com"  # 替换为实际域名
EMAIL="your-email@example.com"  # 替换为实际邮箱
WEBROOT="/var/www/siege-game/public"
NGINX_CONFIG="/etc/nginx/sites-available/siege-game"
NGINX_ENABLED="/etc/nginx/sites-enabled/siege-game"

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

# 验证域名配置
validate_domain() {
    log_info "验证域名配置..."
    
    if [[ "$DOMAIN" == "yourdomain.com" ]]; then
        log_error "请先修改脚本中的域名配置"
        log_info "将 DOMAIN 变量设置为您的实际域名"
        exit 1
    fi
    
    if [[ "$EMAIL" == "your-email@example.com" ]]; then
        log_error "请先修改脚本中的邮箱配置"
        log_info "将 EMAIL 变量设置为您的实际邮箱"
        exit 1
    fi
    
    # 检查域名DNS解析
    if ! nslookup "$DOMAIN" > /dev/null 2>&1; then
        log_warning "域名 $DOMAIN 的DNS解析可能有问题"
        log_info "请确保域名已正确解析到此服务器"
        read -p "是否继续? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
    
    log_success "域名配置验证通过"
}

# 安装Certbot
install_certbot() {
    log_info "安装Certbot..."
    
    # 更新包列表
    apt update
    
    # 安装snapd (如果未安装)
    if ! command -v snap &> /dev/null; then
        apt install -y snapd
        systemctl enable snapd
        systemctl start snapd
    fi
    
    # 安装certbot
    if ! command -v certbot &> /dev/null; then
        snap install core; snap refresh core
        snap install --classic certbot
        ln -sf /snap/bin/certbot /usr/bin/certbot
    fi
    
    log_success "Certbot安装完成"
}

# 配置临时Nginx
setup_temp_nginx() {
    log_info "配置临时Nginx配置..."
    
    # 创建临时配置文件
    cat > "$NGINX_CONFIG" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN www.$DOMAIN;
    
    root $WEBROOT;
    index index.html index.htm;
    
    # Let's Encrypt验证
    location /.well-known/acme-challenge/ {
        root $WEBROOT;
        try_files \$uri =404;
    }
    
    # 临时主页
    location / {
        return 200 'SSL证书配置中，请稍候...';
        add_header Content-Type text/plain;
    }
}
EOF
    
    # 启用配置
    ln -sf "$NGINX_CONFIG" "$NGINX_ENABLED"
    
    # 测试配置
    nginx -t
    
    # 重载Nginx
    systemctl reload nginx
    
    log_success "临时Nginx配置完成"
}

# 获取SSL证书
obtain_certificate() {
    log_info "获取SSL证书..."
    
    # 确保webroot目录存在
    mkdir -p "$WEBROOT/.well-known/acme-challenge"
    chown -R www-data:www-data "$WEBROOT"
    
    # 获取证书
    certbot certonly \
        --webroot \
        --webroot-path="$WEBROOT" \
        --email "$EMAIL" \
        --agree-tos \
        --no-eff-email \
        --domains "$DOMAIN,www.$DOMAIN" \
        --non-interactive
    
    if [[ $? -eq 0 ]]; then
        log_success "SSL证书获取成功"
    else
        log_error "SSL证书获取失败"
        exit 1
    fi
}

# 配置生产Nginx
setup_production_nginx() {
    log_info "配置生产环境Nginx..."
    
    # 复制生产配置
    cp "/var/www/siege-game/deployment/nginx.conf" "$NGINX_CONFIG"
    
    # 替换域名占位符
    sed -i "s/yourdomain.com/$DOMAIN/g" "$NGINX_CONFIG"
    
    # 测试配置
    nginx -t
    
    if [[ $? -eq 0 ]]; then
        # 重载Nginx
        systemctl reload nginx
        log_success "生产环境Nginx配置完成"
    else
        log_error "Nginx配置测试失败"
        exit 1
    fi
}

# 配置自动续期
setup_auto_renewal() {
    log_info "配置SSL证书自动续期..."
    
    # 创建续期脚本
    cat > /etc/cron.d/certbot-renewal <<EOF
# SSL证书自动续期
# 每天凌晨2点检查证书是否需要续期
0 2 * * * root certbot renew --quiet --post-hook "systemctl reload nginx"
EOF
    
    # 测试续期
    certbot renew --dry-run
    
    if [[ $? -eq 0 ]]; then
        log_success "自动续期配置完成"
    else
        log_warning "自动续期测试失败，请检查配置"
    fi
}

# 配置安全头部
setup_security_headers() {
    log_info "配置安全头部..."
    
    # 创建安全配置文件
    cat > /etc/nginx/conf.d/security-headers.conf <<EOF
# 安全头部配置
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;

# HSTS (仅在HTTPS下生效)
map \$scheme \$hsts_header {
    https "max-age=31536000; includeSubDomains; preload";
}
add_header Strict-Transport-Security \$hsts_header always;

# CSP (内容安全策略)
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self';" always;
EOF
    
    log_success "安全头部配置完成"
}

# 配置防火墙
setup_firewall() {
    log_info "配置防火墙规则..."
    
    # 允许HTTP和HTTPS
    ufw allow 80/tcp
    ufw allow 443/tcp
    
    # 限制SSH访问 (可选)
    ufw limit ssh
    
    # 启用防火墙
    ufw --force enable
    
    log_success "防火墙配置完成"
}

# SSL测试
test_ssl() {
    log_info "测试SSL配置..."
    
    # 等待服务启动
    sleep 5
    
    # 测试HTTPS连接
    if curl -f -s "https://$DOMAIN" > /dev/null; then
        log_success "HTTPS连接测试通过"
    else
        log_warning "HTTPS连接测试失败，请检查配置"
    fi
    
    # 测试HTTP重定向
    if curl -s -o /dev/null -w "%{http_code}" "http://$DOMAIN" | grep -q "301"; then
        log_success "HTTP重定向测试通过"
    else
        log_warning "HTTP重定向测试失败"
    fi
    
    # 显示证书信息
    echo
    echo "=== SSL证书信息 ==="
    openssl x509 -in "/etc/letsencrypt/live/$DOMAIN/fullchain.pem" -text -noout | grep -E "(Subject:|Issuer:|Not Before:|Not After:)"
    echo
}

# 显示配置信息
show_info() {
    log_success "SSL配置完成！"
    
    echo
    echo "=== 配置信息 ==="
    echo "域名: $DOMAIN"
    echo "证书路径: /etc/letsencrypt/live/$DOMAIN/"
    echo "Nginx配置: $NGINX_CONFIG"
    echo "自动续期: 已配置 (每天凌晨2点检查)"
    echo
    echo "=== 访问地址 ==="
    echo "HTTPS: https://$DOMAIN"
    echo "HTTP: http://$DOMAIN (自动重定向到HTTPS)"
    echo
    echo "=== 证书管理命令 ==="
    echo "查看证书状态: certbot certificates"
    echo "手动续期: certbot renew"
    echo "测试续期: certbot renew --dry-run"
    echo "撤销证书: certbot revoke --cert-path /etc/letsencrypt/live/$DOMAIN/fullchain.pem"
    echo
    echo "=== SSL评级测试 ==="
    echo "请访问以下网址测试SSL配置:"
    echo "https://www.ssllabs.com/ssltest/analyze.html?d=$DOMAIN"
    echo
}

# 清理函数
cleanup() {
    log_info "清理临时文件..."
    # 这里可以添加清理逻辑
}

# 错误处理
handle_error() {
    log_error "脚本执行失败"
    cleanup
    exit 1
}

# 设置错误处理
trap handle_error ERR

# 显示帮助信息
show_help() {
    echo "《攻城掠地》桌游项目 - SSL证书配置脚本"
    echo
    echo "用法: $0 [选项]"
    echo
    echo "选项:"
    echo "  install   安装SSL证书 (默认)"
    echo "  renew     手动续期证书"
    echo "  test      测试SSL配置"
    echo "  info      显示证书信息"
    echo "  help      显示此帮助信息"
    echo
    echo "配置前请修改脚本中的以下变量:"
    echo "  DOMAIN    - 您的域名"
    echo "  EMAIL     - 您的邮箱地址"
    echo
}

# 主函数
main() {
    local action="${1:-install}"
    
    case "$action" in
        "install")
            log_info "开始SSL证书配置..."
            check_permissions
            validate_domain
            install_certbot
            setup_temp_nginx
            obtain_certificate
            setup_production_nginx
            setup_auto_renewal
            setup_security_headers
            setup_firewall
            test_ssl
            show_info
            cleanup
            ;;
        "renew")
            log_info "手动续期SSL证书..."
            check_permissions
            certbot renew
            systemctl reload nginx
            log_success "证书续期完成"
            ;;
        "test")
            test_ssl
            ;;
        "info")
            certbot certificates
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