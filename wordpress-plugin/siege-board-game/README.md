# 攻城掠地桌游 WordPress 插件

一个功能完整的中国象棋变体桌游WordPress插件，支持人机对战、游戏保存、统计分析等功能。

## 功能特性

### 🎮 游戏功能
- **完整的攻城掠地桌游体验**：经典的中国象棋变体玩法
- **人机对战**：内置AI系统，支持多种难度级别
- **游戏保存/加载**：支持保存游戏进度，随时继续游戏
- **撤销功能**：支持悔棋操作
- **游戏提示**：AI提示系统帮助新手玩家
- **多种主题**：经典、现代、暗色、木质等多种棋盘主题

### 🔧 WordPress集成
- **短代码支持**：使用 `[siege_game]` 在任何页面嵌入游戏
- **用户系统集成**：与WordPress用户系统无缝集成
- **数据库存储**：游戏数据安全存储在WordPress数据库中
- **权限管理**：支持WordPress角色和权限系统
- **响应式设计**：完美适配桌面和移动设备

### 📊 管理功能
- **统计面板**：详细的游戏统计和分析
- **用户管理**：查看和管理玩家游戏记录
- **设置配置**：灵活的插件设置选项
- **性能优化**：缓存和优化机制

## 安装说明

### 系统要求
- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.6 或更高版本

### 安装步骤

#### 方法一：通过WordPress管理后台安装
1. 将整个 `siege-board-game` 文件夹上传到 `/wp-content/plugins/` 目录
2. 在WordPress管理后台进入"插件"页面
3. 找到"攻城掠地桌游"插件并点击"启用"
4. 插件会自动创建必要的数据库表

#### 方法二：手动安装
1. 下载插件文件
2. 解压到 `/wp-content/plugins/siege-board-game/` 目录
3. 确保文件权限正确（建议755）
4. 在WordPress管理后台启用插件

### 配置设置
1. 启用插件后，在管理后台会出现"攻城掠地"菜单
2. 进入"设置"页面配置插件选项：
   - 游戏设置（AI、保存游戏等）
   - 显示设置（主题、音效等）
   - 性能设置（缓存、超时等）

## 使用方法

### 基本用法
在任何页面或文章中使用短代码：
```
[siege_game]
```

### 高级用法
使用参数自定义游戏：
```
[siege_game width="800" height="600" ai="true" save_games="true" theme="modern" difficulty="medium"]
```

### 短代码参数说明
| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `width` | 数字 | 800 | 游戏容器宽度（像素） |
| `height` | 数字 | 600 | 游戏容器高度（像素） |
| `ai` | 布尔值 | true | 是否启用AI对战 |
| `save_games` | 布尔值 | true | 是否允许保存游戏 |
| `theme` | 字符串 | classic | 棋盘主题（classic/modern/dark/wood） |
| `difficulty` | 字符串 | medium | AI难度（easy/medium/hard） |

### 示例用法
```html
<!-- 基本游戏 -->
[siege_game]

<!-- 大尺寸游戏 -->
[siege_game width="1000" height="750"]

<!-- 困难AI对战 -->
[siege_game difficulty="hard" theme="dark"]

<!-- 仅人人对战（禁用AI） -->
[siege_game ai="false" save_games="false"]
```

## 文件结构

