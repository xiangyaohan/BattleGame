#!/bin/bash

# 《攻城掠地》桌游项目 - 健康检查脚本
# 用于检查应用和相关服务的健康状态

set -euo pipefail

# 配置变量
APP_NAME="siege-game"
APP_URL="http://localhost:8000"
NGINX_URL="http://localhost:80"
REDIS_HOST="localhost"
REDIS_PORT="6379"
PROMETHEUS_URL="http://localhost:9090"
GRAFANA_URL="http://localhost:3000"

# 日志配置
LOG_FILE="/var/log/siege-game/health-check.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log() {
    echo -e "${TIMESTAMP} $1" | tee -a "${LOG_FILE}"
}

log_info() {
    log "${BLUE}[INFO]${NC} $1"
}

log_success() {
    log "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    log "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    log "${RED}[ERROR]${NC} $1"
}

# 检查HTTP服务
check_http_service() {
    local service_name="$1"
    local url="$2"
    local timeout="${3:-10}"
    
    log_info "检查 ${service_name} 服务: ${url}"
    
    if curl -f -s --max-time "${timeout}" "${url}" > /dev/null; then
        log_success "${service_name} 服务正常"
        return 0
    else
        log_error "${service_name} 服务异常"
        return 1
    fi
}

# 检查TCP端口
check_tcp_port() {
    local service_name="$1"
    local host="$2"
    local port="$3"
    local timeout="${4:-5}"
    
    log_info "检查 ${service_name} TCP端口: ${host}:${port}"
    
    if timeout "${timeout}" bash -c "</dev/tcp/${host}/${port}"; then
        log_success "${service_name} 端口可访问"
        return 0
    else
        log_error "${service_name} 端口不可访问"
        return 1
    fi
}

# 检查进程
check_process() {
    local process_name="$1"
    
    log_info "检查进程: ${process_name}"
    
    if pgrep -f "${process_name}" > /dev/null; then
        local pid=$(pgrep -f "${process_name}")
        log_success "${process_name} 进程运行中 (PID: ${pid})"
        return 0
    else
        log_error "${process_name} 进程未运行"
        return 1
    fi
}

# 检查PM2进程
check_pm2_process() {
    local app_name="$1"
    
    log_info "检查PM2应用: ${app_name}"
    
    if command -v pm2 > /dev/null; then
        local status=$(pm2 jlist | jq -r ".[] | select(.name==\"${app_name}\") | .pm2_env.status" 2>/dev/null || echo "not_found")
        
        if [ "${status}" = "online" ]; then
            log_success "PM2应用 ${app_name} 运行正常"
            return 0
        else
            log_error "PM2应用 ${app_name} 状态异常: ${status}"
            return 1
        fi
    else
        log_warning "PM2未安装，跳过PM2检查"
        return 1
    fi
}

# 检查Docker容器
check_docker_container() {
    local container_name="$1"
    
    log_info "检查Docker容器: ${container_name}"
    
    if command -v docker > /dev/null; then
        local status=$(docker inspect --format='{{.State.Status}}' "${container_name}" 2>/dev/null || echo "not_found")
        
        if [ "${status}" = "running" ]; then
            log_success "Docker容器 ${container_name} 运行正常"
            return 0
        else
            log_error "Docker容器 ${container_name} 状态异常: ${status}"
            return 1
        fi
    else
        log_warning "Docker未安装，跳过Docker检查"
        return 1
    fi
}

# 检查磁盘空间
check_disk_space() {
    local threshold="${1:-85}"
    
    log_info "检查磁盘空间使用率 (阈值: ${threshold}%)"
    
    local usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    
    if [ "${usage}" -lt "${threshold}" ]; then
        log_success "磁盘空间使用率正常: ${usage}%"
        return 0
    else
        log_warning "磁盘空间使用率过高: ${usage}%"
        return 1
    fi
}

# 检查内存使用
check_memory_usage() {
    local threshold="${1:-85}"
    
    log_info "检查内存使用率 (阈值: ${threshold}%)"
    
    local usage=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    if [ "${usage}" -lt "${threshold}" ]; then
        log_success "内存使用率正常: ${usage}%"
        return 0
    else
        log_warning "内存使用率过高: ${usage}%"
        return 1
    fi
}

# 检查CPU负载
check_cpu_load() {
    local threshold="${1:-2.0}"
    
    log_info "检查CPU负载 (阈值: ${threshold})"
    
    local load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    
    if (( $(echo "${load} < ${threshold}" | bc -l) )); then
        log_success "CPU负载正常: ${load}"
        return 0
    else
        log_warning "CPU负载过高: ${load}"
        return 1
    fi
}

