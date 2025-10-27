#!/bin/bash

# 《攻城掠地》桌游项目 - Docker生产环境部署脚本

set -e

# 配置变量
PROJECT_NAME="siege-board-game"
COMPOSE_FILE="docker-compose.production.yml"
ENV_FILE=".env.production"
BACKUP_DIR="/var/backups/docker-siege-game"
LOG_DIR="/var/log/docker-siege-game"

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
    
    if ! groups | grep -q docker; then
        log_error "当前用户不在docker组中"
        log_info "请运行: sudo usermod -aG docker $USER"
        log_info "然后重新登录"
        exit 1
    fi
}

# 检查Docker环境
check_docker() {
    log_info "检查Docker环境..."
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker未安装"
        exit 1
    fi
    
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose未安装"
        exit 1
    fi
    
    if ! docker info &> /dev/null; then
        log_error "Docker服务未运行"
        log_info "请运行: sudo systemctl start docker"
        exit 1
    fi
    
    log_success "Docker环境检查通过"
}

# 检查系统资源
check_resources() {
    log_info "检查系统资源..."
    
    # 检查内存
    local total_mem=$(free -m | awk 'NR==2{print $2}')
    if [[ $total_mem -lt 2048 ]]; then
        log_warning "系统内存不足2GB，可能影响性能"
    fi
    
    # 检查磁盘空间
    local disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [[ $disk_usage -gt 80 ]]; then
        log_warning "磁盘使用率超过80%，请清理空间"
    fi
    
    # 检查Docker磁盘空间
    local docker_usage=$(docker system df | awk 'NR==2 {print $4}' | sed 's/%//')
    if [[ $docker_usage -gt 70 ]]; then
        log_warning "Docker磁盘使用率过高，建议清理"
        log_info "运行 'docker system prune' 清理"
    fi
    
    log_success "系统资源检查完成"
}

# 创建必要目录
create_directories() {
    log_info "创建必要目录..."
    
    local dirs=(
        "$BACKUP_DIR"
        "$LOG_DIR"
        "/var/log/siege-game"
        "/var/log/nginx"
        "/var/www/siege-game/uploads"
        "./ssl"
        "./config"
        "./monitoring"
        "./redis"
    )
    
    for dir in "${dirs[@]}"; do
        sudo mkdir -p "$dir"
        sudo chown -R "$USER:docker" "$dir" 2>/dev/null || true
    done
    
    log_success "目录创建完成"
}

# 配置环境变量
setup_environment() {
    log_info "配置环境变量..."
    
    if [[ ! -f "$ENV_FILE" ]]; then
        log_info "创建环境配置文件..."
        cp ".env.production" "$ENV_FILE"
        
        # 生成随机密码
        local redis_password=$(openssl rand -base64 32)
        local grafana_password=$(openssl rand -base64 16)
        
        # 更新配置文件
        sed -i "s/your-session-secret-here/$(openssl rand -base64 32)/" "$ENV_FILE"
        echo "REDIS_PASSWORD=$redis_password" >> "$ENV_FILE"
        echo "GRAFANA_PASSWORD=$grafana_password" >> "$ENV_FILE"
        
        log_success "环境配置文件创建完成"
        log_info "请检查并修改 $ENV_FILE 中的配置"
    else
        log_info "使用现有环境配置文件"
    fi
}

# 创建备份
create_backup() {
    log_info "创建当前部署备份..."
    
    local timestamp=$(date +"%Y%m%d_%H%M%S")
    local backup_path="$BACKUP_DIR/backup_$timestamp"
    
    sudo mkdir -p "$backup_path"
    
    # 备份Docker镜像
    if docker images | grep -q "$PROJECT_NAME"; then
        log_info "备份Docker镜像..."
        docker save "$PROJECT_NAME:latest" | gzip > "$backup_path/image.tar.gz"
    fi
    
    # 备份数据卷
    if docker volume ls | grep -q "${PROJECT_NAME}_"; then
        log_info "备份数据卷..."
        mkdir -p "$backup_path/volumes"
        
        local volumes=$(docker volume ls --format "{{.Name}}" | grep "^${PROJECT_NAME}_")
        for volume in $volumes; do
            docker run --rm -v "$volume:/data" -v "$backup_path/volumes:/backup" alpine tar czf "/backup/$volume.tar.gz" -C /data .
        done
    fi
    
    # 备份配置文件
    if [[ -f "$ENV_FILE" ]]; then
        cp "$ENV_FILE" "$backup_path/"
    fi
    
    # 保留最近10个备份
    sudo find "$BACKUP_DIR" -maxdepth 1 -type d -name "backup_*" | sort -r | tail -n +11 | sudo xargs rm -rf
    
    log_success "备份创建完成: $backup_path"
}

# 停止现有服务
stop_services() {
    log_info "停止现有服务..."
    
    if docker-compose -f "$COMPOSE_FILE" ps | grep -q "Up"; then
        docker-compose -f "$COMPOSE_FILE" down --remove-orphans
        log_success "服务已停止"
    else
        log_info "没有运行中的服务"
    fi
}

# 清理旧资源
cleanup_old_resources() {
    log_info "清理旧资源..."
    
    # 清理未使用的镜像
    docker image prune -f
    
    # 清理未使用的容器
    docker container prune -f
    
    # 清理未使用的网络
    docker network prune -f
    
    log_success "旧资源清理完成"
}

# 构建镜像
build_images() {
    log_info "构建Docker镜像..."
    
    # 构建应用镜像
    docker-compose -f "$COMPOSE_FILE" build --no-cache app
    
    # 标记镜像
    docker tag "${PROJECT_NAME}_app:latest" "$PROJECT_NAME:latest"
    
    log_success "镜像构建完成"
}

