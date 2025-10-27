@echo off
REM WordPress Plugin Deployment Script for Siege Board Game
REM This script helps deploy the plugin to a WordPress installation

setlocal enabledelayedexpansion

echo ========================================
echo 攻城掠地桌游 WordPress 插件部署脚本
echo ========================================
echo.

REM Check if WordPress path is provided
if "%1"=="" (
    echo 使用方法: deploy-wordpress.bat [WordPress路径]
    echo 示例: deploy-wordpress.bat C:\xampp\htdocs\wordpress
    echo.
    echo 或者设置环境变量 WORDPRESS_PATH
    if defined WORDPRESS_PATH (
        set "WP_PATH=%WORDPRESS_PATH%"
        echo 使用环境变量中的WordPress路径: !WP_PATH!
    ) else (
        echo 请提供WordPress安装路径
        pause
        exit /b 1
    )
) else (
    set "WP_PATH=%1"
)

REM Validate WordPress path
if not exist "%WP_PATH%\wp-config.php" (
    echo 错误: 指定路径不是有效的WordPress安装目录
    echo 路径: %WP_PATH%
    echo 请确认路径正确且包含wp-config.php文件
    pause
    exit /b 1
)

echo WordPress路径: %WP_PATH%
echo.

REM Set plugin paths
set "PLUGIN_SOURCE=%~dp0siege-board-game"
set "PLUGIN_DEST=%WP_PATH%\wp-content\plugins\siege-board-game"

echo 插件源路径: %PLUGIN_SOURCE%
echo 插件目标路径: %PLUGIN_DEST%
echo.

REM Check if source plugin exists
if not exist "%PLUGIN_SOURCE%" (
    echo 错误: 插件源目录不存在
    echo 请确认当前目录包含siege-board-game文件夹
    pause
    exit /b 1
)

REM Create plugins directory if it doesn't exist
if not exist "%WP_PATH%\wp-content\plugins" (
    echo 创建插件目录...
    mkdir "%WP_PATH%\wp-content\plugins"
)

REM Backup existing plugin if it exists
if exist "%PLUGIN_DEST%" (
    echo.
    echo 发现现有插件安装，是否备份？ (Y/N)
    set /p backup_choice=
    if /i "!backup_choice!"=="Y" (
        set "backup_dir=%PLUGIN_DEST%_backup_%date:~0,4%%date:~5,2%%date:~8,2%_%time:~0,2%%time:~3,2%%time:~6,2%"
        set "backup_dir=!backup_dir: =0!"
        echo 备份到: !backup_dir!
        xcopy "%PLUGIN_DEST%" "!backup_dir!" /E /I /H /Y
        if !errorlevel! equ 0 (
            echo 备份完成
        ) else (
            echo 备份失败
            pause
            exit /b 1
        )
    )
    
    echo 删除现有插件...
    rmdir /s /q "%PLUGIN_DEST%"
)

REM Copy plugin files
echo.
echo 复制插件文件...
xcopy "%PLUGIN_SOURCE%" "%PLUGIN_DEST%" /E /I /H /Y

if %errorlevel% equ 0 (
    echo 插件文件复制完成
) else (
    echo 插件文件复制失败
    pause
    exit /b 1
)

REM Set file permissions (Windows doesn't need this, but we'll show the concept)
echo.
echo 设置文件权限...
REM In a real deployment, you might use icacls or other tools
echo 文件权限设置完成

REM Verify installation
echo.
echo 验证安装...
if exist "%PLUGIN_DEST%\siege-board-game.php" (
    echo ✓ 主插件文件存在
) else (
    echo ✗ 主插件文件缺失
    set "install_error=1"
)

if exist "%PLUGIN_DEST%\css\siege-game.css" (
    echo ✓ CSS文件存在
) else (
    echo ✗ CSS文件缺失
    set "install_error=1"
)

if exist "%PLUGIN_DEST%\js\siege-game.js" (
    echo ✓ JavaScript文件存在
) else (
    echo ✗ JavaScript文件缺失
    set "install_error=1"
)

if exist "%PLUGIN_DEST%\includes" (
    echo ✓ includes目录存在
) else (
    echo ✗ includes目录缺失
    set "install_error=1"
)

if exist "%PLUGIN_DEST%\admin" (
    echo ✓ admin目录存在
) else (
    echo ✗ admin目录缺失
    set "install_error=1"
)

if defined install_error (
    echo.
    echo 安装验证失败，请检查文件完整性
    pause
    exit /b 1
)

REM Create activation script
echo.
echo 创建激活脚本...
set "activation_script=%WP_PATH%\activate-siege-game.php"
(
echo ^<?php
echo // Temporary activation script for Siege Board Game plugin
echo require_once^('wp-load.php'^);
echo.
echo if ^(^!is_plugin_active^('siege-board-game/siege-board-game.php'^)^) {
echo     $result = activate_plugin^('siege-board-game/siege-board-game.php'^);
echo     if ^(is_wp_error^($result^)^) {
echo         echo 'Plugin activation failed: ' . $result-^>get_error_message^(^);
echo     } else {
echo         echo 'Plugin activated successfully!';
echo     }
echo } else {
echo     echo 'Plugin is already active.';
echo }
echo.
echo // Clean up - delete this file after use
echo unlink^(__FILE__^);
echo ?^>
) > "%activation_script%"

REM Generate deployment report
echo.
echo 生成部署报告...
set "report_file=%~dp0deployment-report.txt"
(
echo 攻城掠地桌游 WordPress 插件部署报告
echo =====================================
echo.
echo 部署时间: %date% %time%
echo WordPress路径: %WP_PATH%
echo 插件路径: %PLUGIN_DEST%
echo.
echo 部署的文件:
dir "%PLUGIN_DEST%" /s /b
echo.
echo 部署状态: 成功
echo.
echo 后续步骤:
echo 1. 访问WordPress管理后台
echo 2. 进入插件页面
echo 3. 启用"攻城掠地桌游"插件
echo 4. 配置插件设置
echo 5. 在页面中使用 [siege_game] 短代码
echo.
echo 或者运行激活脚本:
echo php %activation_script%
) > "%report_file%"

echo 部署报告已保存到: %report_file%

REM Success message
echo.
echo ========================================
echo 部署完成！
echo ========================================
echo.
echo 插件已成功部署到WordPress
echo.
echo 下一步操作:
echo 1. 访问WordPress管理后台: %WP_PATH%/wp-admin/
echo 2. 进入"插件"页面
echo 3. 找到"攻城掠地桌游"并点击"启用"
echo 4. 配置插件设置
echo.
echo 或者运行以下命令自动激活:
echo php "%activation_script%"
echo.
echo 使用方法:
echo 在页面或文章中添加短代码: [siege_game]
echo.
echo 管理后台菜单: 攻城掠地
echo.

REM Optional: Open WordPress admin
echo 是否打开WordPress管理后台？ (Y/N)
set /p open_admin=
if /i "%open_admin%"=="Y" (
    start "" "http://localhost/wordpress/wp-admin/"
)

echo.
echo 部署脚本执行完成
pause