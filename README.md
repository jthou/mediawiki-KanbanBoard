# KanbanBoard Extension for MediaWiki

[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE)
[![MediaWiki](https://img.shields.io/badge/MediaWiki-1.42+-blue.svg)](https://www.mediawiki.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net/)

一个功能完整的看板管理系统扩展，为MediaWiki提供现代化的任务管理和项目管理功能。

## 📋 目录

- [概述](#概述)
- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装指南](#安装指南)
- [配置](#配置)
- [使用方法](#使用方法)
- [API接口](#api接口)
- [测试](#测试)
- [开发](#开发)
- [故障排除](#故障排除)
- [贡献](#贡献)
- [许可证](#许可证)

## 🎯 概述

KanbanBoard扩展为MediaWiki添加了完整的看板管理系统，允许用户：

- 创建和管理多个看板
- 使用拖拽界面管理任务
- 设置任务优先级、截止日期和标签
- 跟踪任务变更历史
- 控制用户权限和访问级别
- 与MediaWiki搜索系统集成

### 与MediaWiki的关系

KanbanBoard是一个**MediaWiki扩展**，它：

- 完全集成到MediaWiki的权限系统
- 使用MediaWiki的数据库和API框架
- 遵循MediaWiki的编码规范和架构
- 支持MediaWiki的多语言系统
- 与MediaWiki的搜索功能无缝集成
- 使用MediaWiki的命名空间系统

## ✨ 功能特性

### 核心功能
- 🎯 **看板管理** - 创建、编辑、删除看板
- 📋 **列管理** - 添加、删除、重排序列
- 📝 **任务管理** - 创建、编辑、删除、移动任务
- 🎨 **拖拽界面** - 直观的拖拽排序功能
- 🔐 **权限控制** - 完整的用户权限管理系统

### 高级功能
- 📊 **任务历史** - 完整的变更历史追踪
- 🔍 **搜索集成** - 与MediaWiki搜索系统集成
- 🏷️ **标签系统** - 任务标签和分类
- 💬 **评论功能** - 任务评论和讨论
- 📎 **附件支持** - 文件上传和附件管理
- 🌙 **深色主题** - 完整的深色模式支持

### 技术特性
- 🚀 **REST API** - 完整的API接口
- 📱 **响应式设计** - 支持各种屏幕尺寸
- ⚡ **性能优化** - DOM缓存和防抖机制
- 🔧 **命名空间集成** - 专用看板命名空间
- 🎨 **自定义样式** - 可定制的界面样式

## 🔧 系统要求

### MediaWiki版本
- **MediaWiki**: 1.42 或更高版本
- **PHP**: 8.1 或更高版本
- **数据库**: MySQL 5.7+ 或 MariaDB 10.3+

### 推荐配置
- **内存**: 至少 256MB PHP内存限制
- **存储**: 至少 100MB 可用空间
- **浏览器**: 现代浏览器（Chrome 60+, Firefox 60+, Safari 12+, Edge 79+）

### 依赖扩展
- 无强制依赖，但推荐安装：
  - `Extension:ParserFunctions` - 增强解析功能
  - `Extension:VisualEditor` - 可视化编辑器支持

## 📦 安装指南

### 1. 下载扩展

```bash
# 方法1: 使用Git克隆
cd /path/to/mediawiki/extensions/
git clone https://github.com/yourusername/KanbanBoard.git

# 方法2: 下载ZIP文件
# 下载最新版本的ZIP文件并解压到 extensions/KanbanBoard/
```

### 2. 配置MediaWiki

在 `LocalSettings.php` 文件中添加：

```php
// 加载KanbanBoard扩展
wfLoadExtension( 'KanbanBoard' );

// 可选配置
$wgKanbanBoardMaxColumns = 10;  // 最大列数
$wgKanbanBoardMaxCardsPerColumn = 100;  // 每列最大卡片数
$wgKanbanBoardAllowAnonymousEdit = false;  // 不允许匿名用户编辑
```

### 3. 数据库更新

运行MediaWiki数据库更新脚本：

```bash
# 在MediaWiki根目录执行
php maintenance/update.php
```

### 4. 权限配置

确保用户有适当的权限：

```php
// 在LocalSettings.php中添加权限配置
$wgGroupPermissions['user']['kanbanboard-view'] = true;
$wgGroupPermissions['user']['kanbanboard-edit'] = true;
$wgGroupPermissions['sysop']['kanbanboard-admin'] = true;
```

### 5. 清除缓存

```bash
php maintenance/rebuildLocalisationCache.php
php maintenance/runJobs.php
```

## ⚙️ 配置

### 基本配置

```php
// LocalSettings.php 配置示例

// 看板设置
$wgKanbanBoardMaxColumns = 10;  // 最大列数
$wgKanbanBoardMaxCardsPerColumn = 100;  // 每列最大卡片数
$wgKanbanBoardAllowAnonymousEdit = false;  // 匿名用户编辑权限

// 权限设置
$wgGroupPermissions['*']['kanbanboard-view'] = true;  // 所有人可查看
$wgGroupPermissions['user']['kanbanboard-edit'] = true;  // 注册用户可编辑
$wgGroupPermissions['sysop']['kanbanboard-admin'] = true;  // 管理员权限

// API设置
$wgEnableAPI = true;
$wgEnableWriteAPI = true;
```

### 高级配置

```php
// 自定义命名空间
$wgExtraNamespaces[3000] = 'Kanban';
$wgExtraNamespaces[3001] = 'Kanban_Talk';

// 搜索设置
$wgNamespacesToBeSearchedDefault[3000] = true;  // 看板命名空间可搜索

// 缓存设置
$wgMainCacheType = CACHE_MEMCACHED;  // 推荐使用Memcached
```

## 🚀 使用方法

### 特殊页面

访问以下特殊页面来管理看板：

- **`特殊:KanbanBoard`** - 看板管理页面
- **`特殊:KanbanSearch`** - 看板搜索页面

### 嵌入看板

在wiki页面中使用看板：

```wikitext
<kanban name="项目看板" />
<kanban name="任务看板" />
```

### 命名空间使用

使用专用命名空间创建看板页面：

```wikitext
Kanban:项目看板
Kanban:任务管理
Kanban_Talk:项目看板  # 讨论页面
```

### 搜索功能

在MediaWiki搜索框中搜索看板内容：

- 搜索看板名称
- 搜索任务标题和描述
- 搜索任务标签

## 🔌 API接口

KanbanBoard提供完整的REST API接口：

### 基础URL
```
/api.php?action=kanban&format=json
```

### 支持的操作

| 操作 | 描述 | 权限要求 |
|------|------|----------|
| `getboard` | 获取看板数据 | view |
| `addcolumn` | 添加列 | edit |
| `deletecolumn` | 删除列 | edit |
| `updatecolumn` | 更新列 | edit |
| `reordercolumns` | 重排序列 | edit |
| `createtask` | 创建任务 | edit |
| `updatetask` | 更新任务 | edit |
| `deletetask` | 删除任务 | edit |
| `gethistory` | 获取任务历史 | view |

### API示例

```javascript
// 获取看板数据
fetch('/api.php?action=kanban&kanban_action=getboard&board_id=1&format=json')
  .then(response => response.json())
  .then(data => console.log(data));

// 创建任务
const params = new URLSearchParams({
  action: 'kanban',
  kanban_action: 'createtask',
  board_id: 1,
  column_id: 2,
  title: '新任务',
  description: '任务描述',
  priority: 'medium',
  format: 'json'
});

fetch('/api.php', {
  method: 'POST',
  body: params
}).then(response => response.json());
```

## 🧪 测试

### 功能测试

1. **创建看板测试**
   ```bash
   # 访问特殊页面
   curl "http://your-wiki.com/wiki/特殊:KanbanBoard"
   ```

2. **API测试**
   ```bash
   # 测试API接口
   curl "http://your-wiki.com/api.php?action=kanban&kanban_action=getboard&board_id=1&format=json"
   ```

3. **权限测试**
   - 测试不同用户角色的权限
   - 验证匿名用户访问限制
   - 检查管理员权限

### 性能测试

1. **加载测试**
   ```bash
   # 使用ab工具测试性能
   ab -n 100 -c 10 "http://your-wiki.com/wiki/特殊:KanbanBoard"
   ```

2. **数据库测试**
   ```sql
   -- 检查数据库表
   SHOW TABLES LIKE 'kanban_%';
   
   -- 检查索引
   SHOW INDEX FROM kanban_tasks;
   ```

### 浏览器测试

测试支持的浏览器：
- Chrome 60+
- Firefox 60+
- Safari 12+
- Edge 79+

### 自动化测试

```bash
# 运行PHPUnit测试（如果配置了）
cd /path/to/mediawiki/extensions/KanbanBoard
composer install
vendor/bin/phpunit tests/
```

## 🛠️ 开发

### 开发环境设置

1. **克隆仓库**
   ```bash
   git clone https://github.com/yourusername/KanbanBoard.git
   cd KanbanBoard
   ```

2. **安装依赖**
   ```bash
   composer install
   ```

3. **配置开发环境**
   ```bash
   # 复制配置文件
   cp LocalSettings.php.example LocalSettings.php
   
   # 配置数据库连接
   # 编辑 LocalSettings.php
   ```

### 代码结构

```
KanbanBoard/
├── includes/           # PHP类文件
│   ├── ApiKanban.php   # API接口
│   ├── Hooks.php       # 钩子处理
│   └── ...
├── resources/          # 前端资源
│   ├── js/            # JavaScript文件
│   └── css/           # 样式文件
├── sql/               # 数据库脚本
├── i18n/              # 国际化文件
├── tests/             # 测试文件
└── docs/              # 文档
```

### 调试

启用调试模式：

```php
// LocalSettings.php
$wgShowExceptionDetails = true;
$wgShowSQLErrors = true;
$wgDebugLogFile = '/path/to/debug.log';
```

### 贡献代码

1. Fork仓库
2. 创建功能分支
3. 提交更改
4. 创建Pull Request

## 🔧 故障排除

### 常见问题

1. **扩展无法加载**
   ```bash
   # 检查文件权限
   ls -la extensions/KanbanBoard/
   
   # 检查PHP语法
   php -l extensions/KanbanBoard/includes/ApiKanban.php
   ```

2. **数据库错误**
   ```bash
   # 检查数据库连接
   php maintenance/checkDatabase.php
   
   # 运行数据库更新
   php maintenance/update.php
   ```

3. **权限问题**
   ```php
   // 检查权限配置
   $wgGroupPermissions['user']['kanbanboard-edit'] = true;
   ```

4. **JavaScript错误**
   - 检查浏览器控制台
   - 验证资源文件加载
   - 检查API接口响应

### 日志文件

检查以下日志文件：
- MediaWiki错误日志
- PHP错误日志
- 浏览器控制台
- 网络请求日志

### 性能问题

1. **慢查询**
   ```sql
   -- 检查慢查询
   SHOW PROCESSLIST;
   
   -- 优化索引
   EXPLAIN SELECT * FROM kanban_tasks WHERE board_id = 1;
   ```

2. **内存使用**
   ```php
   // 增加内存限制
   ini_set('memory_limit', '512M');
   ```

## 🤝 贡献

我们欢迎各种形式的贡献：

- 🐛 报告Bug
- 💡 提出功能建议
- 📝 改进文档
- 🔧 提交代码
- 🌍 翻译支持

### 贡献指南

1. 阅读[贡献指南](CONTRIBUTING.md)
2. 遵循[代码规范](docs/CODING_STANDARDS.md)
3. 编写测试用例
4. 更新文档

## 📄 许可证

本项目采用 [GPL-2.0-or-later](LICENSE) 许可证。

## 📞 支持

- 📧 邮箱: support@example.com
- 🐛 Bug报告: [GitHub Issues](https://github.com/yourusername/KanbanBoard/issues)
- 💬 讨论: [GitHub Discussions](https://github.com/yourusername/KanbanBoard/discussions)
- 📖 文档: [Wiki文档](https://github.com/yourusername/KanbanBoard/wiki)

## 🙏 致谢

感谢所有为这个项目做出贡献的开发者和用户！

---

**版本**: 1.0.0  
**最后更新**: 2024年1月15日  
**维护者**: Your Name