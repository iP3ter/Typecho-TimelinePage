<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Timeline content sanitizer and inline markdown renderer.
 */
class TimelinePage_Sanitizer
{
    /**
     * Keep only safe http/https URLs and return escaped url string.
     */
    public static function sanitizeUrl($url)
    {
        $url = trim(html_entity_decode((string)$url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return '';
        }

        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Parse comma separated image URLs.
     */
    public static function parseImageList($imageField)
    {
        $imageField = trim((string)$imageField);
        if ($imageField === '') {
            return array();
        }

        $images = array();
        $seen = array();
        $rawList = preg_split('/\s*,\s*/', $imageField);
        foreach ($rawList as $rawUrl) {
            $rawUrl = trim($rawUrl);
            if ($rawUrl === '') {
                continue;
            }

            $safeUrl = self::sanitizeUrl($rawUrl);
            if ($safeUrl === '' || isset($seen[$safeUrl])) {
                continue;
            }
            $seen[$safeUrl] = true;

            $path = (string)parse_url($rawUrl, PHP_URL_PATH);
            $name = $path === '' ? 'timeline-image' : basename($path);
            if ($name === '' || $name === '/' || $name === '.') {
                $name = 'timeline-image';
            }

            $images[] = array(
                'url' => $safeUrl,
                'alt' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            );
        }

        return $images;
    }

    /**
     * Convert inline markdown and safe HTML into escaped HTML.
     */
    public static function renderInline($text, $allowBreak)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return '';
        }

        $tokens = array();

        // Preserve already existing safe HTML tags as canonical tokens.
        $text = self::extractHtmlLinks($text, $tokens);
        $text = self::extractHtmlImages($text, $tokens);
        $text = self::extractSimpleHtmlTags($text, $tokens);

        // Escape everything else first.
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Render markdown syntax.
        $text = self::renderMarkdownImages($text);
        $text = self::renderMarkdownLinks($text);
        $text = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/==(.+?)==/', '<mark>$1</mark>', $text);
        $text = preg_replace('/\*\*([^*\n][^*]*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/~~([^~\n]+)~~/', '<del>$1</del>', $text);
        $text = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text);

        if ($allowBreak) {
            $text = preg_replace('/\r\n|\r|\n/', '<br>', $text);
        } else {
            $text = str_replace(array("\r", "\n"), '', $text);
        }

        if (!empty($tokens)) {
            $text = strtr($text, $tokens);
        }

        return $text;
    }

    private static function renderMarkdownImages($text)
    {
        return preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($match) {
            $rawUrl = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
            $safeUrl = self::sanitizeUrl($rawUrl);
            if ($safeUrl === '') {
                return $match[0];
            }

            $alt = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
            $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');
            return '<img src="' . $safeUrl . '" alt="' . $alt . '" loading="lazy" referrerpolicy="no-referrer">';
        }, $text);
    }

    private static function renderMarkdownLinks($text)
    {
        return preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($match) {
            $rawUrl = html_entity_decode($match[2], ENT_QUOTES, 'UTF-8');
            $safeUrl = self::sanitizeUrl($rawUrl);
            if ($safeUrl === '') {
                return $match[1];
            }

            $label = trim(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'));
            $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            return '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        }, $text);
    }

    private static function extractHtmlLinks($text, &$tokens)
    {
        return preg_replace_callback('/<a\s+([^>]*)>(.*?)<\/a>/is', function ($match) use (&$tokens) {
            $attrs = isset($match[1]) ? $match[1] : '';
            $content = isset($match[2]) ? $match[2] : '';
            $href = '';
            if (preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/is', $attrs, $hrefMatch)) {
                $href = $hrefMatch[2];
            }

            $safeUrl = self::sanitizeUrl($href);
            $label = trim(strip_tags($content));
            if ($label === '') {
                $label = $safeUrl !== '' ? html_entity_decode($safeUrl, ENT_QUOTES, 'UTF-8') : '';
            }
            $label = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            if ($safeUrl === '') {
                return $label;
            }

            $html = '<a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
            return self::storeToken($tokens, $html);
        }, $text);
    }

    private static function extractHtmlImages($text, &$tokens)
    {
        return preg_replace_callback('/<img\s+([^>]*?)\/?>/is', function ($match) use (&$tokens) {
            $attrs = isset($match[1]) ? $match[1] : '';

            $src = '';
            if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/is', $attrs, $srcMatch)) {
                $src = $srcMatch[2];
            }
            $safeUrl = self::sanitizeUrl($src);
            if ($safeUrl === '') {
                return '';
            }

            $alt = '';
            if (preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $attrs, $altMatch)) {
                $alt = trim($altMatch[2]);
            }
            $alt = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

            $html = '<img src="' . $safeUrl . '" alt="' . $alt . '" loading="lazy" referrerpolicy="no-referrer">';
            return self::storeToken($tokens, $html);
        }, $text);
    }

    private static function extractSimpleHtmlTags($text, &$tokens)
    {
        $text = preg_replace_callback('/<br\s*\/?>/i', function () use (&$tokens) {
            return self::storeToken($tokens, '<br>');
        }, $text);

        $pairedTags = array('strong', 'em', 'del', 'code', 'mark');
        foreach ($pairedTags as $tag) {
            $openPattern = '/<\s*' . $tag . '\s*>/i';
            $closePattern = '/<\s*\/\s*' . $tag . '\s*>/i';

            $text = preg_replace_callback($openPattern, function () use (&$tokens, $tag) {
                return self::storeToken($tokens, '<' . $tag . '>');
            }, $text);

            $text = preg_replace_callback($closePattern, function () use (&$tokens, $tag) {
                return self::storeToken($tokens, '</' . $tag . '>');
            }, $text);
        }

        return $text;
    }

    private static function storeToken(&$tokens, $html)
    {
        $key = '__TC_TOKEN_' . count($tokens) . '__';
        $tokens[$key] = $html;
        return $key;
    }
}
