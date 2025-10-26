@echo off
echo ========================================
echo 《攻城掠地》桌游项目 - Vercel部署脚本
echo ========================================

echo.
echo 检查Vercel CLI是否已安装...
vercel --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Vercel CLI未安装，正在安装...
    npm install -g vercel
    if %errorlevel% neq 0 (
        echo 错误：无法安装Vercel CLI
        pause
        exit /b 1
    )
) else (
    echo Vercel CLI已安装
)

echo.
echo 检查是否已登录Vercel...
vercel whoami >nul 2>&1
if %errorlevel% neq 0 (
    echo 请先登录Vercel账户...
    vercel login
    if %errorlevel% neq 0 (
        echo 错误：登录失败
        pause
        exit /b 1
    )
)

echo.
echo 开始部署到Vercel...
echo.

REM 部署预览版本
echo 1. 部署预览版本（用于测试）
echo 2. 部署生产版本
echo 3. 退出
echo.
set /p choice=请选择部署类型 (1-3): 

if "%choice%"=="1" (
    echo 正在部署预览版本...
    vercel
) else if "%choice%"=="2" (
    echo 正在部署生产版本...
    vercel --prod
) else if "%choice%"=="3" (
    echo 退出部署
    exit /b 0
) else (
    echo 无效选择，默认部署预览版本...
    vercel
)

if %errorlevel% equ 0 (
    echo.
    echo ========================================
    echo 部署成功！
    echo ========================================
    echo.
    echo 您的项目已成功部署到Vercel
    echo 请查看上方输出的URL来访问您的网站
    echo.
    echo 常用命令：
    echo - vercel          : 部署预览版本
    echo - vercel --prod   : 部署生产版本
    echo - vercel ls       : 查看所有部署
    echo - vercel rm       : 删除部署
    echo.
) else (
    echo.
    echo ========================================
    echo 部署失败！
    echo ========================================
    echo.
    echo 请检查错误信息并重试
    echo 常见问题：
    echo 1. 确保已正确登录Vercel账户
    echo 2. 检查网络连接
    echo 3. 确保项目配置正确
    echo.
)

pause