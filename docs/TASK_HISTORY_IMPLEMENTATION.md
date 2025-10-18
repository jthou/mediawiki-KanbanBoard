# KanbanBoard 任务历史记录功能实现

## 功能概述

为MediaWiki KanbanBoard扩展添加了完整的任务历史记录功能，包括：

- ✅ 任务创建历史记录
- ✅ 任务更新历史记录  
- ✅ 任务删除历史记录
- ✅ 任务状态变更历史记录
- ✅ 历史记录查询API
- ✅ 前端历史记录显示界面

## 实现内容

### 1. 数据库结构

**新增表：**
- `kanban_task_history` - 任务历史记录表
- `kanban_board_history` - 看板历史记录表（可选）

**历史记录字段：**
- `history_id` - 历史记录ID
- `task_id` - 任务ID
- `field_name` - 变更字段名
- `old_value` - 变更前的值
- `new_value` - 变更后的值
- `changed_by` - 变更用户ID
- `changed_at` - 变更时间
- `change_type` - 变更类型（create/update/delete/move）
- `change_reason` - 变更原因
- `ip_address` - IP地址
- `user_agent` - 用户代理

### 2. API接口

**新增API操作：**
- `gethistory` - 获取任务历史记录

**API参数：**
- `task_id` - 任务ID（必需）
- `limit` - 限制记录数（默认50）
- `offset` - 偏移量（默认0）

**API响应示例：**
```json
{
    "history": [
        {
            "history_id": 1,
            "field_name": "title",
            "old_value": "旧标题",
            "new_value": "新标题",
            "changed_at": "2024-01-15 10:30:00",
            "change_type": "update",
            "change_reason": "Task updated",
            "changed_by": "用户名"
        }
    ],
    "result": "success"
}
```

### 3. 前端界面

**新增功能：**
- 任务编辑对话框添加"历史记录"标签页
- 历史记录时间线显示
- 变更类型智能识别
- 相对时间显示（如"2小时前"）
- 深色主题支持

**界面特性：**
- 标签页切换
- 实时加载历史记录
- 滚动查看历史
- 错误状态处理

## 安装步骤

### 1. 执行数据库迁移

```bash
# 在MediaWiki根目录执行
php maintenance/run.php sql --file=extensions/KanbanBoard/sql/003-add-task-history.sql
```

### 2. 清除缓存

```bash
php maintenance/run.php rebuildLocalisationCache.php
```

### 3. 验证安装

访问任意包含看板的页面，点击任务卡片查看是否有"历史记录"标签页。

## 使用方法

### 查看任务历史

1. 点击任意任务卡片
2. 在弹出的对话框中点击"历史记录"标签页
3. 查看任务的完整变更历史

### 历史记录内容

**记录的操作类型：**
- **创建任务** - 记录任务初始创建
- **修改标题** - 记录标题变更
- **修改描述** - 记录描述变更
- **修改优先级** - 记录优先级变更
- **修改颜色** - 记录颜色变更
- **修改截止日期** - 记录截止日期变更
- **移动状态** - 记录任务在不同列间的移动
- **删除任务** - 记录任务删除操作

**显示信息：**
- 变更操作描述
- 变更时间（相对时间）
- 操作用户
- 变更原因（如果有）

## 技术特性

### 1. 性能优化

- **增量记录**：只记录有变化的字段
- **索引优化**：为常用查询字段添加索引
- **分页支持**：支持大量历史记录的分页查询
- **自动清理**：提供清理旧历史记录的存储过程

### 2. 安全特性

- **权限控制**：只有有查看权限的用户才能查看历史记录
- **IP记录**：记录操作用户的IP地址
- **用户代理记录**：记录用户代理信息
- **软删除**：任务删除采用软删除，保留历史记录

### 3. 扩展性

- **字段扩展**：支持记录任意字段的变更
- **变更类型扩展**：支持自定义变更类型
- **原因记录**：支持记录变更原因
- **多语言支持**：界面支持多语言

## 配置选项

### 历史记录保留时间

```sql
-- 手动清理6个月前的历史记录
CALL CleanOldTaskHistory();
```

### 自定义字段映射

在JavaScript中可以自定义字段名称映射：

```javascript
var fieldNames = {
    'title': '标题',
    'description': '描述',
    'priority': '优先级',
    'color': '颜色',
    'status_id': '状态',
    'due_date': '截止日期'
};
```

## 故障排除

### 常见问题

1. **历史记录不显示**
   - 检查数据库表是否正确创建
   - 检查API权限设置
   - 查看浏览器控制台错误

2. **历史记录加载失败**
   - 检查API接口是否正常
   - 检查用户权限
   - 检查网络连接

3. **样式显示异常**
   - 清除浏览器缓存
   - 检查CSS文件是否正确加载
   - 检查MediaWiki资源加载器

### 调试方法

1. **启用调试模式**
   ```php
   $wgShowExceptionDetails = true;
   $wgShowSQLErrors = true;
   ```

2. **查看API响应**
   ```javascript
   // 在浏览器控制台查看API响应
   console.log(data);
   ```

3. **检查数据库**
   ```sql
   SELECT * FROM kanban_task_history WHERE task_id = 1 ORDER BY changed_at DESC;
   ```

## 更新日志

### v1.0.0 (2024-01-15)
- ✅ 实现基础任务历史记录功能
- ✅ 添加数据库表结构
- ✅ 实现API接口
- ✅ 添加前端界面
- ✅ 支持深色主题
- ✅ 添加权限控制

## 未来计划

### 计划功能
- [ ] 历史记录导出功能
- [ ] 历史记录搜索功能
- [ ] 批量操作历史记录
- [ ] 历史记录统计图表
- [ ] 邮件通知变更
- [ ] 历史记录API权限细化

### 性能优化
- [ ] 历史记录缓存机制
- [ ] 异步历史记录加载
- [ ] 历史记录压缩存储
- [ ] 分布式历史记录存储

## 贡献指南

欢迎提交Issue和Pull Request来改进这个功能。

### 开发环境设置

1. 克隆MediaWiki仓库
2. 安装KanbanBoard扩展
3. 执行数据库迁移
4. 启动开发服务器

### 代码规范

- 遵循MediaWiki编码规范
- 添加适当的注释
- 编写单元测试
- 更新文档

## 许可证

GPL-2.0-or-later

