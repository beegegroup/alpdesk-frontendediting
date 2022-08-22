<?php

declare(strict_types=1);

namespace Alpdesk\AlpdeskFrontendediting\Utils;

use Contao\ArticleModel;
use Contao\BackendUser;
use Contao\Config;
use Contao\PageModel;
use Contao\Database;
use Contao\Date;
use Contao\StringUtil;
use Contao\System;
use Symfony\Component\Security\Core\User\UserInterface;

class Utils
{
    const CAN_EDIT_PAGE = 1;
    const CAN_DELETE_PAGE = 3;
    const CAN_EDIT_ARTICLES = 4;
    const CAN_DELETE_ARTICLES = 6;

    public static function hasPageMountAccess(PageModel $objPage, UserInterface $backendUser): bool
    {
        if (!$backendUser instanceof BackendUser) {
            return false;
        }

        if ($backendUser->isAdmin || $backendUser->hasAccess($objPage->id, 'pagemounts')) {
            return true;
        }

        $check = false;

        // Bad but fo not want to override PageModel-Reference from Hook
        $objParentPage = PageModel::findById($objPage->id);
        $pid = $objPage->pid;

        while ($objParentPage !== null && $check === false && $pid > 0) {

            $pid = $objParentPage->pid;
            $check = $backendUser->hasAccess($objParentPage->id, 'pagemounts');
            if ($check === false) {
                $objParentPage = PageModel::findById($pid);
            }

        }

        return $check;
    }

    // @TODO https://github.com/contao/contao/issues/5138 -> implementation of old method (polyfill) till a solution is found
    public static function isAllowed(int $int, mixed $row, UserInterface $backendUser): bool
    {
        if (!$backendUser instanceof BackendUser) {
            return false;
        }

        if ($backendUser->isAdmin) {
            return true;
        }

        // Inherit CHMOD settings
        if (!$row['includeChmod']) {
            $pid = $row['pid'];

            $row['chmod'] = false;
            $row['cuser'] = false;
            $row['cgroup'] = false;

            $objParentPage = PageModel::findById($pid);

            while ($objParentPage !== null && $row['chmod'] === false && $pid > 0) {
                $pid = $objParentPage->pid;

                $row['chmod'] = $objParentPage->includeChmod ? $objParentPage->chmod : false;
                $row['cuser'] = $objParentPage->includeChmod ? $objParentPage->cuser : false;
                $row['cgroup'] = $objParentPage->includeChmod ? $objParentPage->cgroup : false;

                $objParentPage = PageModel::findById($pid);
            }

            // Set default values
            if ($row['chmod'] === false) {
                $row['chmod'] = Config::get('defaultChmod');
            }

            if ($row['cuser'] === false) {
                $row['cuser'] = (int)Config::get('defaultUser');
            }

            if ($row['cgroup'] === false) {
                $row['cgroup'] = (int)Config::get('defaultGroup');
            }
        }

        // Set permissions
        $chmod = StringUtil::deserialize($row['chmod']);
        $chmod = \is_array($chmod) ? $chmod : array($chmod);
        $permission = array('w' . $int);

        if (\in_array($row['cgroup'], $backendUser->groups)) {
            $permission[] = 'g' . $int;
        }

        if ($row['cuser'] == $backendUser->id) {
            $permission[] = 'u' . $int;
        }

        return \count(array_intersect($permission, $chmod)) > 0;
    }

    public static function mergeUserGroupPersmissions(UserInterface $backendUser): void
    {
        if ($backendUser instanceof BackendUser) {

            if ($backendUser->inherit === 'group' || $backendUser->inherit === 'extend') {

                $time = Date::floorToMinute();

                foreach ((array)$backendUser->groups as $id) {

                    $objGroup = Database::getInstance()->prepare("SELECT alpdesk_fee_enabled,alpdesk_fee_elements FROM tl_user_group WHERE id=? AND disable!='1' AND (start='' OR start<='$time') AND (stop='' OR stop>'$time')")->limit(1)->execute($id);
                    if ($objGroup->numRows > 0) {

                        if ((int)$backendUser->alpdesk_fee_enabled === 0) {
                            $backendUser->alpdesk_fee_enabled = $objGroup->alpdesk_fee_enabled;
                        }

                        $value = StringUtil::deserialize($objGroup->alpdesk_fee_elements, true);
                        if (\is_array($value) && \count($value) > 0) {

                            if ($backendUser->alpdesk_fee_elements === null) {
                                $backendUser->alpdesk_fee_elements = $value;
                            } else {
                                $backendUser->alpdesk_fee_elements = \array_merge($backendUser->alpdesk_fee_elements, $value);
                            }

                            $backendUser->alpdesk_fee_elements = \array_unique($backendUser->alpdesk_fee_elements);

                        }

                    }

                }

            }

        }

    }

