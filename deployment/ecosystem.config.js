/**
 * 《攻城掠地》桌游项目 - PM2生产环境配置
 * 
 * PM2是Node.js应用的生产级进程管理器
 * 提供负载均衡、自动重启、监控等功能
 */

module.exports = {
  apps: [
    {
      // 应用基本信息
      name: 'siege-board-game',
      script: './server.js',
      cwd: '/var/www/siege-game',
      
      // 集群配置
      instances: 'max', // 使用所有CPU核心
      exec_mode: 'cluster', // 集群模式
      
      // 环境变量
      env: {
        NODE_ENV: 'production',
        PORT: 8000,
        HOST: '0.0.0.0'
      },
      
      // 开发环境配置
      env_development: {
        NODE_ENV: 'development',
        PORT: 3000,
        HOST: 'localhost'
      },
      
      // 测试环境配置
      env_staging: {
        NODE_ENV: 'staging',
        PORT: 8001,
        HOST: '0.0.0.0'
      },
      
      // 日志配置
      log_type: 'json',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      error_file: '/var/log/siege-game/error.log',
      out_file: '/var/log/siege-game/access.log',
      log_file: '/var/log/siege-game/combined.log',
      time: true,
      
      // 内存管理
      max_memory_restart: '512M', // 内存超过512MB时重启
      node_args: '--max-old-space-size=512', // Node.js内存限制
      
      // 监控和重启配置
      watch: false, // 生产环境不启用文件监控
      ignore_watch: [
        'node_modules',
        'logs',
        '.git',
        'uploads',
        'temp'
      ],
      
      // 重启策略
      max_restarts: 10, // 最大重启次数
      min_uptime: '10s', // 最小运行时间
      restart_delay: 1000, // 重启延迟
      
      // 进程管理
      kill_timeout: 5000, // 强制杀死进程的超时时间
      wait_ready: true, // 等待应用准备就绪
      listen_timeout: 10000, // 监听超时时间
      
      // 自动重启条件
      autorestart: true,
      
      // 合并日志
      merge_logs: true,
      
      // 实例间负载均衡
      instance_var: 'INSTANCE_ID',
      
      // 健康检查
      health_check_grace_period: 3000,
      
      // 进程标题
      name: 'siege-game-worker'
    }
  ],
  
  // 部署配置
  deploy: {
    // 生产环境部署
    production: {
      user: 'gameapp',
      host: ['your-server-ip'], // 替换为实际服务器IP
      ref: 'origin/main',
      repo: 'https://github.com/xiangyaohan/BattleGame.git',
      path: '/var/www/siege-game',
      'post-deploy': 'npm ci --only=production && pm2 reload ecosystem.config.js --env production',
      'pre-setup': 'apt update && apt install git -y',
      'post-setup': 'ls -la',
      env: {
        NODE_ENV: 'production'
      }
    },
    
    // 测试环境部署
    staging: {
      user: 'gameapp',
      host: ['staging-server-ip'], // 替换为测试服务器IP
      ref: 'origin/develop',
      repo: 'https://github.com/xiangyaohan/BattleGame.git',
      path: '/var/www/siege-game-staging',
      'post-deploy': 'npm ci && pm2 reload ecosystem.config.js --env staging',
      env: {
        NODE_ENV: 'staging'
      }
    }
  }
};

/**
 * PM2常用命令：
 * 
 * 启动应用：
 * pm2 start ecosystem.config.js
 * 
 * 重启应用：
 * pm2 restart siege-board-game
 * 
 * 停止应用：
 * pm2 stop siege-board-game
 * 
 * 删除应用：
 * pm2 delete siege-board-game
 * 
 * 查看状态：
 * pm2 list
 * pm2 status
 * 
 * 查看日志：
 * pm2 logs siege-board-game
 * pm2 logs siege-board-game --lines 100
 * 
 * 监控：
 * pm2 monit
 * 
 * 重载配置：
 * pm2 reload ecosystem.config.js
 * 
 * 保存配置：
 * pm2 save
 * 
 * 开机自启：
 * pm2 startup
 * pm2 save
 * 
 * 部署：
 * pm2 deploy production setup
 * pm2 deploy production
 * 
 * 性能分析：
 * pm2 profile:cpu
 * pm2 profile:mem
 */