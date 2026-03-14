<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/**
 * Timeline HTML renderer.
 */
class TimelinePage_Renderer
{
    private $showYearCount = true;
    private $galleryColumns = 3;
    private $enableImagePreview = true;

    public function __construct($options = array())
    {
        if (isset($options['showYearCount'])) {
            $this->showYearCount = (bool)$options['showYearCount'];
        }
        if (isset($options['galleryColumns'])) {
            $columns = (int)$options['galleryColumns'];
            if ($columns < 2) {
                $columns = 2;
            } elseif ($columns > 4) {
                $columns = 4;
            }
            $this->galleryColumns = $columns;
        }
        if (isset($options['enableImagePreview'])) {
            $this->enableImagePreview = (bool)$options['enableImagePreview'];
        }
    }

    /**
     * Render normalized data to timeline HTML.
     */
    public function render($parsed)
    {
        if (!is_array($parsed) || empty($parsed['groups'])) {
            return '';
        }

        $order = isset($parsed['order']) && strtolower((string)$parsed['order']) === 'asc' ? 'asc' : 'desc';
        $html = '<div class="tc-timeline" data-order="' . $this->h($order) . '" style="--tc-gallery-columns:' . $this->galleryColumns . ';">';

        foreach ($parsed['groups'] as $year => $items) {
            if (!is_array($items) || empty($items)) {
                continue;
            }

            $html .= '<section class="tc-timeline-year">';
            $html .= '<header class="tc-timeline-year__header">';
            $html .= '<h2 class="tc-timeline-year__title">' . $this->h((string)$year) . '</h2>';
            if ($this->showYearCount) {
                $html .= '<span class="tc-timeline-year__count">' . count($items) . ' 条记录</span>';
            }
            $html .= '</header>';

            $html .= '<ol class="tc-timeline-list">';
            foreach ($items as $item) {
                $date = isset($item['month_day']) ? (string)$item['month_day'] : '';
                $contentHtml = isset($item['content_html']) ? (string)$item['content_html'] : '';
                $images = isset($item['images']) && is_array($item['images']) ? $item['images'] : array();

                $html .= '<li class="tc-timeline-item">';
                $html .= '<time class="tc-timeline-item__date" datetime="' . $this->h(isset($item['date']) ? (string)$item['date'] : '') . '">' . $this->h($date) . '</time>';
                $html .= '<div class="tc-timeline-item__axis" aria-hidden="true"><span class="tc-timeline-item__dot"></span></div>';
                $html .= '<div class="tc-timeline-item__content">';
                $cardClass = 'tc-timeline-item__card';
                if (!empty($images)) {
                    $cardClass .= ' has-gallery';
                }
                $html .= '<div class="' . $cardClass . '">';

                if ($contentHtml !== '') {
                    $html .= '<div class="tc-timeline-item__text">' . $contentHtml . '</div>';
                }

                if (!empty($images)) {
                    $html .= '<div class="tc-timeline-item__gallery">';
                    foreach ($images as $image) {
                        $url = isset($image['url']) ? (string)$image['url'] : '';
                        if ($url === '') {
                            continue;
                        }
                        $alt = isset($image['alt']) ? (string)$image['alt'] : 'timeline-image';
                        $linkClass = 'tc-timeline-item__image-link';
                        if ($this->enableImagePreview) {
                            $linkClass .= ' js-tc-preview';
                        }

                        $html .= '<a class="' . $linkClass . '" href="' . $url . '" target="_blank" rel="noopener noreferrer">';
                        $html .= '<img class="tc-timeline-item__image" src="' . $url . '" alt="' . $alt . '" loading="lazy" referrerpolicy="no-referrer">';
                        $html .= '</a>';
                    }
                    $html .= '</div>';
                }

                $html .= '</div>';
                $html .= '</div>';
                $html .= '</li>';
            }
            $html .= '</ol>';
            $html .= '</section>';
        }

        $html .= '</div>';
        return $html;
    }

    private function h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}


