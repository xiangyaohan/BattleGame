#!/bin/bash

# 《攻城掠地》桌游项目 - 自动化部署脚本
# 用于将项目部署到生产服务器

set -e  # 遇到错误立即退出

# 配置变量
PROJECT_NAME="siege-board-game"
APP_USER="gameapp"
APP_DIR="/var/www/siege-game"
BACKUP_DIR="/var/backups/siege-game"
LOG_DIR="/var/log/siege-game"
REPO_URL="https://github.com/xiangyaohan/BattleGame.git"
BRANCH="main"
NODE_ENV="production"
PORT="8000"

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
    if [[ $EUID -eq 0 ]]; then
        log_error "请不要使用root用户运行部署脚本"
        exit 1
    fi
    
    if ! groups | grep -q sudo; then
        log_error "当前用户没有sudo权限"
        exit 1
    fi
}

# 检查必需的工具
check_dependencies() {
    log_info "检查部署依赖..."
    
    local deps=("git" "node" "npm" "pm2" "nginx")
    for dep in "${deps[@]}"; do
        if ! command -v "$dep" &> /dev/null; then
            log_error "缺少依赖: $dep"
            log_info "请先运行 setup-server.sh 安装服务器环境"
            exit 1
        fi
    done
    
    log_success "所有依赖检查通过"
}

# 创建备份
create_backup() {
    log_info "创建当前部署备份..."
    
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local backup_path="$BACKUP_DIR/backup_$timestamp"
    
    sudo mkdir -p "$BACKUP_DIR"
    
    if [[ -d "$APP_DIR" ]]; then
        sudo cp -r "$APP_DIR" "$backup_path"
        sudo chown -R "$APP_USER:www-data" "$backup_path"
        log_success "备份创建完成: $backup_path"
        
        # 保留最近10个备份
        sudo find "$BACKUP_DIR" -maxdepth 1 -type d -name "backup_*" | sort -r | tail -n +11 | sudo xargs rm -rf
    else
        log_info "首次部署，跳过备份"
    fi
}

# 停止应用服务
stop_services() {
    log_info "停止应用服务..."
    
    # 停止PM2进程
    if sudo -u "$APP_USER" pm2 list | grep -q "$PROJECT_NAME"; then
        sudo -u "$APP_USER" pm2 stop "$PROJECT_NAME" || true
        log_success "PM2进程已停止"
    else
        log_info "PM2进程未运行"
    fi
}

# 部署代码
deploy_code() {
    log_info "部署应用代码..."
    
    # 创建应用目录
    sudo mkdir -p "$APP_DIR"
    
    # 如果目录存在且不为空，先备份
    if [[ -d "$APP_DIR/.git" ]]; then
        log_info "更新现有代码库..."
        cd "$APP_DIR"
        sudo -u "$APP_USER" git fetch origin
        sudo -u "$APP_USER" git reset --hard "origin/$BRANCH"
        sudo -u "$APP_USER" git clean -fd
    else
        log_info "克隆代码库..."
        sudo rm -rf "$APP_DIR"
        sudo -u "$APP_USER" git clone "$REPO_URL" "$APP_DIR"
        cd "$APP_DIR"
        sudo -u "$APP_USER" git checkout "$BRANCH"
    fi
    
    # 设置正确的权限
    sudo chown -R "$APP_USER:www-data" "$APP_DIR"
    sudo chmod -R 755 "$APP_DIR"
    
    log_success "代码部署完成"
}

# 安装依赖
install_dependencies() {
    log_info "安装项目依赖..."
    
    cd "$APP_DIR"
    
    # 检查是否有package.json
    if [[ -f "package.json" ]]; then
        # 清理npm缓存
        sudo -u "$APP_USER" npm cache clean --force
        
        # 安装生产依赖
        sudo -u "$APP_USER" npm ci --only=production
        log_success "依赖安装完成"
    else
        log_info "未找到package.json，跳过依赖安装"
    fi
}

# 配置环境变量
configure_environment() {
    log_info "配置环境变量..."
    
    # 创建环境配置文件
    sudo -u "$APP_USER" tee "$APP_DIR/.env" > /dev/null <<EOF
# 《攻城掠地》桌游项目环境配置
NODE_ENV=$NODE_ENV
PORT=$PORT
HOST=0.0.0.0

# 日志配置
LOG_LEVEL=info
LOG_DIR=$LOG_DIR

# 安全配置
SESSION_SECRET=$(openssl rand -base64 32)

# 应用配置
APP_NAME=$PROJECT_NAME
APP_VERSION=$(cd "$APP_DIR" && git describe --tags --always)
EOF

    sudo chown "$APP_USER:www-data" "$APP_DIR/.env"
    sudo chmod 600 "$APP_DIR/.env"
    
    log_success "环境变量配置完成"
}

