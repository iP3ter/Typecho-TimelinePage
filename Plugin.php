<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Typecho 时间轴插件。
 *
 * @package TimelinePage
 * @author P3ter
 * @version 2.0.0
 * @link https://github.com/iP3ter/Typecho-TimelinePage
 */
class TimelinePage_Plugin implements Typecho_Plugin_Interface
{
    private static $assetsInjected = false;
    private static $bootstrapped = false;

    /**
     * Activate plugin.
     */
    public static function activate()
    {
        self::bootstrap();
        self::registerContentFilterHooks(self::resolveFactoryName());
        return _t('TimelinePage 插件已启用');
    }

    /**
     * Deactivate plugin.
     */
    public static function deactivate()
    {
        return _t('TimelinePage 插件已禁用');
    }

    /**
     * Plugin settings.
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $selectClass = self::resolveFormElementClass('Select');
        $radioClass = self::resolveFormElementClass('Radio');
        if ($selectClass === '' || $radioClass === '') {
            return;
        }

        $defaultOrder = new $selectClass(
            'defaultOrder',
            array(
                'desc' => _t('倒序（最新在前）'),
                'asc' => _t('正序（最早在前）')
            ),
            'desc',
            _t('默认时间轴排序'),
            _t('当短代码未指定 order 属性时使用。')
        );
        $form->addInput($defaultOrder);

        $showYearCount = new $radioClass(
            'showYearCount',
            array('1' => _t('显示'), '0' => _t('隐藏')),
            '1',
            _t('显示年度记录数'),
            _t('在每个年份标题旁显示该年的记录总数。')
        );
        $form->addInput($showYearCount);

        $galleryColumns = new $selectClass(
            'galleryColumns',
            array('2' => '2', '3' => '3', '4' => '4'),
            '3',
            _t('桌面端图片列数'),
            _t('用于每条时间轴记录中的图片网格列数。')
        );
        $form->addInput($galleryColumns);

        $enableImagePreview = new $radioClass(
            'enableImagePreview',
            array('1' => _t('启用'), '0' => _t('禁用')),
            '1',
            _t('启用图片预览灯箱'),
            _t('禁用后，点击图片仅在新标签页打开原图。')
        );
        $form->addInput($enableImagePreview);

        $injectDefaultCss = new $radioClass(
            'injectDefaultCss',
            array('1' => _t('启用'), '0' => _t('禁用')),
            '1',
            _t('注入默认样式'),
            _t('仅当主题已提供完整时间轴样式时再禁用。')
        );
        $form->addInput($injectDefaultCss);
    }

    /**
     * Personal settings (unused).
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * Replace [timeline] blocks with rendered HTML.
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

        self::bootstrap();
        if (!class_exists('TimelinePage_Parser') || !class_exists('TimelinePage_Renderer')) {
            return $text;
        }

        $pattern = '/\[timeline([^\]]*)\](.*?)\[\/timeline\]/is';
        $result = preg_replace_callback($pattern, function ($matches) {
            $attrs = self::parseShortcodeAttributes(isset($matches[1]) ? $matches[1] : '');
            $order = self::resolveOrder($attrs);

            $parser = new TimelinePage_Parser();
            $parsed = $parser->parseBlock(isset($matches[2]) ? $matches[2] : '', $order);
            if (empty($parsed['total'])) {
                return '';
            }

            $renderer = new TimelinePage_Renderer(self::getRendererOptions());
            $html = $renderer->render($parsed);
            if ($html === '') {
                return '';
            }

            if (!self::$assetsInjected) {
                $html = self::getAssetsBlock() . $html;
                self::$assetsInjected = true;
            }
            return $html;
        }, $text);

        return is_string($result) ? $result : $text;
    }

    /**
     * Register content hooks for default and Mirages proxy.
     */
    private static function registerContentFilterHooks($factoryName)
    {
        Typecho_Plugin::factory($factoryName)->contentEx = array('TimelinePage_Plugin', 'filterContent');
        Typecho_Plugin::factory($factoryName)->excerptEx = array('TimelinePage_Plugin', 'filterContent');
        Typecho_Plugin::factory($factoryName)->content = array('TimelinePage_Plugin', 'filterContent');
        Typecho_Plugin::factory($factoryName)->excerpt = array('TimelinePage_Plugin', 'filterContent');
    }