```
siege-board-game/
├── siege-board-game.php          # 主插件文件
├── README.md                      # 说明文档
├── uninstall.php                  # 卸载脚本
├── assets/                        # 资源文件
│   ├── images/                    # 图片资源
│   └── sounds/                    # 音效文件
├── css/                          # 样式文件
│   └── siege-game.css            # 主样式文件
├── js/                           # JavaScript文件
│   ├── siege-game.js             # 主游戏逻辑
│   └── siege-game-admin.js       # 管理后台脚本
├── includes/                     # PHP类文件
│   ├── class-siege-game-database.php    # 数据库操作
│   ├── class-siege-game-shortcode.php   # 短代码处理
│   ├── class-siege-game-ajax.php        # AJAX处理
│   └── views/                    # 视图模板
│       └── game-board.php        # 游戏界面模板
└── admin/                        # 管理后台
    ├── class-siege-game-admin.php       # 管理后台主类
    ├── css/                      # 管理后台样式
    │   └── admin-style.css       # 管理后台样式
    ├── js/                       # 管理后台脚本
    │   └── admin-script.js       # 管理后台脚本
    └── views/                    # 管理后台视图
        ├── admin-main.php        # 主面板
        ├── admin-settings.php    # 设置页面
        ├── admin-records.php     # 游戏记录
        └── admin-stats.php       # 统计页面
```

## 数据库表结构

插件会创建以下数据库表：

### siege_games 表
存储游戏基本信息：
- `id`: 游戏ID（主键）
- `user_id`: 玩家用户ID
- `game_state`: 游戏状态JSON
- `status`: 游戏状态（playing/completed/saved）
- `winner`: 获胜方
- `moves_count`: 移动次数
- `created_at`: 创建时间
- `updated_at`: 更新时间

### siege_game_moves 表
存储游戏移动记录：
- `id`: 记录ID（主键）
- `game_id`: 关联游戏ID
- `move_number`: 移动序号
- `player`: 玩家（red/black）
- `from_pos`: 起始位置
- `to_pos`: 目标位置
- `piece_type`: 棋子类型
- `captured_piece`: 被吃棋子
- `move_time`: 移动时间

## 管理功能

### 主面板
- 游戏统计概览
- 快速操作按钮
- 最近游戏记录
- 系统信息显示

### 设置页面
- 游戏基本设置
- 显示和主题设置
- 性能优化选项
- 短代码使用说明

### 游戏记录
- 查看所有游戏记录
- 按用户、状态、时间筛选
- 导出游戏数据
- 批量操作功能

### 统计分析
- 游戏数据统计图表
- 用户活跃度分析
- 游戏时长分布
- 胜负比例统计

## 性能优化

### 缓存机制
- 游戏状态缓存
- 静态资源缓存
- 数据库查询优化

### 资源优化
- CSS/JS文件压缩
- 图片优化
- 按需加载资源

### 数据库优化
- 索引优化
- 定期清理过期数据
- 查询语句优化

## 安全特性

### 数据验证
- 输入数据验证和清理
- SQL注入防护
- XSS攻击防护

### 权限控制
- WordPress nonce验证
- 用户权限检查
- AJAX请求验证

### 数据保护
- 敏感数据加密
- 安全的数据传输
- 定期安全检查

## 故障排除

### 常见问题

**Q: 插件启用后游戏无法显示**
A: 检查以下项目：
1. 确认JavaScript已启用
2. 检查浏览器控制台是否有错误
3. 确认主题兼容性
4. 检查插件文件权限

**Q: 游戏保存功能不工作**
A: 可能的原因：
1. 用户未登录
2. 数据库权限问题
3. 插件设置中禁用了保存功能

**Q: AI对战无响应**
A: 检查：
1. 服务器PHP内存限制
2. 脚本执行时间限制
3. 插件设置中AI是否启用

### 调试模式
在 `wp-config.php` 中启用调试：
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### 日志文件
插件日志位置：`/wp-content/debug.log`

## 更新说明

### 版本 1.0.0
- 初始版本发布
- 完整的游戏功能
- WordPress集成
- 管理后台

## 技术支持

如遇到问题或需要技术支持，请：
1. 查看本文档的故障排除部分
2. 检查WordPress和插件版本兼容性
3. 提供详细的错误信息和环境信息

## 许可证

本插件基于 GPL v2 或更高版本许可证发布。

## 贡献

欢迎提交问题报告和功能建议。

---

**注意**：使用本插件前请备份您的网站数据。