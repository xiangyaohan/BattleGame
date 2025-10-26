#!/bin/bash

# 《攻城掠地》桌游项目 - 备份脚本
# 用于备份应用代码、配置文件、数据库和日志

set -euo pipefail

# 配置变量
APP_NAME="siege-game"
APP_DIR="/var/www/siege-game"
BACKUP_DIR="/var/backups/siege-game"
CONFIG_DIR="/etc/siege-game"
LOG_DIR="/var/log/siege-game"
NGINX_CONFIG="/etc/nginx/sites-available/siege-game"
PM2_CONFIG="/home/gameapp/ecosystem.config.js"

# 备份保留策略
DAILY_RETENTION=7      # 保留7天的每日备份
WEEKLY_RETENTION=4     # 保留4周的每周备份
MONTHLY_RETENTION=12   # 保留12个月的月度备份

# 日志配置
BACKUP_LOG="${LOG_DIR}/backup/backup.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')
DATE_ONLY=$(date '+%Y%m%d')

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log() {
    echo -e "$(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "${BACKUP_LOG}"
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

# 检查权限
check_permissions() {
    if [[ $EUID -ne 0 ]]; then
        log_error "此脚本需要root权限运行"
        exit 1
    fi
}

# 创建备份目录
create_backup_dirs() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}"
    
    log_info "创建备份目录: ${backup_path}"
    
    mkdir -p "${backup_path}"/{app,config,logs,database,docker}
    mkdir -p "${LOG_DIR}/backup"
    
    log_success "备份目录创建完成"
}

# 备份应用代码
backup_application() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}/app"
    local app_backup="${backup_path}/app_${TIMESTAMP}.tar.gz"
    
    log_info "备份应用代码: ${APP_DIR}"
    
    if [ -d "${APP_DIR}" ]; then
        tar -czf "${app_backup}" \
            --exclude="node_modules" \
            --exclude=".git" \
            --exclude="*.log" \
            --exclude="tmp" \
            --exclude="uploads" \
            -C "$(dirname "${APP_DIR}")" \
            "$(basename "${APP_DIR}")"
        
        log_success "应用代码备份完成: ${app_backup}"
        echo "${app_backup}" > "${backup_path}/app_latest.txt"
    else
        log_warning "应用目录不存在: ${APP_DIR}"
    fi
}

# 备份配置文件
backup_configs() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}/config"
    local config_backup="${backup_path}/config_${TIMESTAMP}.tar.gz"
    
    log_info "备份配置文件"
    
    # 创建临时目录收集配置文件
    local temp_config_dir=$(mktemp -d)
    
    # 复制各种配置文件
    if [ -d "${CONFIG_DIR}" ]; then
        cp -r "${CONFIG_DIR}" "${temp_config_dir}/app-config"
    fi
    
    if [ -f "${NGINX_CONFIG}" ]; then
        mkdir -p "${temp_config_dir}/nginx"
        cp "${NGINX_CONFIG}" "${temp_config_dir}/nginx/"
    fi
    
    if [ -f "${PM2_CONFIG}" ]; then
        mkdir -p "${temp_config_dir}/pm2"
        cp "${PM2_CONFIG}" "${temp_config_dir}/pm2/"
    fi
    
    # 备份环境变量文件
    if [ -f "${APP_DIR}/.env" ]; then
        mkdir -p "${temp_config_dir}/env"
        cp "${APP_DIR}/.env" "${temp_config_dir}/env/"
    fi
    
    # 备份SSL证书
    if [ -d "/etc/letsencrypt" ]; then
        mkdir -p "${temp_config_dir}/ssl"
        cp -r /etc/letsencrypt "${temp_config_dir}/ssl/"
    fi
    
    # 创建配置备份
    tar -czf "${config_backup}" -C "${temp_config_dir}" .
    
    # 清理临时目录
    rm -rf "${temp_config_dir}"
    
    log_success "配置文件备份完成: ${config_backup}"
    echo "${config_backup}" > "${backup_path}/config_latest.txt"
}

