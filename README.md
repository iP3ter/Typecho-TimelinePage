# TimelinePage for Typecho

在 Typecho 页面正文中写 `[timeline]...[/timeline]`，自动渲染成时间轴。

## 安装

1. 将 `TimelinePage` 文件夹放到 Typecho 的 `usr/plugins/` 目录。
2. 进入 Typecho 后台 `控制台 -> 插件`。
3. 启用 `TimelinePage`。

## 页面写法

在页面内容里直接写：

```text
[timeline]
2026-03-01|项目启动|确定需求与范围
2026-03-03|完成设计|页面风格与结构定稿
2026-03-06|上线|发布首个版本
[/timeline]
```

带图片示例（第 4 段图片 URL）：

```text
[timeline]
2026-03-06|更新|版本升级与修复|https://example.com/img/a.jpg
2026-03-10|发布|上线新页面|https://example.com/img/b.jpg, https://example.com/img/c.png
[/timeline]
```

<img width="927" height="627" alt="image" src="https://github.com/user-attachments/assets/41a49f84-e297-4cfd-9a30-397308190655" />

## 规则

- 每一行一条记录，格式：`日期|标题|描述|图片URL`
- `描述` 可省略
- `图片URL` 可省略；多图用英文逗号分隔
- 以 `#` 开头的行会被忽略（可当注释）
- 标题和描述支持行内 Markdown：`**加粗**`、`*斜体*`、`~~删除线~~`、`` `代码` ``、`==高亮==`、`[链接](https://...)`、`![图片](https://...)`
- 兼容编辑器输出的 HTML 链接：`<a href="https://...">文字</a>`
- 兼容编辑器输出的 HTML 图片：`<img src="https://..." alt="...">`

## 备注

- 插件对文章和页面正文都生效。
- 样式是内联输出，不依赖主题文件，启用后即可使用。
- 默认是偏 HexoUI 的紧凑时间轴风格（更小间距、更轻量卡片）。
- 时间轴中的图片支持点击弹层预览（支持 `ESC` 或点击空白处关闭）。
