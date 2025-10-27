@echo off
echo Creating WordPress Plugin Deployment Package...
echo.

REM Set variables
set PLUGIN_NAME=siege-board-game
set DEPLOY_DIR=%PLUGIN_NAME%-deploy
set ZIP_NAME=%PLUGIN_NAME%-v1.0.0.zip

REM Create deployment directory
if exist %DEPLOY_DIR% rmdir /s /q %DEPLOY_DIR%
mkdir %DEPLOY_DIR%

echo Copying plugin files...

REM Copy main plugin files
copy %PLUGIN_NAME%\siege-board-game.php %DEPLOY_DIR%\
copy %PLUGIN_NAME%\uninstall.php %DEPLOY_DIR%\
copy %PLUGIN_NAME%\README.md %DEPLOY_DIR%\
copy %PLUGIN_NAME%\INSTALLATION.md %DEPLOY_DIR%\
copy %PLUGIN_NAME%\DEPLOYMENT-CHECKLIST.md %DEPLOY_DIR%\

REM Copy includes directory
xcopy %PLUGIN_NAME%\includes %DEPLOY_DIR%\includes /E /I

REM Copy admin directory
xcopy %PLUGIN_NAME%\admin %DEPLOY_DIR%\admin /E /I

REM Copy css directory
xcopy %PLUGIN_NAME%\css %DEPLOY_DIR%\css /E /I

REM Copy js directory
xcopy %PLUGIN_NAME%\js %DEPLOY_DIR%\js /E /I

REM Create languages directory
mkdir %DEPLOY_DIR%\languages

REM Create assets directory
mkdir %DEPLOY_DIR%\assets

echo.
echo Files copied successfully!
echo.

REM Create ZIP file (requires PowerShell)
echo Creating ZIP package...
powershell -command "Compress-Archive -Path '%DEPLOY_DIR%\*' -DestinationPath '%ZIP_NAME%' -Force"

if exist %ZIP_NAME% (
    echo.
    echo ✓ Deployment package created: %ZIP_NAME%
    echo ✓ Deployment folder created: %DEPLOY_DIR%
    echo.
    echo Next steps:
    echo 1. Upload %ZIP_NAME% to WordPress admin ^> Plugins ^> Add New ^> Upload Plugin
    echo 2. Or extract %DEPLOY_DIR% to /wp-content/plugins/ directory
    echo 3. Activate the plugin in WordPress admin
    echo 4. Configure settings in Siege Game ^> Settings
    echo 5. Add [siege_game] shortcode to any page or post
    echo.
) else (
    echo ✗ Failed to create ZIP package
    echo You can manually use the %DEPLOY_DIR% folder
)

echo Deployment package creation completed!
pause