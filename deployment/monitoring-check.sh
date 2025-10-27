#!/bin/bash

# 《攻城掠地》桌游项目 - 监控检查脚本
# 用于定期检查系统状态并发送告警

set -euo pipefail

# 配置
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
LOG_FILE="/var/log/siege-game/monitoring.log"
ALERT_EMAIL="admin@yourdomain.com"
WEBHOOK_URL=""  # Slack/Discord webhook URL
APP_URL="http://localhost:8000"
NGINX_URL="http://localhost"

# 阈值配置
CPU_THRESHOLD=80
MEMORY_THRESHOLD=85
DISK_THRESHOLD=90
RESPONSE_TIME_THRESHOLD=5000  # 毫秒

# 日志函数
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# 发送告警
send_alert() {
    local level="$1"
    local message="$2"
    local subject="[Siege Game] $level Alert"
    
    log "$level: $message"
    
    # 发送邮件告警
    if command -v mail >/dev/null 2>&1; then
        echo "$message" | mail -s "$subject" "$ALERT_EMAIL"
    fi
    
    # 发送Webhook告警
    if [[ -n "$WEBHOOK_URL" ]]; then
        curl -X POST "$WEBHOOK_URL" \
            -H "Content-Type: application/json" \
            -d "{\"text\":\"$subject: $message\"}" \
            >/dev/null 2>&1 || true
    fi
}

# 检查CPU使用率
check_cpu() {
    local cpu_usage
    cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}')
    
    if (( $(echo "$cpu_usage > $CPU_THRESHOLD" | bc -l) )); then
        send_alert "WARNING" "High CPU usage: ${cpu_usage}%"
        return 1
    fi
    
    log "CPU usage: ${cpu_usage}% (OK)"
    return 0
}

# 检查内存使用率
check_memory() {
    local memory_usage
    memory_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
    
    if (( $(echo "$memory_usage > $MEMORY_THRESHOLD" | bc -l) )); then
        send_alert "WARNING" "High memory usage: ${memory_usage}%"
        return 1
    fi
    
    log "Memory usage: ${memory_usage}% (OK)"
    return 0
}

# 检查磁盘使用率
check_disk() {
    local disk_usage
    disk_usage=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
    
    if [[ $disk_usage -gt $DISK_THRESHOLD ]]; then
        send_alert "CRITICAL" "High disk usage: ${disk_usage}%"
        return 1
    fi
    
    log "Disk usage: ${disk_usage}% (OK)"
    return 0
}

# 检查应用服务
check_app_service() {
    local response_time
    local http_code
    
    # 检查HTTP响应
    if ! response_time=$(curl -o /dev/null -s -w "%{time_total}" -m 10 "$APP_URL" 2>/dev/null); then
        send_alert "CRITICAL" "Application is not responding at $APP_URL"
        return 1
    fi
    
    # 转换为毫秒
    response_time_ms=$(echo "$response_time * 1000" | bc)
    
    # 检查响应时间
    if (( $(echo "$response_time_ms > $RESPONSE_TIME_THRESHOLD" | bc -l) )); then
        send_alert "WARNING" "Slow application response: ${response_time_ms}ms"
        return 1
    fi
    
    # 检查HTTP状态码
    http_code=$(curl -o /dev/null -s -w "%{http_code}" -m 10 "$APP_URL" 2>/dev/null)
    if [[ "$http_code" != "200" ]]; then
        send_alert "WARNING" "Application returned HTTP $http_code"
        return 1
    fi
    
    log "Application service: ${response_time_ms}ms, HTTP $http_code (OK)"
    return 0
}

# 检查Nginx服务
check_nginx_service() {
    if ! systemctl is-active --quiet nginx; then
        send_alert "CRITICAL" "Nginx service is not running"
        return 1
    fi
    
    # 检查Nginx响应
    if ! curl -f -s "$NGINX_URL" >/dev/null; then
        send_alert "CRITICAL" "Nginx is not responding"
        return 1
    fi
    
    log "Nginx service: Running (OK)"
    return 0
}

# 检查PM2进程
check_pm2_processes() {
    if ! command -v pm2 >/dev/null 2>&1; then
        log "PM2 not installed, skipping check"
        return 0
    fi
    
    local stopped_processes
    stopped_processes=$(pm2 jlist | jq -r '.[] | select(.pm2_env.status != "online") | .name' 2>/dev/null || echo "")
    
    if [[ -n "$stopped_processes" ]]; then
        send_alert "CRITICAL" "PM2 processes not running: $stopped_processes"
        return 1
    fi
    
    log "PM2 processes: All online (OK)"
    return 0
}

# 检查Docker容器
check_docker_containers() {
    if ! command -v docker >/dev/null 2>&1; then
        log "Docker not installed, skipping check"
        return 0
    fi
    
    local unhealthy_containers
    unhealthy_containers=$(docker ps --filter "health=unhealthy" --format "{{.Names}}" 2>/dev/null || echo "")
    
    if [[ -n "$unhealthy_containers" ]]; then
        send_alert "WARNING" "Unhealthy Docker containers: $unhealthy_containers"
        return 1
    fi
    
    local stopped_containers
    stopped_containers=$(docker ps -a --filter "status=exited" --format "{{.Names}}" 2>/dev/null || echo "")
    
    if [[ -n "$stopped_containers" ]]; then
        send_alert "WARNING" "Stopped Docker containers: $stopped_containers"
        return 1
    fi
    
    log "Docker containers: All healthy (OK)"
    return 0
}