    public static function getAlpdeskFeeElements(BackendUser $user): array
    {
        $validElements = [];

        System::loadLanguageFile('default');
        System::loadLanguageFile('modules');

        $elements = $GLOBALS['TL_CTE'];

        $languagesCTE = $GLOBALS['TL_LANG']['CTE'];
        $languagesMOD = $GLOBALS['TL_LANG']['MOD'];

        if ($elements !== null && \count($elements) > 0) {

            foreach ($elements as $elementGroupKey => $elementGroup) {

                if (\is_array($elementGroup) && \count($elementGroup) > 0) {

                    foreach ($elementGroup as $key => $item) {

                        if ($user->isAdmin || $user->hasAccess($key, 'alpdesk_fee_elements')) {

                            $labelGroup = (string)$elementGroupKey;
                            if (\array_key_exists($labelGroup, $languagesCTE)) {
                                $labelGroup = (string)$languagesCTE[$labelGroup];
                            } else if (\array_key_exists($labelGroup, $languagesMOD)) {
                                $labelGroup = (string)$languagesMOD[$labelGroup];
                            }

                            if (!\array_key_exists($labelGroup, $validElements)) {
                                $validElements[$labelGroup] = [];
                            }

                            $labelItem = $key;
                            if (\array_key_exists($labelItem, $languagesCTE)) {

                                if (\is_array($languagesCTE[$labelItem]) && \count($languagesCTE[$labelItem]) > 0) {
                                    $labelItem = (string)$languagesCTE[$labelItem][0];
                                } else if (\is_string($languagesCTE[$labelItem]) && $languagesCTE[$labelItem] !== '') {
                                    $labelItem = $languagesCTE[$labelItem];
                                }

                            } else if (\array_key_exists($labelItem, $languagesMOD)) {

                                if (\is_array($languagesMOD[$labelItem]) && \count($languagesMOD[$labelItem]) > 0) {
                                    $labelItem = (string)$languagesMOD[$labelItem][0];
                                } else if (\is_string($languagesMOD[$labelItem]) && $languagesMOD[$labelItem] !== '') {
                                    $labelItem = $languagesMOD[$labelItem];
                                }

                            }

                            $validElements[$labelGroup][] = [
                                'key' => $key,
                                'label' => $labelItem
                            ];
                        }

                    }

                }

            }

        }

        return $validElements;

    }

    /**
     * @param int|null $articleId
     * @param array|null $currentRow
     * @return array|null
     */
    public static function mergeArticlePermissions(?int $articleId, ?array $currentRow): ?array
    {
        if ($currentRow === null) {

            if ($articleId === null) {
                return null;
            }

            $parentArticleModel = ArticleModel::findById($articleId);
            if ($parentArticleModel === null) {
                return null;
            }

            $currentRow = $parentArticleModel->row();

        }

        $parentPage = PageModel::findById((int)$currentRow['pid']);
        if ($parentPage === null) {
            return null;
        }

        $currentRow['includeChmod'] = $parentPage->includeChmod;
        $currentRow['chmod'] = $parentPage->chmod;
        $currentRow['cuser'] = $parentPage->cuser;
        $currentRow['cgroup'] = $parentPage->cgroup;

        return $currentRow;

    }

    /**
     * @param string $strBuffer
     * @param bool $blnCache
     * @return string
     */
    public static function replaceInsertTags(string $strBuffer, bool $blnCache = true): string
    {
        try {

            $parser = System::getContainer()->get('contao.insert_tag.parser');

            if ($blnCache) {
                return $parser->replace($strBuffer);
            }

            return $parser->replaceInline($strBuffer);

        } catch (\Exception $ex) {
            return $strBuffer;
        }

    }

}
