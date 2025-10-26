# 《攻城掠地》WordPress插件部署检查清单

## 📋 部署前检查

### 1. 文件结构验证
- [x] 主插件文件 `siege-board-game.php` 存在
- [x] 插件头部信息完整
- [x] 所有必需的类文件存在于 `includes/` 目录
- [x] 管理后台文件存在于 `admin/` 目录
- [x] 前端资源文件存在于 `css/` 和 `js/` 目录
- [x] 视图文件存在于相应的 `views/` 目录

### 2. WordPress集成验证
- [x] 插件遵循WordPress编码标准
- [x] 正确使用WordPress钩子和过滤器
- [x] 短代码 `[siege_game]` 已注册
- [x] AJAX处理程序已设置
- [x] 管理菜单已添加
- [x] 资源文件正确入队

### 3. 安全性检查
- [x] 所有PHP文件包含ABSPATH保护
- [x] AJAX请求包含nonce验证
- [x] 用户输入数据经过清理和验证
- [x] 输出数据经过转义
- [x] 数据库查询使用预处理语句

### 4. 性能优化验证
- [x] 资源文件压缩和缓存机制
- [x] 数据库查询优化
- [x] 对象缓存实现
- [x] 懒加载功能
- [x] 响应式设计适配

## 🚀 部署步骤

### 步骤1：准备部署包
```bash
# 创建部署目录
mkdir siege-board-game-deploy

# 复制插件文件（排除开发文件）
cp -r siege-board-game siege-board-game-deploy/
cd siege-board-game-deploy

# 删除开发文件
rm -f test-*.php
rm -f *.bat
rm -f .gitignore
```

### 步骤2：上传到WordPress
1. 将 `siege-board-game` 文件夹上传到 `/wp-content/plugins/`
2. 或者创建ZIP文件通过WordPress后台上传

### 步骤3：激活插件
1. 登录WordPress管理后台
2. 进入"插件"页面
3. 找到"Siege Board Game"插件
4. 点击"激活"

### 步骤4：配置设置
1. 进入"Siege Game" > "设置"
2. 配置游戏参数：
   - AI难度级别
   - 游戏保存设置
   - 超时设置
   - 主题选择
3. 保存设置

### 步骤5：测试功能
1. 创建新页面或编辑现有页面
2. 添加短代码 `[siege_game]`
3. 发布页面并测试游戏功能

## 🔧 配置选项

### 短代码参数
```
[siege_game width="800" height="600" theme="classic" ai_enabled="true"]
```

可用参数：
- `width`: 游戏板宽度（默认：800px）
- `height`: 游戏板高度（默认：600px）
- `theme`: 主题（classic/modern/dark）
- `ai_enabled`: 启用AI（true/false）
- `difficulty`: AI难度（easy/medium/hard）

### 管理后台设置
- **游戏设置**：AI配置、保存游戏、超时设置
- **显示设置**：主题、声音、动画效果
- **性能设置**：缓存、自动保存间隔
- **统计设置**：启用统计、排行榜

## 📊 数据库表

插件会自动创建以下数据库表：

### wp_siege_games
```sql
CREATE TABLE wp_siege_games (
    id int(11) NOT NULL AUTO_INCREMENT,
    user_id bigint(20) NOT NULL,
    game_data longtext NOT NULL,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_status (user_id, status),
    KEY idx_created_at (created_at)
);
```

### wp_siege_game_moves
```sql
CREATE TABLE wp_siege_game_moves (
    id int(11) NOT NULL AUTO_INCREMENT,
    game_id int(11) NOT NULL,
    move_number int(11) NOT NULL,
    move_data text NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_game_move (game_id, move_number)
);
```

## 🔍 故障排除

### 常见问题

#### 1. 插件激活失败
- 检查PHP版本（需要7.4+）
- 检查WordPress版本（需要5.0+）
- 检查文件权限

#### 2. 短代码不显示
- 确认插件已激活
- 检查短代码拼写
- 查看浏览器控制台错误

#### 3. 游戏无法加载
- 检查JavaScript错误
- 确认AJAX URL正确
- 验证nonce设置

#### 4. 样式显示异常
- 检查CSS文件加载
- 确认主题兼容性
- 清除缓存

### 调试模式
在 `wp-config.php` 中启用调试：
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

查看错误日志：`/wp-content/debug.log`

## 📈 性能监控

### 关键指标
- 页面加载时间
- 数据库查询数量
- 内存使用量
- 缓存命中率

### 优化建议
1. 启用对象缓存（Redis/Memcached）
2. 使用CDN加速静态资源
3. 启用Gzip压缩
4. 定期清理过期游戏数据

## 🔒 安全建议

### 定期维护
1. 更新WordPress核心
2. 更新插件版本
3. 监控安全日志
4. 备份数据库

### 权限设置
- 限制文件上传权限
- 设置适当的数据库权限
- 使用强密码策略

## 📞 技术支持

### 日志位置
- WordPress错误日志：`/wp-content/debug.log`
- 插件日志：`/wp-content/uploads/siege-game-logs/`

### 性能分析
使用以下工具监控性能：
- Query Monitor插件
- New Relic
- GTmetrix

### 联系支持
如遇到技术问题，请提供：
1. WordPress版本
2. PHP版本
3. 插件版本
4. 错误日志
5. 问题重现步骤

---

## ✅ 部署完成确认

部署完成后，请确认以下功能正常：

- [ ] 插件成功激活
- [ ] 短代码正确显示游戏界面
- [ ] 游戏逻辑运行正常
- [ ] 用户数据正确保存
- [ ] 管理后台功能完整
- [ ] 响应式设计在移动设备上正常
- [ ] 性能表现符合预期
- [ ] 安全功能正常工作

**恭喜！《攻城掠地》WordPress插件部署完成！** 🎉