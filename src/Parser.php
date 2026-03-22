<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Timeline block parser.
 */
class TimelinePage_Parser
{
    /**
     * Parse one [timeline] block into normalized + grouped records.
     */
    public function parseBlock($rawBlock, $order)
    {
        $order = strtolower((string)$order) === 'asc' ? 'asc' : 'desc';
        $normalized = $this->normalizeBlock($rawBlock);
        if ($normalized === '') {
            return array(
                'order' => $order,
                'groups' => array(),
                'total' => 0
            );
        }

        $items = array();
        $lines = preg_split('/\r\n|\r|\n/', $normalized);
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            // New syntax only: date|content (supports markdown, including ![]()).
            $parts = preg_split('/\s*[|｜]\s*/u', $line, 2);
            if (!is_array($parts) || count($parts) < 2) {
                continue;
            }

            $dateInfo = $this->parseDate($parts[0]);
            if ($dateInfo === null) {
                continue;
            }

            $descRaw = trim((string)$parts[1]);
            if ($descRaw === '') {
                continue;
            }

            $contentHtml = TimelinePage_Sanitizer::renderInline($descRaw, true);
            if ($contentHtml === '') {
                continue;
            }

            $items[] = array(
                'year' => $dateInfo['year'],
                'date' => $dateInfo['date'],
                'month_day' => $dateInfo['month_day'],
                'timestamp' => $dateInfo['timestamp'],
                'content_html' => $contentHtml
            );
        }

        if (empty($items)) {
            return array(
                'order' => $order,
                'groups' => array(),
                'total' => 0
            );
        }

        usort($items, function ($left, $right) use ($order) {
            if ($left['timestamp'] === $right['timestamp']) {
                return 0;
            }
            if ($order === 'asc') {
                return ($left['timestamp'] < $right['timestamp']) ? -1 : 1;
            }
            return ($left['timestamp'] > $right['timestamp']) ? -1 : 1;
        });

        $groups = array();
        foreach ($items as $item) {
            $year = (string)$item['year'];
            if (!isset($groups[$year])) {
                $groups[$year] = array();
            }
            $groups[$year][] = $item;
        }

        return array(
            'order' => $order,
            'groups' => $groups,
            'total' => count($items)
        );
    }

    /**
     * Normalize editor HTML wrappers into plain timeline lines.
     */
    private function normalizeBlock($rawBlock)
    {
        $text = (string)$rawBlock;
        $text = str_ireplace(array('<br>', '<br/>', '<br />'), "\n", $text);
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $text);
        $text = preg_replace('/<\/?p[^>]*>/i', '', $text);
        return trim((string)$text);
    }

    /**
     * Parse date and normalize to year/date/month_day/timestamp.
     */
    private function parseDate($rawDate)
    {
        $rawDate = trim((string)$rawDate);
        if (!preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $rawDate, $match)) {
            return null;
        }

        $year = (int)$match[1];
        $month = (int)$match[2];
        $day = (int)$match[3];
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        $timestamp = mktime(0, 0, 0, $month, $day, $year);
        return array(
            'year' => $year,
            'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'month_day' => sprintf('%02d/%02d', $month, $day),
            'timestamp' => $timestamp
        );
    }
}
