<?php

declare(strict_types=1);

namespace Alpdesk\AlpdeskFrontendediting\Mapping\Mappingtypes;

use Alpdesk\AlpdeskFrontendediting\Mapping\Mappingtypes\Base;
use Alpdesk\AlpdeskFrontendediting\Custom\CustomViewItem;
use Contao\BackendUser;
use Contao\Input;
use Contao\StringUtil;

class TypeNewsReader extends Base {

  private static $icon = '../../../system/themes/flexible/icons/news.svg';
  private static $iconclass = 'tl_news_baritem';
  private static $DO = 'do=news&table=tl_content';

  public function run(CustomViewItem $item): CustomViewItem {

    if (class_exists('\Contao\NewsModel')) {
      $newsarchives = StringUtil::deserialize($this->module->news_archives);
      $objNews = \Contao\NewsModel::findPublishedByParentAndIdOrAlias(Input::get('items'), $newsarchives);
      if ($objNews !== null) {
        $objArchive = $objNews->getRelated('pid');
        if (BackendUser::getInstance()->hasAccess($objArchive->id, 'news')) {
          $item->setValid(true);
          $item->setIcon(self::$icon);
          $item->setIconclass(self::$iconclass);
          $item->setPath(self::$DO . '&id=' . $objNews->id);
          $item->setLabel($GLOBALS['TL_LANG']['alpdeskfee_mapping_lables']['news']);
        }
      }
    }

    return $item;
  }

}
