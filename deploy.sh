#!/bin/bash

# 《攻城掠地》桌游部署脚本
# 支持多种部署方式

set -e

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

# 检查依赖
check_dependencies() {
    log_info "检查系统依赖..."
    
    # 检查 Node.js
    if ! command -v node &> /dev/null; then
        log_error "Node.js 未安装，请先安装 Node.js"
        exit 1
    fi
    
    local node_version=$(node --version)
    log_success "Node.js 版本: $node_version"
    
    # 检查 Docker（可选）
    if command -v docker &> /dev/null; then
        local docker_version=$(docker --version)
        log_success "Docker 版本: $docker_version"
    else
        log_warning "Docker 未安装，将跳过 Docker 相关部署选项"
    fi
}

# 本地部署
deploy_local() {
    log_info "开始本地部署..."
    
    # 检查端口是否被占用
    if lsof -Pi :8000 -sTCP:LISTEN -t >/dev/null ; then
        log_warning "端口 8000 已被占用，尝试终止现有进程..."
        pkill -f "node server.js" || true
        sleep 2
    fi
    
    # 启动服务器
    log_info "启动服务器..."
    node server.js &
    
    # 等待服务器启动
    sleep 3
    
    # 检查服务器是否启动成功
    if curl -s http://localhost:8000 > /dev/null; then
        log_success "本地部署成功！访问地址: http://localhost:8000"
    else
        log_error "本地部署失败，请检查日志"
        exit 1
    fi
}

# Docker 部署
deploy_docker() {
    log_info "开始 Docker 部署..."
    
    if ! command -v docker &> /dev/null; then
        log_error "Docker 未安装，无法进行 Docker 部署"
        exit 1
    fi
    
    # 构建镜像
    log_info "构建 Docker 镜像..."
    docker build -t siege-board-game .
    
    # 停止现有容器
    docker stop siege-board-game 2>/dev/null || true
    docker rm siege-board-game 2>/dev/null || true
    
    # 启动容器
    log_info "启动 Docker 容器..."
    docker run -d --name siege-board-game -p 8000:8000 siege-board-game
    
    # 等待容器启动
    sleep 5
    
    # 检查容器状态
    if docker ps | grep -q siege-board-game; then
        log_success "Docker 部署成功！访问地址: http://localhost:8000"
    else
        log_error "Docker 部署失败，请检查容器日志"
        docker logs siege-board-game
        exit 1
    fi
}

# Docker Compose 部署
deploy_docker_compose() {
    log_info "开始 Docker Compose 部署..."
    
    if ! command -v docker-compose &> /dev/null; then
        log_error "Docker Compose 未安装，无法进行 Docker Compose 部署"
        exit 1
    fi
    
    # 停止现有服务
    docker-compose down 2>/dev/null || true
    
    # 启动服务
    log_info "启动 Docker Compose 服务..."
    docker-compose up -d
    
    # 等待服务启动
    sleep 5
    
    # 检查服务状态
    if docker-compose ps | grep -q "Up"; then
        log_success "Docker Compose 部署成功！访问地址: http://localhost:8000"
    else
        log_error "Docker Compose 部署失败，请检查服务日志"
        docker-compose logs
        exit 1
    fi
}

# 生产环境部署
deploy_production() {
    log_info "开始生产环境部署..."
    
    # 设置环境变量
    export NODE_ENV=production
    export PORT=${PORT:-8000}
    export HOST=${HOST:-0.0.0.0}
    
    # 检查 PM2
    if ! command -v pm2 &> /dev/null; then
        log_info "安装 PM2..."
        npm install -g pm2
    fi
    
    # 停止现有进程
    pm2 stop siege-game 2>/dev/null || true
    pm2 delete siege-game 2>/dev/null || true
    
    # 启动应用
    log_info "使用 PM2 启动应用..."
    pm2 start server.js --name siege-game
    
    # 保存 PM2 配置
    pm2 save
    
    # 设置开机自启
    pm2 startup
    
    log_success "生产环境部署成功！"
    log_info "使用 'pm2 status' 查看应用状态"
    log_info "使用 'pm2 logs siege-game' 查看应用日志"
}

# 清理函数
cleanup() {
    log_info "清理临时文件..."
    # 这里可以添加清理逻辑
}

# 显示帮助信息
show_help() {
    echo "《攻城掠地》桌游部署脚本"
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  local       本地部署（默认）"
    echo "  docker      Docker 部署"
    echo "  compose     Docker Compose 部署"
    echo "  production  生产环境部署（使用 PM2）"
    echo "  check       检查系统依赖"
    echo "  help        显示此帮助信息"
    echo ""
    echo "示例:"
    echo "  $0 local      # 本地部署"
    echo "  $0 docker     # Docker 部署"
    echo "  $0 production # 生产环境部署"
}

# 主函数
main() {
    local deployment_type=${1:-local}
    
    case $deployment_type in
        "local")
            check_dependencies
            deploy_local
            ;;
        "docker")
            check_dependencies
            deploy_docker
            ;;
        "compose")
            check_dependencies
            deploy_docker_compose
            ;;
        "production")
            check_dependencies
            deploy_production
            ;;
        "check")
            check_dependencies
            ;;
        "help"|"-h"|"--help")
            show_help
            ;;
        *)
            log_error "未知的部署类型: $deployment_type"
            show_help
            exit 1
            ;;
    esac
}

# 设置清理陷阱
trap cleanup EXIT

# 执行主函数
main "$@"