# 启动服务
start_services() {
    log_info "启动服务..."
    
    # 启动核心服务
    docker-compose -f "$COMPOSE_FILE" up -d app nginx redis
    
    # 等待服务启动
    sleep 10
    
    # 启动监控服务 (可选)
    if [[ "${ENABLE_MONITORING:-false}" == "true" ]]; then
        docker-compose -f "$COMPOSE_FILE" up -d prometheus grafana
    fi
    
    # 启动日志服务 (可选)
    if [[ "${ENABLE_LOGGING:-false}" == "true" ]]; then
        docker-compose -f "$COMPOSE_FILE" up -d elasticsearch kibana
    fi
    
    log_success "服务启动完成"
}

# 健康检查
health_check() {
    log_info "执行健康检查..."
    
    local max_attempts=30
    local attempt=1
    
    while [[ $attempt -le $max_attempts ]]; do
        if docker-compose -f "$COMPOSE_FILE" ps | grep -q "healthy"; then
            log_success "健康检查通过"
            return 0
        fi
        
        log_info "等待服务健康... ($attempt/$max_attempts)"
        sleep 5
        ((attempt++))
    done
    
    log_error "健康检查失败"
    return 1
}

# 显示部署状态
show_status() {
    log_info "显示部署状态..."
    
    echo
    echo "=== Docker服务状态 ==="
    docker-compose -f "$COMPOSE_FILE" ps
    echo
    
    echo "=== 容器资源使用 ==="
    docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}\t{{.BlockIO}}"
    echo
    
    echo "=== 镜像信息 ==="
    docker images | grep "$PROJECT_NAME"
    echo
    
    echo "=== 数据卷信息 ==="
    docker volume ls | grep "$PROJECT_NAME" || echo "无相关数据卷"
    echo
    
    echo "=== 网络信息 ==="
    docker network ls | grep "$PROJECT_NAME" || echo "无相关网络"
    echo
    
    echo "=== 访问地址 ==="
    echo "应用地址: http://localhost"
    echo "Grafana监控: http://localhost:3000 (如已启用)"
    echo "Kibana日志: http://localhost:5601 (如已启用)"
    echo
}

# 查看日志
show_logs() {
    local service="${1:-app}"
    log_info "显示 $service 服务日志..."
    docker-compose -f "$COMPOSE_FILE" logs -f --tail=100 "$service"
}

# 回滚部署
rollback() {
    log_warning "开始回滚部署..."
    
    local latest_backup=$(sudo find "$BACKUP_DIR" -maxdepth 1 -type d -name "backup_*" | sort -r | head -n 1)
    
    if [[ -z "$latest_backup" ]]; then
        log_error "未找到备份，无法回滚"
        exit 1
    fi
    
    log_info "回滚到备份: $latest_backup"
    
    # 停止服务
    stop_services
    
    # 恢复镜像
    if [[ -f "$latest_backup/image.tar.gz" ]]; then
        log_info "恢复Docker镜像..."
        gunzip -c "$latest_backup/image.tar.gz" | docker load
    fi
    
    # 恢复配置
    if [[ -f "$latest_backup/$ENV_FILE" ]]; then
        cp "$latest_backup/$ENV_FILE" "./"
    fi
    
    # 重启服务
    start_services
    
    if health_check; then
        log_success "回滚成功"
    else
        log_error "回滚后健康检查失败"
        exit 1
    fi
}

# 清理部署
cleanup_deployment() {
    log_warning "清理部署环境..."
    
    read -p "确定要清理所有Docker资源吗? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 0
    fi
    
    # 停止并删除服务
    docker-compose -f "$COMPOSE_FILE" down -v --remove-orphans
    
    # 删除镜像
    docker rmi "$PROJECT_NAME:latest" 2>/dev/null || true
    
    # 清理系统
    docker system prune -af --volumes
    
    log_success "清理完成"
}

# 显示帮助信息
show_help() {
    echo "《攻城掠地》桌游项目 - Docker部署脚本"
    echo
    echo "用法: $0 [选项]"
    echo
    echo "选项:"
    echo "  deploy     执行完整部署 (默认)"
    echo "  start      启动服务"
    echo "  stop       停止服务"
    echo "  restart    重启服务"
    echo "  status     显示状态"
    echo "  logs       查看日志 [服务名]"
    echo "  build      重新构建镜像"
    echo "  rollback   回滚到上一个版本"
    echo "  cleanup    清理部署环境"
    echo "  help       显示此帮助信息"
    echo
    echo "环境变量:"
    echo "  ENABLE_MONITORING=true   启用监控服务"
    echo "  ENABLE_LOGGING=true      启用日志服务"
    echo
}

# 主函数
main() {
    local action="${1:-deploy}"
    
    case "$action" in
        "deploy")
            log_info "开始Docker部署..."
            check_permissions
            check_docker
            check_resources
            create_directories
            setup_environment
            create_backup
            stop_services
            cleanup_old_resources
            build_images
            start_services
            
            if health_check; then
                show_status
                log_success "Docker部署完成！"
            else
                log_error "部署失败，正在回滚..."
                rollback
                exit 1
            fi
            ;;
        "start")
            start_services
            health_check
            ;;
        "stop")
            stop_services
            ;;
        "restart")
            stop_services
            start_services
            health_check
            ;;
        "status")
            show_status
            ;;
        "logs")
            show_logs "$2"
            ;;
        "build")
            build_images
            ;;
        "rollback")
            rollback
            ;;
        "cleanup")
            cleanup_deployment
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