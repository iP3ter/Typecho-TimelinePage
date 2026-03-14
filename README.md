# TimelinePage（Typecho 时间轴插件）

TimelinePage 用于在 Typecho 正文中插入时间轴，支持按年份分组、时间排序、图文混排和响应式展示。

## 在 Typecho 中安装

### 方式 A：从 Release 下载（推荐）

1. 打开项目发布页：`https://github.com/iP3ter/Typecho-TimelinePage/releases`
2. 下载最新版本压缩包（通常是 `TimelinePage-v2.0.0-linux.tar.gz` 或源码 zip）。
3. 解压后确认目录名是 `TimelinePage`（不要带版本后缀）。
4. 将整个 `TimelinePage` 目录上传到 Typecho 站点的 `usr/plugins/` 目录下。
5. 登录 Typecho 后台，进入 `控制台 -> 插件`，找到 `TimelinePage` 并点击启用。
6. 启用后可进入插件设置页按需调整参数。

### 方式 B：从仓库源码安装

1. 打开仓库：`https://github.com/iP3ter/Typecho-TimelinePage`
2. 下载源码或克隆仓库。
3. 保证插件目录名为 `TimelinePage`，然后放到 `usr/plugins/`。
4. 到后台 `控制台 -> 插件` 启用。

### 安装后看不到插件时请检查

- 插件目录是否为：`usr/plugins/TimelinePage/`（目录名必须一致）
- `Plugin.php` 是否在该目录根部
- 文件权限是否允许 Typecho 读取

## 使用方法

1. 在文章或独立页面正文中插入 `[timeline]...[/timeline]` 块。
2. 每行一条记录，格式为：`日期|描述` 或 `日期|描述|图片URL列表`。
3. 发布后前台会自动渲染为时间轴。

### 最小示例

```text
[timeline]
2026-03-01|项目启动
2026-03-03|完成线框设计
2026-03-06|发布第一个版本
[/timeline]
```

### 图文示例

```text
[timeline]
2026-03-06|发布第一个版本|https://example.com/a.jpg, https://example.com/b.png
2026-03-10|修复并更新|https://example.com/c.jpg
[/timeline]
```

### 指定排序

```text
[timeline order="asc"]
2026-03-01|项目启动
2026-03-03|完成线框设计
[/timeline]
```

- `order="desc"`（默认）：最新在前
- `order="asc"`：最早在前

## 语法规则

- 空行会自动忽略。
- 以 `#` 开头的行会视为注释并忽略。
- 日期支持：`YYYY-MM-DD`、`YYYY/MM/DD`、`YYYY.MM.DD`。
- 多张图片使用英文逗号分隔，图片 URL 仅允许 `http/https`。

## 描述字段支持

描述字段支持“行内 Markdown + 安全 HTML”：

- Markdown：`**加粗**`、`*斜体*`、`~~删除线~~`、`` `代码` ``、`==高亮==`、`[链接](https://...)`、`![alt](https://...)`
- 安全 HTML：`a`、`img`、`strong`、`em`、`del`、`code`、`mark`、`br`

不安全协议和危险标签会被过滤。

## 插件设置项

- `默认时间轴排序`：`desc` / `asc`
- `显示年度记录数`：开/关
- `桌面端图片列数`：`2` / `3` / `4`
- `启用图片预览灯箱`：开/关
- `注入默认样式`：开/关

## 输出结构

插件输出语义化结构：

- 年份分组：`section`
- 事件列表：`ol > li`
- 样式前缀：`tc-timeline*`

## 目录结构

```text
TimelinePage/
|- Plugin.php
|- README.md
|- assets/
|  |- timeline.css
|  `- timeline.js
`- src/
   |- Parser.php
   |- Renderer.php
   `- Sanitizer.php
```