# 备份日志文件
backup_logs() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}/logs"
    local logs_backup="${backup_path}/logs_${TIMESTAMP}.tar.gz"
    
    log_info "备份日志文件: ${LOG_DIR}"
    
    if [ -d "${LOG_DIR}" ]; then
        # 只备份最近7天的日志
        find "${LOG_DIR}" -name "*.log" -mtime -7 -type f | \
        tar -czf "${logs_backup}" --files-from=-
        
        log_success "日志文件备份完成: ${logs_backup}"
        echo "${logs_backup}" > "${backup_path}/logs_latest.txt"
    else
        log_warning "日志目录不存在: ${LOG_DIR}"
    fi
}

# 备份数据库 (如果使用)
backup_database() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}/database"
    
    log_info "检查数据库备份需求"
    
    # 检查是否有MySQL/PostgreSQL
    if command -v mysqldump > /dev/null; then
        backup_mysql "${backup_path}"
    fi
    
    if command -v pg_dump > /dev/null; then
        backup_postgresql "${backup_path}"
    fi
    
    # 备份Redis数据 (如果使用)
    if command -v redis-cli > /dev/null; then
        backup_redis "${backup_path}"
    fi
    
    # 备份SQLite数据库 (如果使用)
    if [ -f "${APP_DIR}/database.sqlite" ]; then
        backup_sqlite "${backup_path}"
    fi
}

# 备份MySQL数据库
backup_mysql() {
    local backup_path="$1"
    local mysql_backup="${backup_path}/mysql_${TIMESTAMP}.sql.gz"
    
    log_info "备份MySQL数据库"
    
    # 这里需要根据实际情况配置数据库连接信息
    local DB_NAME="${MYSQL_DATABASE:-siege_game}"
    local DB_USER="${MYSQL_USER:-root}"
    local DB_PASS="${MYSQL_PASSWORD:-}"
    
    if [ -n "${DB_PASS}" ]; then
        mysqldump -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" | gzip > "${mysql_backup}"
    else
        mysqldump -u"${DB_USER}" "${DB_NAME}" | gzip > "${mysql_backup}"
    fi
    
    log_success "MySQL数据库备份完成: ${mysql_backup}"
    echo "${mysql_backup}" > "${backup_path}/mysql_latest.txt"
}

# 备份PostgreSQL数据库
backup_postgresql() {
    local backup_path="$1"
    local pg_backup="${backup_path}/postgresql_${TIMESTAMP}.sql.gz"
    
    log_info "备份PostgreSQL数据库"
    
    local DB_NAME="${POSTGRES_DB:-siege_game}"
    local DB_USER="${POSTGRES_USER:-postgres}"
    
    pg_dump -U "${DB_USER}" "${DB_NAME}" | gzip > "${pg_backup}"
    
    log_success "PostgreSQL数据库备份完成: ${pg_backup}"
    echo "${pg_backup}" > "${backup_path}/postgresql_latest.txt"
}

# 备份Redis数据
backup_redis() {
    local backup_path="$1"
    local redis_backup="${backup_path}/redis_${TIMESTAMP}.rdb"
    
    log_info "备份Redis数据"
    
    # 触发Redis保存
    redis-cli BGSAVE
    
    # 等待保存完成
    while [ "$(redis-cli LASTSAVE)" = "$(redis-cli LASTSAVE)" ]; do
        sleep 1
    done
    
    # 复制RDB文件
    local redis_dir=$(redis-cli CONFIG GET dir | tail -n 1)
    local redis_file=$(redis-cli CONFIG GET dbfilename | tail -n 1)
    
    if [ -f "${redis_dir}/${redis_file}" ]; then
        cp "${redis_dir}/${redis_file}" "${redis_backup}"
        log_success "Redis数据备份完成: ${redis_backup}"
        echo "${redis_backup}" > "${backup_path}/redis_latest.txt"
    else
        log_warning "Redis数据文件不存在"
    fi
}

# 备份SQLite数据库
backup_sqlite() {
    local backup_path="$1"
    local sqlite_backup="${backup_path}/sqlite_${TIMESTAMP}.db"
    
    log_info "备份SQLite数据库"
    
    cp "${APP_DIR}/database.sqlite" "${sqlite_backup}"
    
    log_success "SQLite数据库备份完成: ${sqlite_backup}"
    echo "${sqlite_backup}" > "${backup_path}/sqlite_latest.txt"
}

