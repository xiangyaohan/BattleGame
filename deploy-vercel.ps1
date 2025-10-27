# 《攻城掠地》桌游项目 - Vercel部署脚本 (PowerShell版本)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "《攻城掠地》桌游项目 - Vercel部署脚本" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# 检查Vercel CLI是否已安装
Write-Host "检查Vercel CLI是否已安装..." -ForegroundColor Yellow
try {
    $vercelVersion = vercel --version 2>$null
    Write-Host "Vercel CLI已安装: $vercelVersion" -ForegroundColor Green
} catch {
    Write-Host "Vercel CLI未安装，正在安装..." -ForegroundColor Yellow
    try {
        npm install -g vercel
        Write-Host "Vercel CLI安装成功" -ForegroundColor Green
    } catch {
        Write-Host "错误：无法安装Vercel CLI" -ForegroundColor Red
        Read-Host "按任意键退出"
        exit 1
    }
}

Write-Host ""

# 检查是否已登录Vercel
Write-Host "检查是否已登录Vercel..." -ForegroundColor Yellow
try {
    $whoami = vercel whoami 2>$null
    Write-Host "已登录Vercel账户: $whoami" -ForegroundColor Green
} catch {
    Write-Host "请先登录Vercel账户..." -ForegroundColor Yellow
    try {
        vercel login
        Write-Host "登录成功" -ForegroundColor Green
    } catch {
        Write-Host "错误：登录失败" -ForegroundColor Red
        Read-Host "按任意键退出"
        exit 1
    }
}

Write-Host ""
Write-Host "开始部署到Vercel..." -ForegroundColor Cyan
Write-Host ""

# 部署选项
Write-Host "请选择部署类型：" -ForegroundColor Yellow
Write-Host "1. 部署预览版本（用于测试）" -ForegroundColor White
Write-Host "2. 部署生产版本" -ForegroundColor White
Write-Host "3. 退出" -ForegroundColor White
Write-Host ""

$choice = Read-Host "请输入选择 (1-3)"

switch ($choice) {
    "1" {
        Write-Host "正在部署预览版本..." -ForegroundColor Yellow
        try {
            vercel
            $deploySuccess = $true
        } catch {
            $deploySuccess = $false
        }
    }
    "2" {
        Write-Host "正在部署生产版本..." -ForegroundColor Yellow
        try {
            vercel --prod
            $deploySuccess = $true
        } catch {
            $deploySuccess = $false
        }
    }
    "3" {
        Write-Host "退出部署" -ForegroundColor Gray
        exit 0
    }
    default {
        Write-Host "无效选择，默认部署预览版本..." -ForegroundColor Yellow
        try {
            vercel
            $deploySuccess = $true
        } catch {
            $deploySuccess = $false
        }
    }
}

Write-Host ""

if ($deploySuccess) {
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "部署成功！" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "您的项目已成功部署到Vercel" -ForegroundColor Green
    Write-Host "请查看上方输出的URL来访问您的网站" -ForegroundColor White
    Write-Host ""
    Write-Host "常用命令：" -ForegroundColor Yellow
    Write-Host "- vercel          : 部署预览版本" -ForegroundColor White
    Write-Host "- vercel --prod   : 部署生产版本" -ForegroundColor White
    Write-Host "- vercel ls       : 查看所有部署" -ForegroundColor White
    Write-Host "- vercel rm       : 删除部署" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host "========================================" -ForegroundColor Red
    Write-Host "部署失败！" -ForegroundColor Red
    Write-Host "========================================" -ForegroundColor Red
    Write-Host ""
    Write-Host "请检查错误信息并重试" -ForegroundColor Red
    Write-Host "常见问题：" -ForegroundColor Yellow
    Write-Host "1. 确保已正确登录Vercel账户" -ForegroundColor White
    Write-Host "2. 检查网络连接" -ForegroundColor White
    Write-Host "3. 确保项目配置正确" -ForegroundColor White
    Write-Host ""
}

Read-Host "按任意键退出"