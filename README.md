# TimelinePage（Typecho 时间轴插件）

TimelinePage 用于在 Typecho 正文中插入时间轴，支持按年份分组、时间排序、Markdown 行内渲染（含链接与图片）和响应式展示。

## 安装

### 方式 A：Release 安装（推荐）

1. 打开发布页：`https://github.com/iP3ter/Typecho-TimelinePage/releases`
2. 下载最新压缩包并解压。
3. 确认插件目录名为 `TimelinePage`。
4. 上传到站点目录：`usr/plugins/TimelinePage/`
5. 在 Typecho 后台 `控制台 -> 插件` 启用 `TimelinePage`。

### 方式 B：源码安装

1. 克隆或下载仓库源码。
2. 保证目录名为 `TimelinePage`。
3. 上传到 `usr/plugins/`。
4. 在后台启用插件。

## 用法

在文章或独立页面正文中插入：

```text
[timeline]
2026-03-01|项目启动
2026-03-03|完成线框设计
2026-03-06|发布第一版，查看 [发布说明](https://example.com/release)
[/timeline]
```

## 图片写法（Markdown）

图片请直接写在“正文”里，使用 Markdown 图片语法 `![]()`：

```text
[timeline]
2023-04-29|更新 [Mirages](https://get233.com/archives/mirages-intro.html)；搬迁博客到 HK Server；配图：![Server](/usr/uploads/2023/04/2297759421.webp)
[/timeline]
```

也支持绝对地址：

```text
![Demo](https://example.com/demo.webp)
```

## 语法规则

1. 单条记录格式：`日期|正文`
2. 分隔符支持英文竖线 `|` 和中文竖线 `｜`
3. 日期支持：`YYYY-MM-DD`、`YYYY/MM/DD`、`YYYY.MM.DD`
4. 正文支持 Markdown 行内语法：
   - `**粗体**`
   - `*斜体*`
   - `~~删除线~~`
   - `` `code` ``
   - `==高亮==`
   - `[链接](https://...)`
   - `![图片](...)`
5. 空行会忽略；以 `#` 开头的行会被当作注释忽略

## 重要变更

- 现在只支持 `日期|正文`
- 旧写法 `日期|正文|图片URL列表` 已移除，不再解析

## 样式与结构

- 样式前缀：`tc-timeline*`
- 语义结构：年份分组 `section`，事件列表 `ol > li`

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