# 检查SSL证书
check_ssl_certificate() {
    local domain="$1"
    local days_threshold="${2:-7}"
    
    log_info "检查SSL证书: ${domain} (阈值: ${days_threshold}天)"
    
    if command -v openssl > /dev/null; then
        local expiry_date=$(echo | openssl s_client -servername "${domain}" -connect "${domain}:443" 2>/dev/null | openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
        local expiry_epoch=$(date -d "${expiry_date}" +%s)
        local current_epoch=$(date +%s)
        local days_left=$(( (expiry_epoch - current_epoch) / 86400 ))
        
        if [ "${days_left}" -gt "${days_threshold}" ]; then
            log_success "SSL证书有效，剩余 ${days_left} 天"
            return 0
        else
            log_warning "SSL证书即将过期，剩余 ${days_left} 天"
            return 1
        fi
    else
        log_warning "OpenSSL未安装，跳过SSL检查"
        return 1
    fi
}

# 检查日志文件
check_log_files() {
    local log_dir="/var/log/siege-game"
    local max_size_mb="${1:-100}"
    
    log_info "检查日志文件大小 (阈值: ${max_size_mb}MB)"
    
    if [ -d "${log_dir}" ]; then
        find "${log_dir}" -name "*.log" -type f | while read -r log_file; do
            local size_mb=$(du -m "${log_file}" | cut -f1)
            if [ "${size_mb}" -gt "${max_size_mb}" ]; then
                log_warning "日志文件过大: ${log_file} (${size_mb}MB)"
            else
                log_success "日志文件大小正常: ${log_file} (${size_mb}MB)"
            fi
        done
    else
        log_warning "日志目录不存在: ${log_dir}"
        return 1
    fi
}

# 应用特定健康检查
check_app_health() {
    log_info "执行应用特定健康检查"
    
    # 检查应用API端点
    if curl -f -s --max-time 10 "${APP_URL}/health" > /dev/null; then
        log_success "应用健康检查端点正常"
    else
        log_error "应用健康检查端点异常"
        return 1
    fi
    
    # 检查应用版本信息
    local version=$(curl -s --max-time 5 "${APP_URL}/version" 2>/dev/null || echo "unknown")
    log_info "应用版本: ${version}"
    
    # 检查应用响应时间
    local response_time=$(curl -o /dev/null -s -w '%{time_total}' "${APP_URL}")
    if (( $(echo "${response_time} < 2.0" | bc -l) )); then
        log_success "应用响应时间正常: ${response_time}s"
    else
        log_warning "应用响应时间过长: ${response_time}s"
    fi
    
    return 0
}

# 生成健康报告
generate_health_report() {
    local report_file="/var/log/siege-game/health-report-$(date +%Y%m%d-%H%M%S).json"
    
    log_info "生成健康报告: ${report_file}"
    
    cat > "${report_file}" << EOF
{
  "timestamp": "${TIMESTAMP}",
  "application": "${APP_NAME}",
  "checks": {
    "app_service": $(check_http_service "应用服务" "${APP_URL}" && echo "true" || echo "false"),
    "nginx_service": $(check_http_service "Nginx服务" "${NGINX_URL}" && echo "true" || echo "false"),
    "redis_service": $(check_tcp_port "Redis服务" "${REDIS_HOST}" "${REDIS_PORT}" && echo "true" || echo "false"),
    "disk_space": $(check_disk_space && echo "true" || echo "false"),
    "memory_usage": $(check_memory_usage && echo "true" || echo "false"),
    "cpu_load": $(check_cpu_load && echo "true" || echo "false")
  },
  "system_info": {
    "hostname": "$(hostname)",
    "uptime": "$(uptime)",
    "disk_usage": "$(df -h / | awk 'NR==2 {print $5}')",
    "memory_usage": "$(free -h | awk 'NR==2{printf "%s/%s (%.2f%%)", $3,$2,$3*100/$2}')",
    "load_average": "$(uptime | awk -F'load average:' '{print $2}')"
  }
}
EOF
    
    log_success "健康报告已生成: ${report_file}"
}

# 主函数
main() {
    local mode="${1:-full}"
    local exit_code=0
    
    log_info "开始健康检查 - 模式: ${mode}"
    
    # 创建日志目录
    mkdir -p "$(dirname "${LOG_FILE}")"
    
    case "${mode}" in
        "quick")
            # 快速检查
            check_http_service "应用服务" "${APP_URL}" || exit_code=1
            check_tcp_port "Redis服务" "${REDIS_HOST}" "${REDIS_PORT}" || exit_code=1
            ;;
        "app")
            # 应用检查
            check_app_health || exit_code=1
            check_pm2_process "${APP_NAME}" || exit_code=1
            ;;
        "system")
            # 系统检查
            check_disk_space || exit_code=1
            check_memory_usage || exit_code=1
            check_cpu_load || exit_code=1
            ;;
        "full"|*)
            # 完整检查
            check_http_service "应用服务" "${APP_URL}" || exit_code=1
            check_http_service "Nginx服务" "${NGINX_URL}" || exit_code=1
            check_tcp_port "Redis服务" "${REDIS_HOST}" "${REDIS_PORT}" || exit_code=1
            check_pm2_process "${APP_NAME}" || exit_code=1
            check_disk_space || exit_code=1
            check_memory_usage || exit_code=1
            check_cpu_load || exit_code=1
            check_log_files || exit_code=1
            check_app_health || exit_code=1
            generate_health_report
            ;;
    esac
    
    if [ ${exit_code} -eq 0 ]; then
        log_success "健康检查完成 - 所有检查通过"
    else
        log_error "健康检查完成 - 发现问题"
    fi
    
    exit ${exit_code}
}

# 显示帮助信息
show_help() {
    cat << EOF
《攻城掠地》桌游项目健康检查脚本

用法: $0 [模式]

模式:
  quick   - 快速检查 (应用服务、Redis)
  app     - 应用检查 (应用健康、PM2进程)
  system  - 系统检查 (磁盘、内存、CPU)
  full    - 完整检查 (默认，包含所有检查项)

示例:
  $0              # 执行完整检查
  $0 quick        # 执行快速检查
  $0 app          # 执行应用检查
  $0 system       # 执行系统检查

日志文件: ${LOG_FILE}
EOF
}

# 脚本入口
if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    show_help
    exit 0
fi

main "$@"