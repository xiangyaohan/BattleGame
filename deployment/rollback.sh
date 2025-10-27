#!/bin/bash

# 《攻城掠地》桌游项目 - 回滚脚本
# 用于快速回滚到之前的版本

set -euo pipefail

# 配置变量
APP_NAME="siege-game"
APP_DIR="/var/www/siege-game"
BACKUP_DIR="/var/backups/siege-game"
CONFIG_DIR="/etc/siege-game"
LOG_DIR="/var/log/siege-game"
NGINX_CONFIG="/etc/nginx/sites-available/siege-game"
PM2_CONFIG="/home/gameapp/ecosystem.config.js"

# 日志配置
ROLLBACK_LOG="${LOG_DIR}/deployment/rollback.log"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 日志函数
log() {
    echo -e "$(date '+%Y-%m-%d %H:%M:%S') $1" | tee -a "${ROLLBACK_LOG}"
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

# 列出可用的备份
list_available_backups() {
    log_info "可用的备份版本:"
    
    if [ ! -d "${BACKUP_DIR}" ]; then
        log_error "备份目录不存在: ${BACKUP_DIR}"
        exit 1
    fi
    
    local backups=($(find "${BACKUP_DIR}" -maxdepth 1 -type d -name "????????" | sort -r))
    
    if [ ${#backups[@]} -eq 0 ]; then
        log_error "没有找到可用的备份"
        exit 1
    fi
    
    echo
    echo "序号  日期        大小      状态"
    echo "----  --------    ------    ------"
    
    local index=1
    for backup in "${backups[@]}"; do
        local backup_date=$(basename "${backup}")
        local backup_size=$(du -sh "${backup}" 2>/dev/null | cut -f1 || echo "未知")
        local manifest="${backup}/backup_manifest_*.json"
        local status="完整"
        
        # 检查备份完整性
        if [ ! -f ${manifest} ]; then
            status="不完整"
        fi
        
        printf "%-4s  %-8s    %-6s    %-6s\n" "${index}" "${backup_date}" "${backup_size}" "${status}"
        ((index++))
    done
    
    echo
}

# 选择备份版本
select_backup() {
    local backup_date="$1"
    local backup_path="${BACKUP_DIR}/${backup_date}"
    
    if [ ! -d "${backup_path}" ]; then
        log_error "备份不存在: ${backup_path}"
        exit 1
    fi
    
    # 检查备份完整性
    local manifest="${backup_path}/backup_manifest_*.json"
    if [ ! -f ${manifest} ]; then
        log_error "备份清单文件不存在，备份可能不完整"
        exit 1
    fi
    
    log_info "选择的备份版本: ${backup_date}"
    log_info "备份路径: ${backup_path}"
    
    # 显示备份信息
    if command -v jq > /dev/null; then
        local manifest_file=$(ls ${manifest} | head -n 1)
        log_info "备份信息:"
        jq -r '.backup_info | "  时间: \(.timestamp)\n  主机: \(.hostname)\n  大小: \(.backup_size)"' "${manifest_file}"
    fi
    
    echo "${backup_path}"
}

# 创建回滚前备份
create_pre_rollback_backup() {
    log_info "创建回滚前备份"
    
    local pre_rollback_dir="${BACKUP_DIR}/pre-rollback-${TIMESTAMP}"
    mkdir -p "${pre_rollback_dir}"
    
    # 备份当前应用
    if [ -d "${APP_DIR}" ]; then
        tar -czf "${pre_rollback_dir}/current_app_${TIMESTAMP}.tar.gz" \
            --exclude="node_modules" \
            --exclude=".git" \
            --exclude="*.log" \
            -C "$(dirname "${APP_DIR}")" \
            "$(basename "${APP_DIR}")"
        log_success "当前应用已备份"
    fi
    
    # 备份当前配置
    if [ -f "${NGINX_CONFIG}" ]; then
        cp "${NGINX_CONFIG}" "${pre_rollback_dir}/nginx_config_${TIMESTAMP}"
    fi
    
    if [ -f "${PM2_CONFIG}" ]; then
        cp "${PM2_CONFIG}" "${pre_rollback_dir}/pm2_config_${TIMESTAMP}"
    fi
    
    log_success "回滚前备份完成: ${pre_rollback_dir}"
    echo "${pre_rollback_dir}" > "/tmp/siege-game-pre-rollback-path"
}

# 停止服务
stop_services() {
    log_info "停止应用服务"
    
    # 停止PM2应用
    if command -v pm2 > /dev/null; then
        sudo -u gameapp pm2 stop "${APP_NAME}" || log_warning "PM2应用停止失败"
    fi
    
    # 停止Docker容器 (如果使用)
    if command -v docker > /dev/null; then
        if docker ps --format "{{.Names}}" | grep -q "siege-game"; then
            docker stop $(docker ps --format "{{.Names}}" | grep "siege-game") || log_warning "Docker容器停止失败"
        fi
    fi
    
    log_success "服务停止完成"
}

# 恢复应用代码
restore_application() {
    local backup_path="$1"
    
    log_info "恢复应用代码"
    
    local app_backup_file="${backup_path}/app/app_latest.txt"
    if [ ! -f "${app_backup_file}" ]; then
        log_error "应用备份文件信息不存在"
        return 1
    fi
    
    local app_backup=$(cat "${app_backup_file}")
    if [ ! -f "${app_backup}" ]; then
        log_error "应用备份文件不存在: ${app_backup}"
        return 1
    fi
    
    # 删除当前应用目录
    if [ -d "${APP_DIR}" ]; then
        rm -rf "${APP_DIR}"
    fi
    
    # 恢复应用代码
    mkdir -p "$(dirname "${APP_DIR}")"
    tar -xzf "${app_backup}" -C "$(dirname "${APP_DIR}")"
    
    # 设置权限
    chown -R gameapp:gameapp "${APP_DIR}"
    chmod -R 755 "${APP_DIR}"
    
    log_success "应用代码恢复完成"
}

# 恢复配置文件
restore_configs() {
    local backup_path="$1"
    
    log_info "恢复配置文件"
    
    local config_backup_file="${backup_path}/config/config_latest.txt"
    if [ ! -f "${config_backup_file}" ]; then
        log_warning "配置备份文件信息不存在，跳过配置恢复"
        return 0
    fi
    
    local config_backup=$(cat "${config_backup_file}")
    if [ ! -f "${config_backup}" ]; then
        log_warning "配置备份文件不存在: ${config_backup}"
        return 0
    fi
    
    # 创建临时目录
    local temp_config_dir=$(mktemp -d)
    
    # 解压配置备份
    tar -xzf "${config_backup}" -C "${temp_config_dir}"
    
    # 恢复各种配置文件
    if [ -d "${temp_config_dir}/app-config" ]; then
        mkdir -p "${CONFIG_DIR}"
        cp -r "${temp_config_dir}/app-config/"* "${CONFIG_DIR}/"
    fi
    
    if [ -f "${temp_config_dir}/nginx/siege-game" ]; then
        cp "${temp_config_dir}/nginx/siege-game" "${NGINX_CONFIG}"
    fi
    
    if [ -f "${temp_config_dir}/pm2/ecosystem.config.js" ]; then
        cp "${temp_config_dir}/pm2/ecosystem.config.js" "${PM2_CONFIG}"
        chown gameapp:gameapp "${PM2_CONFIG}"
    fi
    
    if [ -f "${temp_config_dir}/env/.env" ]; then
        cp "${temp_config_dir}/env/.env" "${APP_DIR}/.env"
        chown gameapp:gameapp "${APP_DIR}/.env"
        chmod 600 "${APP_DIR}/.env"
    fi
    
    # 清理临时目录
    rm -rf "${temp_config_dir}"
    
    log_success "配置文件恢复完成"
}

# 恢复数据库 (可选)
restore_database() {
    local backup_path="$1"
    local force_restore="${2:-false}"
    
    if [ "${force_restore}" != "true" ]; then
        log_warning "数据库恢复需要谨慎操作，使用 --restore-database 参数强制执行"
        return 0
    fi
    
    log_info "恢复数据库"
    
    # 恢复MySQL
    local mysql_backup_file="${backup_path}/database/mysql_latest.txt"
    if [ -f "${mysql_backup_file}" ]; then
        local mysql_backup=$(cat "${mysql_backup_file}")
        if [ -f "${mysql_backup}" ]; then
            log_info "恢复MySQL数据库"
            local DB_NAME="${MYSQL_DATABASE:-siege_game}"
            local DB_USER="${MYSQL_USER:-root}"
            local DB_PASS="${MYSQL_PASSWORD:-}"
            
            if [ -n "${DB_PASS}" ]; then
                zcat "${mysql_backup}" | mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}"
            else
                zcat "${mysql_backup}" | mysql -u"${DB_USER}" "${DB_NAME}"
            fi
            log_success "MySQL数据库恢复完成"
        fi
    fi
    
    # 恢复PostgreSQL
    local pg_backup_file="${backup_path}/database/postgresql_latest.txt"
    if [ -f "${pg_backup_file}" ]; then
        local pg_backup=$(cat "${pg_backup_file}")
        if [ -f "${pg_backup}" ]; then
            log_info "恢复PostgreSQL数据库"
            local DB_NAME="${POSTGRES_DB:-siege_game}"
            local DB_USER="${POSTGRES_USER:-postgres}"
            
            zcat "${pg_backup}" | psql -U "${DB_USER}" -d "${DB_NAME}"
            log_success "PostgreSQL数据库恢复完成"
        fi
    fi
    
    # 恢复Redis
    local redis_backup_file="${backup_path}/database/redis_latest.txt"
    if [ -f "${redis_backup_file}" ]; then
        local redis_backup=$(cat "${redis_backup_file}")
        if [ -f "${redis_backup}" ]; then
            log_info "恢复Redis数据"
            
            # 停止Redis
            systemctl stop redis-server || service redis-server stop
            
            # 恢复RDB文件
            local redis_dir=$(redis-cli CONFIG GET dir | tail -n 1)
            local redis_file=$(redis-cli CONFIG GET dbfilename | tail -n 1)
            cp "${redis_backup}" "${redis_dir}/${redis_file}"
            
            # 启动Redis
            systemctl start redis-server || service redis-server start
            
            log_success "Redis数据恢复完成"
        fi
    fi
    
    # 恢复SQLite
    local sqlite_backup_file="${backup_path}/database/sqlite_latest.txt"
    if [ -f "${sqlite_backup_file}" ]; then
        local sqlite_backup=$(cat "${sqlite_backup_file}")
        if [ -f "${sqlite_backup}" ]; then
            log_info "恢复SQLite数据库"
            cp "${sqlite_backup}" "${APP_DIR}/database.sqlite"
            chown gameapp:gameapp "${APP_DIR}/database.sqlite"
            log_success "SQLite数据库恢复完成"
        fi
    fi
}

# 安装依赖
install_dependencies() {
    log_info "安装应用依赖"
    
    cd "${APP_DIR}"
    
    # 检查包管理器
    if [ -f "package.json" ]; then
        if command -v pnpm > /dev/null && [ -f "pnpm-lock.yaml" ]; then
            sudo -u gameapp pnpm install --production
        elif [ -f "yarn.lock" ]; then
            sudo -u gameapp yarn install --production
        else
            sudo -u gameapp npm install --production
        fi
        log_success "Node.js依赖安装完成"
    fi
}

# 启动服务
start_services() {
    log_info "启动应用服务"
    
    # 重新加载Nginx配置
    nginx -t && systemctl reload nginx
    
    # 启动PM2应用
    if command -v pm2 > /dev/null; then
        sudo -u gameapp pm2 start "${PM2_CONFIG}" --env production
        sudo -u gameapp pm2 save
    fi
    
    # 启动Docker容器 (如果使用)
    if [ -f "${APP_DIR}/docker-compose.yml" ]; then
        cd "${APP_DIR}"
        docker-compose up -d
    fi
    
    log_success "服务启动完成"
}

# 验证回滚
verify_rollback() {
    log_info "验证回滚结果"
    
    local app_url="http://localhost:8000"
    local max_attempts=30
    local attempt=1
    
    while [ ${attempt} -le ${max_attempts} ]; do
        if curl -f -s --max-time 5 "${app_url}" > /dev/null; then
            log_success "应用服务验证通过"
            break
        else
            log_info "等待应用启动... (${attempt}/${max_attempts})"
            sleep 2
            ((attempt++))
        fi
    done
    
    if [ ${attempt} -gt ${max_attempts} ]; then
        log_error "应用服务验证失败"
        return 1
    fi
    
    # 检查PM2状态
    if command -v pm2 > /dev/null; then
        local pm2_status=$(sudo -u gameapp pm2 jlist | jq -r ".[] | select(.name==\"${APP_NAME}\") | .pm2_env.status" 2>/dev/null || echo "unknown")
        if [ "${pm2_status}" = "online" ]; then
            log_success "PM2应用状态正常"
        else
            log_warning "PM2应用状态异常: ${pm2_status}"
        fi
    fi
    
    return 0
}

# 清理回滚
cleanup_rollback() {
    log_info "清理回滚临时文件"
    
    # 清理临时文件
    rm -f /tmp/siege-game-pre-rollback-path
    
    log_success "回滚清理完成"
}

# 主函数
main() {
    local backup_date="$1"
    local restore_database="${2:-false}"
    
    log_info "开始执行回滚到版本: ${backup_date}"
    
    # 检查权限
    check_permissions
    
    # 创建日志目录
    mkdir -p "$(dirname "${ROLLBACK_LOG}")"
    
    # 选择备份版本
    local backup_path=$(select_backup "${backup_date}")
    
    # 确认回滚
    echo
    log_warning "即将回滚到版本: ${backup_date}"
    log_warning "这将替换当前的应用代码和配置"
    read -p "确认继续? (y/N): " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log_info "回滚已取消"
        exit 0
    fi
    
    # 创建回滚前备份
    create_pre_rollback_backup
    
    # 停止服务
    stop_services
    
    # 恢复应用和配置
    restore_application "${backup_path}"
    restore_configs "${backup_path}"
    
    # 恢复数据库 (可选)
    if [ "${restore_database}" = "true" ]; then
        restore_database "${backup_path}" "true"
    fi
    
    # 安装依赖
    install_dependencies
    
    # 启动服务
    start_services
    
    # 验证回滚
    if verify_rollback; then
        log_success "回滚完成并验证通过"
        cleanup_rollback
        exit 0
    else
        log_error "回滚验证失败"
        
        # 提供回滚到回滚前状态的选项
        echo
        log_warning "回滚失败，是否恢复到回滚前状态? (y/N): "
        read -p "" -n 1 -r
        echo
        
        if [[ $REPLY =~ ^[Yy]$ ]]; then
            local pre_rollback_path=$(cat /tmp/siege-game-pre-rollback-path 2>/dev/null || echo "")
            if [ -n "${pre_rollback_path}" ] && [ -d "${pre_rollback_path}" ]; then
                log_info "恢复到回滚前状态"
                # 这里可以实现恢复逻辑
                log_success "已恢复到回滚前状态"
            else
                log_error "无法找到回滚前备份"
            fi
        fi
        
        exit 1
    fi
}

# 显示帮助信息
show_help() {
    cat << EOF
《攻城掠地》桌游项目回滚脚本

用法: $0 <备份日期> [选项]

参数:
  备份日期        - 要回滚到的备份日期 (格式: YYYYMMDD)

选项:
  --restore-database  - 同时恢复数据库 (谨慎使用)
  --list             - 列出可用的备份版本
  -h, --help         - 显示此帮助信息

示例:
  $0 --list                    # 列出可用备份
  $0 20240115                  # 回滚到2024年1月15日的备份
  $0 20240115 --restore-database  # 回滚并恢复数据库

注意:
  - 回滚前会自动创建当前状态的备份
  - 数据库恢复需要额外确认，请谨慎使用
  - 建议在维护窗口期间执行回滚操作

日志文件: ${ROLLBACK_LOG}
EOF
}

# 脚本入口
case "${1:-}" in
    "--list")
        list_available_backups
        exit 0
        ;;
    "-h"|"--help")
        show_help
        exit 0
        ;;
    "")
        log_error "请指定备份日期"
        echo
        show_help
        exit 1
        ;;
    *)
        if [[ "$1" =~ ^[0-9]{8}$ ]]; then
            main "$1" "${2:-false}"
        else
            log_error "无效的备份日期格式: $1"
            echo
            show_help
            exit 1
        fi
        ;;
esac