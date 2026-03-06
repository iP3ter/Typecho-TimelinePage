<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * 页面时间轴插件
 *
 * @package TimelinePage
 * @author P3ter
 * @version 1.0.1
 * @link https://github.com/iP3ter/Typecho-TimelinePage
 */
class TimelinePage_Plugin implements Typecho_Plugin_Interface
{
    private static $styleInjected = false;
    private static $scriptInjected = false;

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        $factoryName = 'Widget_Abstract_Contents';
        try {
            $themeName = strtolower((string)Typecho_Widget::widget('Widget_Options')->theme);
            if (strpos($themeName, 'mirages') !== false) {
                $factoryName = 'Mirages_Plugin';
            }
        } catch (Exception $e) {
        } catch (Error $e) {
        }

        self::registerContentFilterHooks($factoryName);
        return _t('TimelinePage 插件已启用');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
        return _t('TimelinePage 插件已禁用');
    }

    /**
     * 获取插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        try {
            $textClass = class_exists('Typecho_Widget_Helper_Form_Element_Text')
                ? 'Typecho_Widget_Helper_Form_Element_Text'
                : (class_exists('\\Typecho\\Widget\\Helper\\Form\\Element\\Text') ? '\\Typecho\\Widget\\Helper\\Form\\Element\\Text' : '');

            if ($textClass === '') {
                return;
            }

            $accentColor = new $textClass(
                'accentColor',
                array(),
                '',
                _t('自定义日期颜色（默认模式）'),
                _t('留空则自动跟随主题主色；格式仅支持 Hex，如 #10bfa8 或 10bfa8。')
            );
            $form->addInput($accentColor);

            $accentColorDark = new $textClass(
                'accentColorDark',
                array(),
                '',
                _t('自定义日期颜色（夜间模式）'),
                _t('可选。留空则沿用默认模式颜色或主题主色；格式仅支持 Hex。')
            );
            $form->addInput($accentColorDark);
        } catch (Exception $e) {
            return;
        } catch (Error $e) {
            return;
        }
    }

    /**
     * 个人用户的配置面板
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 注册内容过滤钩子，兼容默认 Typecho 与 Mirages 主题插件代理
     */
    private static function registerContentFilterHooks($factoryName)
    {
        // 内容与摘要都挂钩，兼容不同主题的调用点
        Typecho_Plugin::factory($factoryName)->contentEx = array('TimelinePage_Plugin', 'filterContent');
        Typecho_Plugin::factory($factoryName)->excerptEx = array('TimelinePage_Plugin', 'filterContent');
        Typecho_Plugin::factory($factoryName)->content = array('TimelinePage_Plugin', 'filterContent');
        Typecho_Plugin::factory($factoryName)->excerpt = array('TimelinePage_Plugin', 'filterContent');
    }

    /**
     * 过滤文章/页面内容，将 [timeline] 块转换为时间轴 HTML
     */
    public static function filterContent($content, $widget = null, $lastResult = null)
    {
        $text = ($lastResult !== null && $lastResult !== '') ? $lastResult : $content;
        if (!is_string($text)) {
            return $text;
        }

        if (stripos($text, '[timeline') === false) {
            return $text;
        }

        $pattern = '/\[timeline\](.*?)\[\/timeline\]/is';

        return preg_replace_callback($pattern, function ($matches) {
            return TimelinePage_Plugin::renderTimelineBlock($matches[1]);
        }, $text);
    }

    /**
     * 将时间轴块正文渲染为 HTML
     */
    private static function renderTimelineBlock($rawBlock)
    {
        $normalized = str_ireplace(array('<br>', '<br/>', '<br />'), "\n", $rawBlock);
        $normalized = preg_replace('/<\/?p[^>]*>/i', '', $normalized);
        $lines = preg_split('/\r\n|\r|\n/', trim($normalized));
        $items = array();

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line, 4));
            $date = isset($parts[0]) ? $parts[0] : '';
            $desc = isset($parts[2]) ? $parts[2] : '';
            $imageField = isset($parts[3]) ? $parts[3] : '';

            if ($date === '') {
                continue;
            }

            if ($desc === '' && isset($parts[1])) {
                $desc = (string)$parts[1];
            }

            $items[] = array(
                'date' => htmlspecialchars($date, ENT_QUOTES, 'UTF-8'),
                'desc' => self::renderInlineMarkdown($desc, true),
                'images' => self::parseImageField($imageField)
            );
        }

        if (empty($items)) {
            return '';
        }

        $html = self::getStyleBlock();
        $html .= self::getScriptBlock();
        $html .= '<div class="tlp-timeline">';

        foreach ($items as $item) {
            $hasBody = ($item['desc'] !== '' || !empty($item['images']));

            $html .= '<article class="tlp-item">';
            $html .= '<header class="tlp-head">';
            $html .= '<span class="tlp-dot" aria-hidden="true"></span>';
            $html .= '<time class="tlp-date">' . $item['date'] . '</time>';
            $html .= '</header>';

            if ($hasBody) {
                $html .= '<div class="tlp-body">';
                $html .= '<div class="tlp-card">';
                if ($item['desc'] !== '') {
                    $html .= '<p class="tlp-desc">' . $item['desc'] . '</p>';
                }
                if (!empty($item['images'])) {
                    $html .= '<div class="tlp-gallery">';
                    foreach ($item['images'] as $image) {
                        $html .= '<a class="tlp-image-link tlp-lightbox-trigger" data-src="' . $image['url'] . '" href="' . $image['url'] . '" target="_blank" rel="noopener noreferrer">';
                        $html .= '<img class="tlp-image" src="' . $image['url'] . '" alt="' . $image['alt'] . '" loading="lazy" referrerpolicy="no-referrer" />';
                        $html .= '</a>';
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
                $html .= '</div>';
            }

            $html .= '</article>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * 解析图片字段（可选第 4 段）
     * 支持使用英文逗号分隔多图：
     * https://a.com/1.jpg, https://b.com/2.png
     */
    private static function parseImageField($imageField)
    {
        $imageField = trim((string)$imageField);
        if ($imageField === '') {
            return array();
        }

        $rawItems = preg_split('/\s*,\s*/', $imageField);
        $images = array();

        foreach ($rawItems as $url) {
            $url = trim($url);
            if (!self::isSafeHttpUrl($url)) {
                continue;
            }

            $path = (string)parse_url($url, PHP_URL_PATH);
            $filename = $path === '' ? 'timeline-image' : basename($path);

            $images[] = array(
                'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                'alt' => htmlspecialchars($filename, ENT_QUOTES, 'UTF-8')
            );
        }

        return $images;
    }

    /**
     * 解析安全的行内 Markdown
     * 支持: **bold** *italic* ~~del~~ `code` ==mark== [text](https://...)
     * 支持: ![alt](https://...) / <img src="https://...">
     * 兼容: <a href="https://...">text</a>
     */
    private static function renderInlineMarkdown($text, $allowBreak = false)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }

        $placeholders = array();
        $text = preg_replace_callback('/<img\s+[^>]*src\s*=\s*(["\'])(.*?)\1[^>]*>/is', function ($m) use (&$placeholders) {
            $tag = $m[0];
            $url = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
            if (!self::isSafeHttpUrl($url)) {
                return $m[0];
            }

            $alt = '';
            if (preg_match('/alt\s*=\s*(["\'])(.*?)\1/is', $tag, $altMatch)) {
                $alt = trim((string)$altMatch[2]);
            }

            $key = '@@TLPIMG' . count($placeholders) . '@@';
            $placeholders[$key] = self::buildImageTag($url, $alt);
            return $key;
        }, $text);

        $text = preg_replace_callback('/!\[([^\]]*)\]\((https?:\/\/[^\s\)]+)\)/i', function ($m) use (&$placeholders) {
            $url = trim($m[2]);
            if (!self::isSafeHttpUrl($url)) {
                return $m[0];
            }

            $alt = trim($m[1]);
            $key = '@@TLPIMG' . count($placeholders) . '@@';
            $placeholders[$key] = self::buildImageTag($url, $alt);
            return $key;
        }, $text);

        $text = preg_replace_callback('/<a\s+[^>]*href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is', function ($m) use (&$placeholders) {
            $url = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
            if (!self::isSafeHttpUrl($url)) {
                return $m[0];
            }

            $labelRaw = trim(strip_tags($m[3]));
            $label = $labelRaw === '' ? $url : $labelRaw;

            $key = '@@TLPLINK' . count($placeholders) . '@@';
            $placeholders[$key] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            return $key;
        }, $text);

        $text = preg_replace_callback('/(?<!\!)\[(.+?)\]\((https?:\/\/[^\s\)]+)\)/i', function ($m) use (&$placeholders) {
            $url = trim($m[2]);
            if (!self::isSafeHttpUrl($url)) {
                return $m[0];
            }

            $key = '@@TLPLINK' . count($placeholders) . '@@';
            $label = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $placeholders[$key] = '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
            return $key;
        }, $text);

        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $rules = array(
            '/\*\*([^\*\n]+)\*\*/' => '<strong>$1</strong>',
            '/\*([^\*\n]+)\*/' => '<em>$1</em>',
            '/~~([^~\n]+)~~/' => '<del>$1</del>',
            '/==([^=\n]+)==/' => '<mark>$1</mark>',
            '/`([^`\n]+)`/' => '<code>$1</code>'
        );

        foreach ($rules as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }

        if (!empty($placeholders)) {
            $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);
        }

        if ($allowBreak) {
            $html = nl2br($html);
        }

        return $html;
    }

    private static function buildImageTag($url, $alt = '')
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $safeAlt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
        return '<img class="tlp-inline-image tlp-lightbox-trigger" data-src="' . $safeUrl . '" src="' . $safeUrl . '" alt="' . $safeAlt . '" loading="lazy" referrerpolicy="no-referrer" />';
    }

    private static function getPluginOptionValue($name)
    {
        $pluginOptions = Typecho_Widget::widget('Widget_Options')->plugin('TimelinePage');
        if (!is_object($pluginOptions) || !isset($pluginOptions->{$name})) {
            return '';
        }

        return trim((string)$pluginOptions->{$name});
    }

    private static function normalizeHexColor($color)
    {
        $color = trim((string)$color);
        if ($color === '') {
            return '';
        }

        if (preg_match('/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color, $matches) !== 1) {
            return '';
        }

        return '#' . strtolower($matches[1]);
    }

    private static function isSafeHttpUrl($url)
    {
        $validUrl = filter_var($url, FILTER_VALIDATE_URL);
        if (!$validUrl) {
            return false;
        }

        $scheme = strtolower((string)parse_url($validUrl, PHP_URL_SCHEME));
        return in_array($scheme, array('http', 'https'), true);
    }

    /**
     * 输出图片预览脚本（每次渲染都可安全重复插入）
     */
    private static function getScriptBlock()
    {
        if (self::$scriptInjected) {
            return '';
        }
        self::$scriptInjected = true;

        return '<div class="tlp-lightbox" id="tlp-lightbox" aria-hidden="true">
            <button type="button" class="tlp-lightbox-close" aria-label="Close">&times;</button>
            <img class="tlp-lightbox-image" src="" alt="" />
        </div>
        <script>
        (function () {
            if (window.__tlpLightboxReady) {
                return;
            }
            window.__tlpLightboxReady = true;

            var lightbox = document.getElementById("tlp-lightbox");
            if (!lightbox) {
                return;
            }
            var imageEl = lightbox.querySelector(".tlp-lightbox-image");
            var closeEl = lightbox.querySelector(".tlp-lightbox-close");
            var accentObserver = null;

            var isValidColor = function (value) {
                if (!value) {
                    return false;
                }
                var tester = document.createElement("span");
                tester.style.color = "";
                tester.style.color = value;
                return tester.style.color !== "";
            };

            var readFirstColorVariable = function (styleMap, keys) {
                if (!styleMap || !keys || !keys.length) {
                    return "";
                }
                for (var i = 0; i < keys.length; i++) {
                    var color = (styleMap.getPropertyValue(keys[i]) || "").trim();
                    if (isValidColor(color)) {
                        return color;
                    }
                }
                return "";
            };

            var resolveThemeAccent = function () {
                var rootStyle = window.getComputedStyle(document.documentElement);
                var candidates = [
                    "--theme-color",
                    "--theme-primary",
                    "--theme-primary-color",
                    "--primary-color",
                    "--accent-color",
                    "--main-color",
                    "--color-primary",
                    "--heo-main",
                    "--joe-theme"
                ];

                var color = readFirstColorVariable(rootStyle, candidates);
                if (color) {
                    return color;
                }

                var bodyStyle = window.getComputedStyle(document.body || document.documentElement);
                color = readFirstColorVariable(bodyStyle, candidates);
                if (color) {
                    return color;
                }

                var link = document.querySelector("main a, article a, .entry-content a, .post-content a, a");
                if (link) {
                    color = (window.getComputedStyle(link).color || "").trim();
                    if (isValidColor(color)) {
                        return color;
                    }
                }

                var themeMeta = document.querySelector("meta[name=\'theme-color\']");
                if (themeMeta) {
                    color = (themeMeta.getAttribute("content") || "").trim();
                    if (isValidColor(color)) {
                        return color;
                    }
                }

                return "";
            };

            var isDarkTheme = function () {
                var root = document.documentElement;
                var body = document.body || document.documentElement;
                var marker = (
                    (root.getAttribute("data-theme") || "") + " " +
                    (root.className || "") + " " +
                    (body.getAttribute("data-theme") || "") + " " +
                    (body.className || "")
                ).toLowerCase();

                if (marker.indexOf("dark") !== -1) {
                    return true;
                }

                if (window.matchMedia) {
                    return window.matchMedia("(prefers-color-scheme: dark)").matches;
                }

                return false;
            };

            var applyTimelineAccent = function () {
                var accent = resolveThemeAccent();
                var darkMode = isDarkTheme();

                var timelines = document.querySelectorAll(".tlp-timeline");
                for (var i = 0; i < timelines.length; i++) {
                    var timeline = timelines[i];
                    var timelineStyle = window.getComputedStyle(timeline);
                    var customAccent = (timelineStyle.getPropertyValue("--tlp-accent-user") || "").trim();
                    var customAccentDark = (timelineStyle.getPropertyValue("--tlp-accent-user-dark") || "").trim();

                    if (isValidColor(customAccent) || isValidColor(customAccentDark)) {
                        var lockedAccent = customAccent;
                        if (darkMode && isValidColor(customAccentDark)) {
                            lockedAccent = customAccentDark;
                        }
                        if (!isValidColor(lockedAccent) && isValidColor(customAccent)) {
                            lockedAccent = customAccent;
                        }
                        if (isValidColor(lockedAccent)) {
                            timeline.style.setProperty("--tlp-accent", lockedAccent);
                            continue;
                        }
                    }

                    if (accent) {
                        timeline.style.setProperty("--tlp-accent", accent);
                    }
                }
            };

            var open = function (src, alt) {
                if (!src || !imageEl) {
                    return;
                }
                imageEl.src = src;
                imageEl.alt = alt || "";
                lightbox.classList.add("is-open");
                lightbox.setAttribute("aria-hidden", "false");
                document.body.classList.add("tlp-lightbox-open");
            };

            var close = function () {
                lightbox.classList.remove("is-open");
                lightbox.setAttribute("aria-hidden", "true");
                document.body.classList.remove("tlp-lightbox-open");
                if (imageEl) {
                    imageEl.src = "";
                    imageEl.alt = "";
                }
            };

            document.addEventListener("click", function (event) {
                var trigger = event.target.closest(".tlp-lightbox-trigger");
                if (!trigger) {
                    return;
                }

                var source = trigger.getAttribute("data-src");
                var altText = trigger.getAttribute("alt") || "";
                if (!source && trigger.tagName === "A") {
                    source = trigger.getAttribute("href");
                }
                if (!source) {
                    var innerImage = trigger.querySelector("img");
                    if (innerImage) {
                        source = innerImage.getAttribute("src");
                        altText = innerImage.getAttribute("alt") || altText;
                    }
                }
                if (!source) {
                    return;
                }

                event.preventDefault();
                open(source, altText);
            });

            if (closeEl) {
                closeEl.addEventListener("click", close);
            }

            lightbox.addEventListener("click", function (event) {
                if (event.target === lightbox) {
                    close();
                }
            });

            document.addEventListener("keydown", function (event) {
                if (event.key === "Escape" && lightbox.classList.contains("is-open")) {
                    close();
                }
            });

            applyTimelineAccent();
            window.setTimeout(applyTimelineAccent, 60);

            if (window.matchMedia) {
                var media = window.matchMedia("(prefers-color-scheme: dark)");
                if (media.addEventListener) {
                    media.addEventListener("change", applyTimelineAccent);
                } else if (media.addListener) {
                    media.addListener(applyTimelineAccent);
                }
            }

            if (window.MutationObserver) {
                accentObserver = new MutationObserver(function () {
                    applyTimelineAccent();
                });
                accentObserver.observe(document.documentElement, {
                    attributes: true,
                    attributeFilter: ["class", "style", "data-theme", "theme"]
                });
            }
        })();
        </script>';
    }

    /**
     * 输出内联样式（每次渲染都可安全重复插入）
     */
    private static function getStyleBlock()
    {
        if (self::$styleInjected) {
            return '';
        }
        self::$styleInjected = true;

        $accentColor = self::normalizeHexColor(self::getPluginOptionValue('accentColor'));
        $accentColorDark = self::normalizeHexColor(self::getPluginOptionValue('accentColorDark'));
        $customAccentCss = '';
        if ($accentColor !== '') {
            $customAccentCss .= '--tlp-accent-user:' . $accentColor . ';';
        }
        if ($accentColorDark !== '') {
            $customAccentCss .= '--tlp-accent-user-dark:' . $accentColorDark . ';';
        }

        return '<style>
        .tlp-timeline{
            ' . $customAccentCss . '
            --tlp-accent:var(--tlp-accent-user,var(--timeline-accent,var(--theme-color,var(--theme-primary,var(--theme-primary-color,var(--primary-color,var(--accent-color,#10bfa8)))))));
            --tlp-text:#2d353f;
            --tlp-muted:#556070;
            --tlp-card:#f2f2f3;
            --tlp-font:"Avenir Next","Segoe UI","PingFang SC","Hiragino Sans GB","Microsoft YaHei",sans-serif;
            margin:12px 0;
            display:grid;
            gap:12px;
            font-family:var(--tlp-font);
            color:var(--tlp-text);
        }
        .tlp-item{
            position:relative;
            padding-left:2px;
        }
        .tlp-head{
            display:flex;
            align-items:center;
            gap:12px;
            margin:0 0 5px;
        }
        .tlp-dot{
            width:10px;
            height:10px;
            flex:0 0 auto;
            border-radius:50%;
            border:2px solid var(--tlp-accent);
            background:#fff;
            box-sizing:border-box;
        }
        .tlp-date{
            display:block;
            font-size:32px;
            line-height:1.2;
            letter-spacing:.4px;
            color:var(--tlp-accent);
            font-weight:500;
            white-space:normal;
            word-break:break-word;
        }
        .tlp-body{
            position:relative;
            margin-left:6px;
            padding-left:28px;
        }
        .tlp-body:before{
            content:"";
            position:absolute;
            left:2px;
            top:1px;
            bottom:9px;
            width:2px;
            background:var(--tlp-accent);
            opacity:.9;
            border-radius:2px;
        }
        .tlp-card{
            background:var(--tlp-card);
            border-radius:28px;
            padding:13px 22px;
            display:inline-flex;
            flex-direction:column;
            justify-content:center;
            align-items:center;
            vertical-align:middle;
            max-width:100%;
            text-align:center;
        }
        .tlp-desc{
            margin:0;
            font-size:15px;
            line-height:1.72;
            font-weight:600;
            color:var(--tlp-text);
            text-align:center;
        }
        .tlp-card mark{
            background:#f6edbf;
            color:inherit;
            padding:0 .18em;
            border-radius:4px;
        }
        .tlp-card a{
            color:var(--tlp-accent);
            text-decoration:none;
            font-weight:600;
        }
        .tlp-card code{
            font-family:"Consolas","SFMono-Regular","Menlo","Monaco",monospace;
            font-size:.92em;
            padding:.08em .32em;
            border-radius:6px;
            background:#e7ebf0;
            color:#344253;
        }
        .tlp-inline-image{
            display:block;
            width:min(100%,420px);
            max-width:100%;
            margin-top:8px;
            border-radius:12px;
            background:#e8ecef;
            object-fit:cover;
            cursor:zoom-in;
        }
        .tlp-card strong{font-weight:700}
        .tlp-card em{font-style:italic}
        .tlp-card del{opacity:.78}
        .tlp-gallery{
            margin-top:10px;
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
            gap:10px;
        }
        .tlp-image-link{
            display:block;
            border-radius:12px;
            overflow:hidden;
            background:#e8ecef;
            cursor:zoom-in;
        }
        .tlp-image{
            display:block;
            width:100%;
            aspect-ratio:4/3;
            object-fit:cover;
            transition:transform .22s ease;
        }
        .tlp-image-link:hover .tlp-image{
            transform:scale(1.04);
        }
        .tlp-lightbox-open{
            overflow:hidden;
        }
        .tlp-lightbox{
            position:fixed;
            inset:0;
            z-index:99999;
            background:rgba(12,18,24,.82);
            display:none;
            align-items:center;
            justify-content:center;
            padding:24px;
        }
        .tlp-lightbox.is-open{
            display:flex;
        }
        .tlp-lightbox-image{
            max-width:min(96vw,1200px);
            max-height:88vh;
            width:auto;
            height:auto;
            border-radius:12px;
            box-shadow:0 20px 45px rgba(0,0,0,.32);
            background:#fff;
            object-fit:contain;
        }
        .tlp-lightbox-close{
            position:absolute;
            top:14px;
            right:16px;
            width:40px;
            height:40px;
            border:none;
            border-radius:999px;
            background:rgba(255,255,255,.18);
            color:#fff;
            font-size:28px;
            line-height:1;
            cursor:pointer;
        }
        .tlp-lightbox-close:hover{
            background:rgba(255,255,255,.28);
        }
        @media (prefers-color-scheme:dark){
            .tlp-timeline{
                --tlp-accent:var(--tlp-accent-user-dark,var(--tlp-accent-user,var(--timeline-accent,var(--theme-color,var(--theme-primary,var(--theme-primary-color,var(--primary-color,var(--accent-color,#10bfa8))))))));
            }
        }
        @media (max-width:640px){
            .tlp-timeline{gap:9px}
            .tlp-head{gap:9px;margin-bottom:4px}
            .tlp-dot{width:9px;height:9px}
            .tlp-date{font-size:21px;line-height:1.3}
            .tlp-body{padding-left:20px}
            .tlp-body:before{bottom:6px}
            .tlp-card{padding:10px 12px;border-radius:16px}
            .tlp-desc{font-size:13px;line-height:1.62}
            .tlp-gallery{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
            .tlp-lightbox{padding:12px}
            .tlp-lightbox-close{top:10px;right:10px}
        }
        </style>';
    }
}
