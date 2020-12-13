<?php

declare(strict_types=1);

namespace Alpdesk\AlpdeskFrontendediting\Listener;

use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Contao\Template;
use Contao\ContentModel;

class HooksListener {

  public function onGetPageLayout(PageModel $objPage, LayoutModel $objLayout, PageRegular $objPageRegular): void {

    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/alpdeskfrontendediting/js/alpdeskfrontendediting_fe.js|async';
    $GLOBALS['TL_CSS'][] = 'bundles/alpdeskfrontendediting/css/alpdeskfrontendediting_fe.css';
  }

  public function onParseTemplate(Template $objTemplate) {
    
  }

  public function onGetContentElement(ContentModel $element, string $buffer): string {

    if (TL_MODE == 'FE') {

      $classes = 'alpdeskfee-ce';

      $dataAttributes = \array_filter(['data-alpdeskfee-id' => $element->id], function ($v) {
        return null !== $v;
      });

      $buffer = \preg_replace_callback('|<([a-zA-Z0-9]+)(\s[^>]*?)?(?<!/)>|', function ($matches) use ($classes, $dataAttributes) {
        $tag = $matches[1];
        $attributes = $matches[2];

        $attributes = preg_replace('/class="([^"]+)"/', 'class="$1 ' . $classes . '"', $attributes, 1, $count);
        if (0 === $count) {
          $attributes .= ' class="' . $classes . '"';
        }

        foreach ($dataAttributes as $key => $value) {
          $attributes .= ' ' . $key . '="' . $value . '"';
        }

        return "<{$tag}{$attributes}>";
      }, $buffer, 1);
    }

    return $buffer;
  }

}
