@echo off
setlocal enabledelayedexpansion

REM 《攻城掠地》桌游 Windows 部署脚本

echo ========================================
echo 《攻城掠地》桌游部署脚本
echo ========================================
echo.

REM 检查参数
set DEPLOY_TYPE=%1
if "%DEPLOY_TYPE%"=="" set DEPLOY_TYPE=local

REM 检查 Node.js
echo [INFO] 检查 Node.js...
node --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Node.js 未安装，请先安装 Node.js
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('node --version') do set NODE_VERSION=%%i
echo [SUCCESS] Node.js 版本: %NODE_VERSION%

REM 检查 Docker（可选）
echo [INFO] 检查 Docker...
docker --version >nul 2>&1
if errorlevel 1 (
    echo [WARNING] Docker 未安装，将跳过 Docker 相关部署选项
    set DOCKER_AVAILABLE=false
) else (
    for /f "tokens=*" %%i in ('docker --version') do set DOCKER_VERSION=%%i
    echo [SUCCESS] Docker 版本: !DOCKER_VERSION!
    set DOCKER_AVAILABLE=true
)

echo.

REM 根据部署类型执行相应操作
if "%DEPLOY_TYPE%"=="local" goto deploy_local
if "%DEPLOY_TYPE%"=="docker" goto deploy_docker
if "%DEPLOY_TYPE%"=="compose" goto deploy_compose
if "%DEPLOY_TYPE%"=="production" goto deploy_production
if "%DEPLOY_TYPE%"=="check" goto check_only
if "%DEPLOY_TYPE%"=="help" goto show_help

echo [ERROR] 未知的部署类型: %DEPLOY_TYPE%
goto show_help

:deploy_local
echo [INFO] 开始本地部署...

REM 检查端口是否被占用
netstat -ano | findstr :8000 >nul 2>&1
if not errorlevel 1 (
    echo [WARNING] 端口 8000 已被占用，尝试终止现有进程...
    for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000') do (
        taskkill /PID %%a /F >nul 2>&1
    )
    timeout /t 2 >nul
)

REM 启动服务器
echo [INFO] 启动服务器...
start /B node server.js

REM 等待服务器启动
timeout /t 3 >nul

REM 检查服务器是否启动成功
curl -s http://localhost:8000 >nul 2>&1
if errorlevel 1 (
    echo [ERROR] 本地部署失败，请检查日志
    pause
    exit /b 1
) else (
    echo [SUCCESS] 本地部署成功！访问地址: http://localhost:8000
    echo [INFO] 按任意键打开浏览器...
    pause >nul
    start http://localhost:8000
)
goto end

:deploy_docker
if "%DOCKER_AVAILABLE%"=="false" (
    echo [ERROR] Docker 未安装，无法进行 Docker 部署
    pause
    exit /b 1
)

echo [INFO] 开始 Docker 部署...

REM 构建镜像
echo [INFO] 构建 Docker 镜像...
docker build -t siege-board-game .
if errorlevel 1 (
    echo [ERROR] Docker 镜像构建失败
    pause
    exit /b 1
)

REM 停止现有容器
echo [INFO] 停止现有容器...
docker stop siege-board-game >nul 2>&1
docker rm siege-board-game >nul 2>&1

REM 启动容器
echo [INFO] 启动 Docker 容器...
docker run -d --name siege-board-game -p 8000:8000 siege-board-game
if errorlevel 1 (
    echo [ERROR] Docker 容器启动失败
    pause
    exit /b 1
)

REM 等待容器启动
timeout /t 5 >nul

REM 检查容器状态
docker ps | findstr siege-board-game >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker 部署失败，请检查容器日志
    docker logs siege-board-game
    pause
    exit /b 1
) else (
    echo [SUCCESS] Docker 部署成功！访问地址: http://localhost:8000
    echo [INFO] 按任意键打开浏览器...
    pause >nul
    start http://localhost:8000
)
goto end

:deploy_compose
if "%DOCKER_AVAILABLE%"=="false" (
    echo [ERROR] Docker 未安装，无法进行 Docker Compose 部署
    pause
    exit /b 1
)

echo [INFO] 检查 Docker Compose...
docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker Compose 未安装，无法进行 Docker Compose 部署
    pause
    exit /b 1
)

echo [INFO] 开始 Docker Compose 部署...

REM 停止现有服务
echo [INFO] 停止现有服务...
docker-compose down >nul 2>&1

REM 启动服务
echo [INFO] 启动 Docker Compose 服务...
docker-compose up -d
if errorlevel 1 (
    echo [ERROR] Docker Compose 启动失败
    pause
    exit /b 1
)

REM 等待服务启动
timeout /t 5 >nul

REM 检查服务状态
docker-compose ps | findstr "Up" >nul 2>&1
if errorlevel 1 (
    echo [ERROR] Docker Compose 部署失败，请检查服务日志
    docker-compose logs
    pause
    exit /b 1
) else (
    echo [SUCCESS] Docker Compose 部署成功！访问地址: http://localhost:8000
    echo [INFO] 按任意键打开浏览器...
    pause >nul
    start http://localhost:8000
)
goto end

:deploy_production
echo [INFO] 开始生产环境部署...

REM 设置环境变量
set NODE_ENV=production
if "%PORT%"=="" set PORT=8000
if "%HOST%"=="" set HOST=0.0.0.0

REM 检查 PM2
echo [INFO] 检查 PM2...
pm2 --version >nul 2>&1
if errorlevel 1 (
    echo [INFO] 安装 PM2...
    npm install -g pm2
    if errorlevel 1 (
        echo [ERROR] PM2 安装失败
        pause
        exit /b 1
    )
)

REM 停止现有进程
echo [INFO] 停止现有进程...
pm2 stop siege-game >nul 2>&1
pm2 delete siege-game >nul 2>&1

REM 启动应用
echo [INFO] 使用 PM2 启动应用...
pm2 start server.js --name siege-game
if errorlevel 1 (
    echo [ERROR] PM2 启动失败
    pause
    exit /b 1
)

REM 保存 PM2 配置
pm2 save >nul 2>&1

echo [SUCCESS] 生产环境部署成功！
echo [INFO] 使用 'pm2 status' 查看应用状态
echo [INFO] 使用 'pm2 logs siege-game' 查看应用日志
echo [INFO] 访问地址: http://localhost:%PORT%
goto end

:check_only
echo [INFO] 系统依赖检查完成
goto end

:show_help
echo 《攻城掠地》桌游 Windows 部署脚本
echo.
echo 用法: %0 [选项]
echo.
echo 选项:
echo   local       本地部署（默认）
echo   docker      Docker 部署
echo   compose     Docker Compose 部署
echo   production  生产环境部署（使用 PM2）
echo   check       检查系统依赖
echo   help        显示此帮助信息
echo.
echo 示例:
echo   %0 local      # 本地部署
echo   %0 docker     # Docker 部署
echo   %0 production # 生产环境部署
echo.
goto end

:end
echo.
echo ========================================
echo 部署脚本执行完成
echo ========================================
pause