# 攻城掠地桌游 WordPress 插件安装指南

## 📋 目录
- [系统要求](#系统要求)
- [安装前准备](#安装前准备)
- [安装方法](#安装方法)
- [配置设置](#配置设置)
- [使用说明](#使用说明)
- [故障排除](#故障排除)
- [升级指南](#升级指南)

## 🔧 系统要求

### 最低要求
- **WordPress**: 5.0 或更高版本
- **PHP**: 7.4 或更高版本
- **MySQL**: 5.6 或更高版本 / MariaDB 10.1 或更高版本
- **内存**: 128MB 或更多
- **磁盘空间**: 10MB 可用空间

### 推荐配置
- **WordPress**: 6.0 或更高版本
- **PHP**: 8.0 或更高版本
- **MySQL**: 8.0 或更高版本
- **内存**: 256MB 或更多
- **磁盘空间**: 50MB 可用空间

### 浏览器支持
- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+
- 移动端浏览器（iOS Safari, Chrome Mobile）

## 📦 安装前准备

### 1. 备份网站
在安装任何插件之前，强烈建议备份您的网站：
```bash
# 备份数据库
mysqldump -u username -p database_name > backup.sql

# 备份文件
tar -czf website_backup.tar.gz /path/to/wordpress/
```

### 2. 检查服务器环境
确保您的服务器满足系统要求：
```php
<?php
// 创建一个临时PHP文件检查环境
echo "PHP版本: " . PHP_VERSION . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";
echo "最大执行时间: " . ini_get('max_execution_time') . "\n";
echo "文件上传限制: " . ini_get('upload_max_filesize') . "\n";
?>
```

### 3. 确保文件权限
WordPress目录需要正确的文件权限：
```bash
# 设置目录权限
find /path/to/wordpress/ -type d -exec chmod 755 {} \;

# 设置文件权限
find /path/to/wordpress/ -type f -exec chmod 644 {} \;

# wp-config.php 特殊权限
chmod 600 wp-config.php
```

## 🚀 安装方法

### 方法一：通过WordPress管理后台安装（推荐）

1. **上传插件文件**
   - 登录WordPress管理后台
   - 进入 `插件` → `安装插件`
   - 点击 `上传插件`
   - 选择 `siege-board-game.zip` 文件
   - 点击 `现在安装`

2. **启用插件**
   - 安装完成后点击 `启用插件`
   - 或在 `插件` → `已安装的插件` 中找到并启用

### 方法二：FTP手动安装

1. **上传文件**
   ```bash
   # 解压插件文件
   unzip siege-board-game.zip
   
   # 上传到WordPress插件目录
   # 使用FTP客户端或命令行
   scp -r siege-board-game/ user@server:/path/to/wordpress/wp-content/plugins/
   ```

2. **设置权限**
   ```bash
   # 设置插件目录权限
   chmod -R 755 /path/to/wordpress/wp-content/plugins/siege-board-game/
   ```

3. **启用插件**
   - 在WordPress管理后台的 `插件` 页面启用插件

### 方法三：WP-CLI安装

```bash
# 进入WordPress根目录
cd /path/to/wordpress/

# 安装插件
wp plugin install siege-board-game.zip

# 启用插件
wp plugin activate siege-board-game
```

## ⚙️ 配置设置

### 1. 基本设置

安装并启用插件后：

1. **访问插件设置**
   - 在WordPress管理后台，点击左侧菜单中的 `攻城掠地`
   - 进入 `设置` 子菜单

2. **配置游戏选项**
   ```
   游戏设置:
   ✓ 启用AI对战
   ✓ 启用游戏保存
   ✓ 最大保存游戏数: 10
   ✓ 游戏超时时间: 3600秒
   ✓ 默认难度: 中等
   
   显示设置:
   ✓ 棋盘主题: 经典
   ✓ 启用音效
   ✓ 启用动画
   
   性能设置:
   ✓ 自动保存间隔: 300秒
   ✓ 最大游戏时长: 7200秒
   ```

### 2. 数据库配置

插件会自动创建必要的数据库表：
- `wp_siege_games` - 存储游戏数据
- `wp_siege_game_moves` - 存储游戏移动记录

如果自动创建失败，可以手动执行SQL：
```sql
-- 创建游戏表
CREATE TABLE `wp_siege_games` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `game_state` longtext NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'playing',
  `winner` varchar(10) DEFAULT NULL,
  `moves_count` int(11) DEFAULT 0,
  `ai_difficulty` varchar(10) DEFAULT 'medium',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
);

-- 创建移动记录表
CREATE TABLE `wp_siege_game_moves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `game_id` bigint(20) unsigned NOT NULL,
  `move_number` int(11) NOT NULL,
  `player` varchar(10) NOT NULL,
  `from_pos` varchar(10) NOT NULL,
  `to_pos` varchar(10) NOT NULL,
  `piece_type` varchar(20) NOT NULL,
  `captured_piece` varchar(20) DEFAULT NULL,
  `move_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `move_number` (`move_number`)
);
```

### 3. 权限设置

配置用户权限：
```php
// 在主题的functions.php中添加自定义权限
function siege_game_add_capabilities() {
    $role = get_role('subscriber');
    $role->add_cap('play_siege_game');
    
    $role = get_role('author');
    $role->add_cap('play_siege_game');
    $role->add_cap('view_siege_stats');
}
add_action('init', 'siege_game_add_capabilities');
```

## 📖 使用说明

### 1. 在页面中嵌入游戏

#### 基本用法
```html
[siege_game]
```

#### 高级用法
```html
[siege_game width="800" height="600" ai="true" save_games="true" theme="modern" difficulty="hard"]
```

#### 参数说明
| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `width` | 字符串 | "100%" | 游戏容器宽度 |
| `height` | 字符串 | "600px" | 游戏容器高度 |
| `ai` | 布尔值 | true | 是否启用AI对战 |
| `save_games` | 布尔值 | true | 是否允许保存游戏 |
| `theme` | 字符串 | "classic" | 主题样式 |
| `difficulty` | 字符串 | "medium" | AI难度 |

### 2. 主题集成

#### 在主题模板中使用
```php
<?php
// 在主题文件中直接调用
echo do_shortcode('[siege_game theme="dark" width="100%"]');
?>
```

#### 自定义样式
```css
/* 在主题的style.css中添加自定义样式 */
.siege-game-container {
    border: 2px solid #your-color;
    border-radius: 10px;
}

.siege-game-container .btn-primary {
    background-color: #your-brand-color;
}
```

### 3. 用户体验优化

#### 响应式设计
插件已内置响应式设计，在不同设备上都能良好显示：
- 桌面端：完整功能界面
- 平板端：优化的触控界面
- 手机端：简化的移动界面

#### 性能优化
- 资源文件仅在需要时加载
- 游戏状态自动保存
- 智能缓存机制

## 🔧 故障排除

### 常见问题及解决方案

#### 1. 插件无法启用
**问题**: 点击启用后出现错误
**解决方案**:
```bash
# 检查PHP错误日志
tail -f /var/log/php_errors.log

# 检查WordPress调试日志
tail -f /path/to/wordpress/wp-content/debug.log

# 检查文件权限
ls -la wp-content/plugins/siege-board-game/
```

#### 2. 游戏无法显示
**问题**: 短代码显示但游戏不加载
**解决方案**:
1. 检查浏览器控制台错误
2. 确认JavaScript已启用
3. 检查主题兼容性
```javascript
// 在浏览器控制台执行
console.log('jQuery版本:', jQuery.fn.jquery);
console.log('游戏对象:', typeof siegeGame);
```

#### 3. 数据库连接错误
**问题**: 游戏保存功能不工作
**解决方案**:
```php
// 在wp-config.php中启用调试
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// 检查数据库表是否存在
// 在WordPress管理后台执行
global $wpdb;
$tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}siege_%'");
var_dump($tables);
```

#### 4. 性能问题
**问题**: 游戏运行缓慢
**解决方案**:
1. 增加PHP内存限制
```php
// 在wp-config.php中添加
ini_set('memory_limit', '256M');
```

2. 启用缓存插件
3. 优化数据库
```sql
-- 优化游戏表
OPTIMIZE TABLE wp_siege_games;
OPTIMIZE TABLE wp_siege_game_moves;
```

### 调试模式

启用调试模式获取详细错误信息：
```php
// 在wp-config.php中添加
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### 日志文件位置
- WordPress错误日志: `/wp-content/debug.log`
- PHP错误日志: `/var/log/php_errors.log`
- 服务器错误日志: `/var/log/apache2/error.log` 或 `/var/log/nginx/error.log`

## 🔄 升级指南

### 自动升级
1. 在WordPress管理后台收到升级通知时
2. 点击 `立即更新`
3. 等待升级完成

### 手动升级
1. **备份当前版本**
   ```bash
   cp -r wp-content/plugins/siege-board-game/ siege-board-game-backup/
   ```

2. **下载新版本**
   - 下载最新版本的插件文件

3. **替换文件**
   ```bash
   # 停用插件（通过管理后台）
   # 删除旧文件
   rm -rf wp-content/plugins/siege-board-game/
   # 上传新文件
   unzip siege-board-game-new.zip -d wp-content/plugins/
   ```

4. **重新启用插件**
   - 在管理后台重新启用插件

### 数据迁移
升级时数据会自动保留，如需手动迁移：
```sql
-- 导出游戏数据
mysqldump -u username -p database_name wp_siege_games wp_siege_game_moves > siege_game_backup.sql

-- 导入到新环境
mysql -u username -p new_database_name < siege_game_backup.sql
```

## 📞 技术支持

### 获取帮助
1. 查看本文档的故障排除部分
2. 检查WordPress和插件版本兼容性
3. 在支持论坛发布问题时请提供：
   - WordPress版本
   - PHP版本
   - 插件版本
   - 错误信息
   - 浏览器信息

### 报告问题
提交问题时请包含以下信息：
```
环境信息:
- WordPress版本: 
- PHP版本: 
- 插件版本: 
- 主题: 
- 其他插件: 

问题描述:
- 具体问题: 
- 重现步骤: 
- 预期结果: 
- 实际结果: 

错误信息:
- 浏览器控制台错误: 
- PHP错误日志: 
- WordPress调试日志: 
```

---

**注意**: 安装前请务必备份您的网站数据。如遇到问题，请参考故障排除部分或联系技术支持。