# 配置PM2
configure_pm2() {
    log_info "配置PM2进程管理..."
    
    # 创建PM2配置文件
    sudo -u "$APP_USER" tee "$APP_DIR/ecosystem.config.js" > /dev/null <<EOF
module.exports = {
  apps: [{
    name: '$PROJECT_NAME',
    script: './server.js',
    cwd: '$APP_DIR',
    instances: 'max',
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: $PORT,
      HOST: '0.0.0.0'
    },
    error_file: '$LOG_DIR/error.log',
    out_file: '$LOG_DIR/access.log',
    log_file: '$LOG_DIR/combined.log',
    time: true,
    max_memory_restart: '500M',
    node_args: '--max-old-space-size=512',
    watch: false,
    ignore_watch: ['node_modules', 'logs', '.git'],
    max_restarts: 10,
    min_uptime: '10s',
    kill_timeout: 5000,
    wait_ready: true,
    listen_timeout: 10000
  }]
};
EOF

    sudo chown "$APP_USER:www-data" "$APP_DIR/ecosystem.config.js"
    
    log_success "PM2配置完成"
}

# 启动应用服务
start_services() {
    log_info "启动应用服务..."
    
    cd "$APP_DIR"
    
    # 启动PM2进程
    sudo -u "$APP_USER" pm2 start ecosystem.config.js
    
    # 保存PM2配置
    sudo -u "$APP_USER" pm2 save
    
    # 设置PM2开机自启
    sudo env PATH=$PATH:/usr/bin pm2 startup systemd -u "$APP_USER" --hp "/home/$APP_USER"
    
    log_success "应用服务启动完成"
}

# 健康检查
health_check() {
    log_info "执行健康检查..."
    
    local max_attempts=30
    local attempt=1
    
    while [[ $attempt -le $max_attempts ]]; do
        if curl -f -s "http://localhost:$PORT" > /dev/null; then
            log_success "健康检查通过"
            return 0
        fi
        
        log_info "等待应用启动... ($attempt/$max_attempts)"
        sleep 2
        ((attempt++))
    done
    
    log_error "健康检查失败，应用可能未正常启动"
    return 1
}

# 显示部署状态
show_status() {
    log_info "显示部署状态..."
    
    echo
    echo "=== 部署状态 ==="
    echo "项目名称: $PROJECT_NAME"
    echo "部署目录: $APP_DIR"
    echo "应用用户: $APP_USER"
    echo "运行端口: $PORT"
    echo "当前版本: $(cd "$APP_DIR" && git describe --tags --always)"
    echo "部署时间: $(date)"
    echo
    
    echo "=== PM2状态 ==="
    sudo -u "$APP_USER" pm2 list
    echo
    
    echo "=== 系统资源 ==="
    echo "CPU使用率: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)%"
    echo "内存使用: $(free -h | awk '/^Mem:/ {print $3 "/" $2}')"
    echo "磁盘使用: $(df -h / | awk 'NR==2 {print $3 "/" $2 " (" $5 ")"}')"
    echo
}

# 清理临时文件
cleanup() {
    log_info "清理临时文件..."
    
    # 清理npm缓存
    sudo -u "$APP_USER" npm cache clean --force
    
    # 清理旧的日志文件
    sudo find "$LOG_DIR" -name "*.log" -mtime +30 -delete 2>/dev/null || true
    
    log_success "清理完成"
}

# 回滚函数
rollback() {
    log_warning "开始回滚到上一个版本..."
    
    local latest_backup=$(sudo find "$BACKUP_DIR" -maxdepth 1 -type d -name "backup_*" | sort -r | head -n 1)
    
    if [[ -z "$latest_backup" ]]; then
        log_error "未找到备份，无法回滚"
        exit 1
    fi
    
    log_info "回滚到备份: $latest_backup"
    
    # 停止服务
    stop_services
    
    # 恢复备份
    sudo rm -rf "$APP_DIR"
    sudo cp -r "$latest_backup" "$APP_DIR"
    sudo chown -R "$APP_USER:www-data" "$APP_DIR"
    
    # 重启服务
    start_services
    
    if health_check; then
        log_success "回滚成功"
    else
        log_error "回滚后健康检查失败"
        exit 1
    fi
}

# 显示帮助信息
show_help() {
    echo "《攻城掠地》桌游项目部署脚本"
    echo
    echo "用法: $0 [选项]"
    echo
    echo "选项:"
    echo "  deploy    执行完整部署 (默认)"
    echo "  rollback  回滚到上一个版本"
    echo "  status    显示当前状态"
    echo "  restart   重启应用服务"
    echo "  stop      停止应用服务"
    echo "  start     启动应用服务"
    echo "  logs      查看应用日志"
    echo "  help      显示此帮助信息"
    echo
}

# 主函数
main() {
    local action="${1:-deploy}"
    
    case "$action" in
        "deploy")
            log_info "开始部署《攻城掠地》桌游项目..."
            check_permissions
            check_dependencies
            create_backup
            stop_services
            deploy_code
            install_dependencies
            configure_environment
            configure_pm2
            start_services
            
            if health_check; then
                show_status
                cleanup
                log_success "部署完成！"
            else
                log_error "部署失败，正在回滚..."
                rollback
                exit 1
            fi
            ;;
        "rollback")
            rollback
            ;;
        "status")
            show_status
            ;;
        "restart")
            log_info "重启应用服务..."
            sudo -u "$APP_USER" pm2 restart "$PROJECT_NAME"
            health_check
            ;;
        "stop")
            stop_services
            ;;
        "start")
            start_services
            health_check
            ;;
        "logs")
            sudo -u "$APP_USER" pm2 logs "$PROJECT_NAME"
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