<?php

declare(strict_types=1);

namespace Alpdesk\AlpdeskFrontendediting\Listener;

use Contao\LayoutModel;
use Contao\PageModel;
use Contao\ArticleModel;
use Contao\PageRegular;
use Contao\ContentModel;
use Contao\ModuleModel;
use Contao\Module;
use Contao\BackendUser;
use Contao\FrontendTemplate;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Alpdesk\AlpdeskFrontendediting\Utils\Utils;
use Alpdesk\AlpdeskFrontendediting\Custom\Custom;
use Alpdesk\AlpdeskFrontendediting\Custom\CustomResponse;

class HooksListener {

  private $tokenChecker = null;
  private $backendUser = null;
  private $currentPageId = null;
  private $pagemountAccess = false;
  private $pageChmodEdit = false;

  public function __construct(TokenChecker $tokenChecker) {
    $this->tokenChecker = $tokenChecker;
    $this->getBackendUser();
  }

  private function getBackendUser() {
    if ($this->tokenChecker->hasBackendUser()) {
      Utils::mergeUserGroupPersmissions();
      $this->backendUser = BackendUser::getInstance();
    }
  }

  public function onGetPageLayout(PageModel $objPage, LayoutModel $objLayout, PageRegular $objPageRegular): void {

    if ($this->backendUser !== null) {
      $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/alpdeskfrontendediting/js/alpdeskfrontendediting_fe.js|async';
      $GLOBALS['TL_CSS'][] = 'bundles/alpdeskfrontendediting/css/alpdeskfrontendediting_fe.css';

      $this->currentPageId = $objPage->id;
      $this->pagemountAccess = Utils::hasPagemountAccess($objPage);
      $this->pageChmodEdit = $this->backendUser->isAllowed(BackendUser::CAN_EDIT_PAGE, $objPage->row());
    }
  }

  private function checkAccess(): bool {
    if (TL_MODE == 'FE' && $this->backendUser !== null && $this->pagemountAccess == true) {
      return true;
    }
    return false;
  }

  private function createElementsTags(string $buffer, string $classes, array $attributes) {
    $dataAttributes = \array_filter($attributes, function ($v) {
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

    return $buffer;
  }

  public function onCompileArticle(FrontendTemplate $template, array $data, Module $module): void {

    if ($this->checkAccess()) {

      $canEdit = $this->backendUser->isAllowed(BackendUser::CAN_EDIT_ARTICLES, $module->getModel()->row());

      $templateArticle = new FrontendTemplate('alpdeskfrontendediting_article');
      $templateArticle->type = 'article';
      $templateArticle->desc = $GLOBALS['TL_LANG']['alpdeskfee_lables']['article'];
      $templateArticle->do = 'article';
      $templateArticle->aid = $data['id'];
      $templateArticle->articleChmodEdit = $canEdit;
      $templateArticle->pageChmodEdit = $this->pageChmodEdit;
      $templateArticle->pageid = $this->currentPageId;
      $elements = $template->elements;
      array_unshift($elements, $templateArticle->parse());
      $template->elements = $elements;
    }
  }

  public function onGetContentElement(ContentModel $element, string $buffer): string {

    if ($this->checkAccess()) {

      $modDoType = Custom::getModDoTypeCe($element);

      // We have a module as content element
      if ($modDoType->getType() == CustomResponse::$TYPE_MODULE) {
        return $this->renderModuleOutput($modDoType, $buffer);
      }

      $hasAccess = true;
      if (!$this->backendUser->hasAccess($element->type, 'elements') || !$this->backendUser->hasAccess($element->type, 'alpdesk_fee_elements')) {
        $hasAccess = false;
      }

      // We have a normale ContentElement
      // If it is not mapped in Backend we have to check the rights
      // If it´s mapped we show to enable Backendmodule edit

      if ($modDoType->getValid() == false) {
        if ($hasAccess == false) {
          return $buffer;
        }
      }

      // Check when Artikel if the element can be edited
      // Maybe the element can be inserted by inserttags in other Module without Article
      // @TODO Check whene Module has inserttag content then two bars will be shown because getContent and Module is triggered
      $canEdit = true;
      if ($element->ptable == 'tl_article') {
        $parentArticleModel = ArticleModel::findBy(['id=?'], $element->pid);
        if ($parentArticleModel !== null) {
          $canEdit = $this->backendUser->isAllowed(BackendUser::CAN_EDIT_ARTICLES, $parentArticleModel->row());
        }
      }

      $label = $GLOBALS['TL_LANG']['alpdeskfee_lables']['ce'];
      if ($modDoType->getValid() === true) {
        $label = $modDoType->getLabel();
      } else {
        $labelList = $GLOBALS['TL_LANG']['CTE'];
        if (\array_key_exists($element->type, $labelList)) {
          if (\is_array($labelList[$element->type]) && \count($labelList[$element->type]) >= 1) {
            $label = $labelList[$element->type][0];
          } else if ($labelList[$element->type] !== null && $labelList[$element->type] !== '') {
            $label = $labelList[$element->type];
          }
        }
      }

      // Maybe the User should not edit ContentElements but edit mapped Module
      // So only mapped path show be shown
      $do = str_replace('tl_', '', $element->ptable);
      if ($hasAccess == false) {
        $do = '';
      }

      $buffer = $this->createElementsTags($buffer, 'alpdeskfee-ce', [
          'data-alpdeskfee-type' => 'ce',
          'data-alpdeskfee-desc' => $label,
          'data-alpdeskfee-do' => $do,
          'data-alpdeskfee-id' => $element->id,
          'data-alpdeskfee-pid' => $element->pid,
          'data-alpdeskfee-articleChmodEdit' => $canEdit,
          'data-alpdeskfee-chmodpageedit' => $this->pageChmodEdit,
          'data-alpdeskfee-pageid' => $this->currentPageId,
          'data-alpdeskfee-act' => ($modDoType->getValid() == true ? $modDoType->getPath() : '')
      ]);
    }

    return $buffer;
  }

  private function renderModuleOutput(CustomResponse $modDoType, string $buffer) {

    if ($modDoType->getValid() === true && $modDoType->getType() == CustomResponse::$TYPE_MODULE) {
      $buffer = $this->createElementsTags($buffer, 'alpdeskfee-ce', [
          'data-alpdeskfee-type' => 'mod',
          'data-alpdeskfee-desc' => $modDoType->getLabel(),
          'data-alpdeskfee-do' => $modDoType->getPath(),
          'data-alpdeskfee-act' => $modDoType->getSublevelpath(),
          'data-alpdeskfee-chmodpageedit' => $this->pageChmodEdit,
          'data-alpdeskfee-pageid' => $this->currentPageId
      ]);
    }

    return $buffer;
  }

  public function onGetFrontendModule(ModuleModel $model, string $buffer, Module $module): string {

    if ($this->checkAccess()) {

      $modDoType = Custom::getModDoType($module);
      return $this->renderModuleOutput($modDoType, $buffer);
    }

    return $buffer;
  }

}