# 备份Docker数据 (如果使用)
backup_docker() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}/docker"
    
    log_info "备份Docker相关数据"
    
    if command -v docker > /dev/null; then
        # 备份Docker镜像列表
        docker images --format "table {{.Repository}}:{{.Tag}}\t{{.ID}}\t{{.Size}}" > "${backup_path}/docker_images_${TIMESTAMP}.txt"
        
        # 备份Docker容器列表
        docker ps -a --format "table {{.Names}}\t{{.Image}}\t{{.Status}}" > "${backup_path}/docker_containers_${TIMESTAMP}.txt"
        
        # 备份Docker Compose文件
        if [ -f "${APP_DIR}/docker-compose.yml" ]; then
            cp "${APP_DIR}/docker-compose.yml" "${backup_path}/docker-compose_${TIMESTAMP}.yml"
        fi
        
        # 备份Docker卷数据
        docker volume ls --format "{{.Name}}" | while read -r volume; do
            if [[ "${volume}" == *"siege-game"* ]]; then
                docker run --rm -v "${volume}:/data" -v "${backup_path}:/backup" alpine tar czf "/backup/volume_${volume}_${TIMESTAMP}.tar.gz" -C /data .
                log_info "Docker卷备份完成: ${volume}"
            fi
        done
        
        log_success "Docker数据备份完成"
    else
        log_info "Docker未安装，跳过Docker备份"
    fi
}

# 创建备份清单
create_backup_manifest() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}"
    local manifest="${backup_path}/backup_manifest_${TIMESTAMP}.json"
    
    log_info "创建备份清单: ${manifest}"
    
    cat > "${manifest}" << EOF
{
  "backup_info": {
    "timestamp": "${TIMESTAMP}",
    "date": "${DATE_ONLY}",
    "application": "${APP_NAME}",
    "backup_path": "${backup_path}",
    "hostname": "$(hostname)",
    "backup_size": "$(du -sh "${backup_path}" | cut -f1)"
  },
  "files": {
    "application": "$(cat "${backup_path}/app/app_latest.txt" 2>/dev/null || echo "not_found")",
    "config": "$(cat "${backup_path}/config/config_latest.txt" 2>/dev/null || echo "not_found")",
    "logs": "$(cat "${backup_path}/logs/logs_latest.txt" 2>/dev/null || echo "not_found")",
    "database": {
      "mysql": "$(cat "${backup_path}/database/mysql_latest.txt" 2>/dev/null || echo "not_found")",
      "postgresql": "$(cat "${backup_path}/database/postgresql_latest.txt" 2>/dev/null || echo "not_found")",
      "redis": "$(cat "${backup_path}/database/redis_latest.txt" 2>/dev/null || echo "not_found")",
      "sqlite": "$(cat "${backup_path}/database/sqlite_latest.txt" 2>/dev/null || echo "not_found")"
    }
  },
  "system_info": {
    "disk_usage": "$(df -h / | awk 'NR==2 {print $5}')",
    "memory_usage": "$(free -h | awk 'NR==2{printf "%s/%s", $3,$2}')",
    "load_average": "$(uptime | awk -F'load average:' '{print $2}')"
  }
}
EOF
    
    log_success "备份清单创建完成: ${manifest}"
}

# 清理旧备份
cleanup_old_backups() {
    log_info "清理旧备份文件"
    
    # 清理每日备份 (保留最近7天)
    find "${BACKUP_DIR}" -maxdepth 1 -type d -name "????????" -mtime +${DAILY_RETENTION} | while read -r old_backup; do
        # 检查是否是每周备份 (周日)
        local backup_date=$(basename "${old_backup}")
        local day_of_week=$(date -d "${backup_date:0:4}-${backup_date:4:2}-${backup_date:6:2}" +%u)
        
        if [ "${day_of_week}" -eq 7 ]; then
            # 是周日，检查是否超过每周保留期
            if [ $(find "${old_backup}" -maxdepth 0 -mtime +$((WEEKLY_RETENTION * 7))) ]; then
                # 检查是否是每月备份 (每月1号)
                local day_of_month=$(date -d "${backup_date:0:4}-${backup_date:4:2}-${backup_date:6:2}" +%d)
                
                if [ "${day_of_month}" -eq 1 ]; then
                    # 是每月1号，检查是否超过每月保留期
                    if [ $(find "${old_backup}" -maxdepth 0 -mtime +$((MONTHLY_RETENTION * 30))) ]; then
                        log_info "删除过期月度备份: ${old_backup}"
                        rm -rf "${old_backup}"
                    fi
                else
                    log_info "删除过期周度备份: ${old_backup}"
                    rm -rf "${old_backup}"
                fi
            fi
        else
            log_info "删除过期每日备份: ${old_backup}"
            rm -rf "${old_backup}"
        fi
    done
    
    log_success "旧备份清理完成"
}