    /**
     * Detect special theme proxy hook if available.
     */
    private static function resolveFactoryName()
    {
        $factoryName = 'Widget_Abstract_Contents';
        try {
            $themeName = strtolower((string)Typecho_Widget::widget('Widget_Options')->theme);
            if (strpos($themeName, 'mirages') !== false) {
                $factoryName = 'Mirages_Plugin';
            }
        } catch (Exception $e) {
        }
        return $factoryName;
    }

    /**
     * Lazy-load module classes once.
     */
    private static function bootstrap()
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $files = array(
            __DIR__ . '/src/Sanitizer.php',
            __DIR__ . '/src/Parser.php',
            __DIR__ . '/src/Renderer.php'
        );

        foreach ($files as $file) {
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Parse shortcode attributes like order="asc".
     */
    private static function parseShortcodeAttributes($rawAttrs)
    {
        $attrs = array();
        $rawAttrs = (string)$rawAttrs;
        if ($rawAttrs === '') {
            return $attrs;
        }

        if (preg_match_all('/([a-zA-Z_][a-zA-Z0-9_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/', $rawAttrs, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = strtolower(trim($match[1]));
                $value = '';
                if (isset($match[2]) && $match[2] !== '') {
                    $value = $match[2];
                } elseif (isset($match[3]) && $match[3] !== '') {
                    $value = $match[3];
                } elseif (isset($match[4])) {
                    $value = $match[4];
                }
                $attrs[$key] = trim($value);
            }
        }
        return $attrs;
    }

    /**
     * Resolve effective order with shortcode override.
     */
    private static function resolveOrder($attrs)
    {
        if (isset($attrs['order'])) {
            $attrOrder = strtolower((string)$attrs['order']);
            if ($attrOrder === 'asc' || $attrOrder === 'desc') {
                return $attrOrder;
            }
        }

        $defaultOrder = strtolower((string)self::getOptionValue('defaultOrder', 'desc'));
        return $defaultOrder === 'asc' ? 'asc' : 'desc';
    }

    /**
     * Build renderer options from plugin settings.
     */
    private static function getRendererOptions()
    {
        $columns = (int)self::getOptionValue('galleryColumns', '3');
        if ($columns < 2) {
            $columns = 2;
        } elseif ($columns > 4) {
            $columns = 4;
        }

        return array(
            'showYearCount' => self::toBool(self::getOptionValue('showYearCount', '1')),
            'galleryColumns' => $columns,
            'enableImagePreview' => self::toBool(self::getOptionValue('enableImagePreview', '1'))
        );
    }

    /**
     * Read and inject local assets based on settings.
     */
    private static function getAssetsBlock()
    {
        $output = '';
        $injectDefaultCss = self::toBool(self::getOptionValue('injectDefaultCss', '1'));
        $enableImagePreview = self::toBool(self::getOptionValue('enableImagePreview', '1'));

        if ($injectDefaultCss) {
            $css = self::readLocalFile('assets/timeline.css');
            if ($css !== '') {
                $output .= '<style id="tc-timeline-style">' . "\n" . $css . "\n" . '</style>';
            }
        }

        if ($enableImagePreview) {
            $js = self::readLocalFile('assets/timeline.js');
            if ($js !== '') {
                $output .= '<script id="tc-timeline-script">' . "\n" . $js . "\n" . '</script>';
            }
        }

        return $output;
    }

    /**
     * Resolve plugin option safely.
     */
    private static function getOptionValue($name, $defaultValue)
    {
        try {
            $options = Typecho_Widget::widget('Widget_Options')->plugin('TimelinePage');
            if (is_object($options) && isset($options->{$name})) {
                return $options->{$name};
            }
            if (is_array($options) && array_key_exists($name, $options)) {
                return $options[$name];
            }
        } catch (Exception $e) {
        }
        return $defaultValue;
    }

    /**
     * Parse boolean values from config.
     */
    private static function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, array('1', 'true', 'yes', 'on'), true);
    }

    /**
     * Read local file content.
     */
    private static function readLocalFile($relativePath)
    {
        $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relativePath);
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }
        $content = file_get_contents($path);
        return is_string($content) ? trim($content) : '';
    }

    /**
     * Resolve Typecho form element class names across versions.
     */
    private static function resolveFormElementClass($type)
    {
        $candidates = array(
            'Typecho_Widget_Helper_Form_Element_' . $type,
            '\\Typecho\\Widget\\Helper\\Form\\Element\\' . $type
        );

        foreach ($candidates as $className) {
            if (class_exists($className)) {
                return $className;
            }
        }
        return '';
    }
}
