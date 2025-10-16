# KanbanBoard Extension

MediaWiki Kanban Board Extension - 看板管理扩展

## 功能特性

- 创建和管理看板
- 拖拽式任务管理
- 多列状态管理
- 权限控制
- 嵌入到 wiki 页面

## 安装

1. 将扩展文件放入 `extensions/KanbanBoard/` 目录
2. 在 `LocalSettings.php` 中添加：
   ```php
   wfLoadExtension( 'KanbanBoard' );
   ```
3. 运行数据库更新脚本

## 使用方法

### 特殊页面
访问 `特殊:KanbanBoard` 来管理看板

### 嵌入看板
在 wiki 页面中使用：
```wikitext
<kanban name="看板名称" />
```

## 开发

### 数据库结构
- `kanban_boards` - 看板表
- `kanban_statuses` - 状态列表
- `kanban_tasks` - 任务表
- `kanban_permissions` - 权限表

### API
扩展提供 REST API 接口用于前端交互

## 许可证

GPL-2.0-or-later