# 验证备份完整性
verify_backup() {
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}"
    
    log_info "验证备份完整性"
    
    local errors=0
    
    # 检查应用备份
    if [ -f "${backup_path}/app/app_latest.txt" ]; then
        local app_backup=$(cat "${backup_path}/app/app_latest.txt")
        if [ -f "${app_backup}" ] && [ -s "${app_backup}" ]; then
            log_success "应用备份验证通过"
        else
            log_error "应用备份验证失败"
            ((errors++))
        fi
    fi
    
    # 检查配置备份
    if [ -f "${backup_path}/config/config_latest.txt" ]; then
        local config_backup=$(cat "${backup_path}/config/config_latest.txt")
        if [ -f "${config_backup}" ] && [ -s "${config_backup}" ]; then
            log_success "配置备份验证通过"
        else
            log_error "配置备份验证失败"
            ((errors++))
        fi
    fi
    
    if [ ${errors} -eq 0 ]; then
        log_success "备份完整性验证通过"
        return 0
    else
        log_error "备份完整性验证失败，发现 ${errors} 个错误"
        return 1
    fi
}

# 发送备份通知 (可选)
send_backup_notification() {
    local status="$1"
    local backup_path="${BACKUP_DIR}/${DATE_ONLY}"
    
    # 这里可以集成邮件、Slack、钉钉等通知方式
    log_info "备份通知: ${status}"
    
    # 示例：写入系统日志
    logger -t "siege-game-backup" "备份${status}: ${backup_path}"
}

# 主函数
main() {
    local backup_type="${1:-full}"
    
    log_info "开始执行备份 - 类型: ${backup_type}"
    
    # 检查权限
    check_permissions
    
    # 创建备份目录
    create_backup_dirs
    
    case "${backup_type}" in
        "app")
            backup_application
            ;;
        "config")
            backup_configs
            ;;
        "logs")
            backup_logs
            ;;
        "database")
            backup_database
            ;;
        "docker")
            backup_docker
            ;;
        "full"|*)
            backup_application
            backup_configs
            backup_logs
            backup_database
            backup_docker
            ;;
    esac
    
    # 创建备份清单
    create_backup_manifest
    
    # 验证备份
    if verify_backup; then
        log_success "备份完成并验证通过"
        send_backup_notification "成功"
        
        # 清理旧备份
        cleanup_old_backups
        
        exit 0
    else
        log_error "备份验证失败"
        send_backup_notification "失败"
        exit 1
    fi
}

# 显示帮助信息
show_help() {
    cat << EOF
《攻城掠地》桌游项目备份脚本

用法: $0 [类型]

备份类型:
  full     - 完整备份 (默认，包含所有内容)
  app      - 仅备份应用代码
  config   - 仅备份配置文件
  logs     - 仅备份日志文件
  database - 仅备份数据库
  docker   - 仅备份Docker相关数据

示例:
  $0              # 执行完整备份
  $0 app          # 仅备份应用代码
  $0 database     # 仅备份数据库

备份目录: ${BACKUP_DIR}
日志文件: ${BACKUP_LOG}

保留策略:
  - 每日备份: ${DAILY_RETENTION} 天
  - 每周备份: ${WEEKLY_RETENTION} 周
  - 每月备份: ${MONTHLY_RETENTION} 个月
EOF
}

# 脚本入口
if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
    show_help
    exit 0
fi

main "$@"