# 检查Redis服务
check_redis_service() {
    if ! command -v redis-cli >/dev/null 2>&1; then
        log "Redis not installed, skipping check"
        return 0
    fi
    
    if ! redis-cli ping >/dev/null 2>&1; then
        send_alert "CRITICAL" "Redis service is not responding"
        return 1
    fi
    
    log "Redis service: Running (OK)"
    return 0
}

# 检查SSL证书
check_ssl_certificate() {
    local domain="yourdomain.com"  # 替换为实际域名
    
    if ! command -v openssl >/dev/null 2>&1; then
        log "OpenSSL not installed, skipping SSL check"
        return 0
    fi
    
    local cert_expiry
    cert_expiry=$(echo | openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | \
                  openssl x509 -noout -dates | grep notAfter | cut -d= -f2)
    
    if [[ -n "$cert_expiry" ]]; then
        local expiry_timestamp
        expiry_timestamp=$(date -d "$cert_expiry" +%s)
        local current_timestamp
        current_timestamp=$(date +%s)
        local days_until_expiry
        days_until_expiry=$(( (expiry_timestamp - current_timestamp) / 86400 ))
        
        if [[ $days_until_expiry -lt 30 ]]; then
            send_alert "WARNING" "SSL certificate expires in $days_until_expiry days"
            return 1
        fi
        
        log "SSL certificate: Valid for $days_until_expiry days (OK)"
    else
        log "SSL certificate: Could not check (domain not configured)"
    fi
    
    return 0
}

# 检查日志错误
check_log_errors() {
    local error_count
    local log_files=(
        "/var/log/siege-game/app.log"
        "/var/log/nginx/error.log"
        "/var/log/siege-game/pm2-error.log"
    )
    
    for log_file in "${log_files[@]}"; do
        if [[ -f "$log_file" ]]; then
            # 检查最近5分钟的错误
            error_count=$(grep -c "ERROR\|CRITICAL\|FATAL" "$log_file" 2>/dev/null | tail -100 | wc -l || echo "0")
            
            if [[ $error_count -gt 10 ]]; then
                send_alert "WARNING" "High error count in $log_file: $error_count errors"
            fi
        fi
    done
    
    log "Log error check: Completed"
    return 0
}

# 生成监控报告
generate_report() {
    local timestamp
    timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    cat > "/tmp/monitoring-report.json" <<EOF
{
    "timestamp": "$timestamp",
    "checks": {
        "cpu": $(check_cpu && echo "true" || echo "false"),
        "memory": $(check_memory && echo "true" || echo "false"),
        "disk": $(check_disk && echo "true" || echo "false"),
        "app_service": $(check_app_service && echo "true" || echo "false"),
        "nginx_service": $(check_nginx_service && echo "true" || echo "false"),
        "pm2_processes": $(check_pm2_processes && echo "true" || echo "false"),
        "docker_containers": $(check_docker_containers && echo "true" || echo "false"),
        "redis_service": $(check_redis_service && echo "true" || echo "false"),
        "ssl_certificate": $(check_ssl_certificate && echo "true" || echo "false")
    }
}
EOF
}

# 主函数
main() {
    log "Starting monitoring check..."
    
    local failed_checks=0
    
    # 执行所有检查
    check_cpu || ((failed_checks++))
    check_memory || ((failed_checks++))
    check_disk || ((failed_checks++))
    check_app_service || ((failed_checks++))
    check_nginx_service || ((failed_checks++))
    check_pm2_processes || ((failed_checks++))
    check_docker_containers || ((failed_checks++))
    check_redis_service || ((failed_checks++))
    check_ssl_certificate || ((failed_checks++))
    check_log_errors
    
    # 生成报告
    generate_report
    
    if [[ $failed_checks -eq 0 ]]; then
        log "All monitoring checks passed"
    else
        log "Monitoring completed with $failed_checks failed checks"
        send_alert "INFO" "Monitoring completed with $failed_checks failed checks"
    fi
    
    return $failed_checks
}

# 显示帮助
show_help() {
    cat <<EOF
《攻城掠地》桌游项目 - 监控检查脚本

用法: $0 [选项]

选项:
    -h, --help          显示此帮助信息
    --cpu              仅检查CPU使用率
    --memory           仅检查内存使用率
    --disk             仅检查磁盘使用率
    --app              仅检查应用服务
    --nginx            仅检查Nginx服务
    --pm2              仅检查PM2进程
    --docker           仅检查Docker容器
    --redis            仅检查Redis服务
    --ssl              仅检查SSL证书
    --logs             仅检查日志错误
    --report           生成监控报告

示例:
    $0                 # 执行所有检查
    $0 --cpu --memory  # 仅检查CPU和内存
    $0 --report        # 生成监控报告

配置文件位置: $SCRIPT_DIR/monitoring.conf
日志文件位置: $LOG_FILE
EOF
}

# 解析命令行参数
case "${1:-}" in
    -h|--help)
        show_help
        exit 0
        ;;
    --cpu)
        check_cpu
        exit $?
        ;;
    --memory)
        check_memory
        exit $?
        ;;
    --disk)
        check_disk
        exit $?
        ;;
    --app)
        check_app_service
        exit $?
        ;;
    --nginx)
        check_nginx_service
        exit $?
        ;;
    --pm2)
        check_pm2_processes
        exit $?
        ;;
    --docker)
        check_docker_containers
        exit $?
        ;;
    --redis)
        check_redis_service
        exit $?
        ;;
    --ssl)
        check_ssl_certificate
        exit $?
        ;;
    --logs)
        check_log_errors
        exit $?
        ;;
    --report)
        generate_report
        cat "/tmp/monitoring-report.json"
        exit 0
        ;;
    "")
        main
        exit $?
        ;;
    *)
        echo "未知选项: $1"
        echo "使用 $0 --help 查看帮助信息"
        exit 1
        ;;
esac