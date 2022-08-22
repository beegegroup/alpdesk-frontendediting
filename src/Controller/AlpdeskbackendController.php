<?php

declare(strict_types=1);

namespace Alpdesk\AlpdeskFrontendediting\Controller;

use Contao\CoreBundle\Controller\AbstractBackendController;
use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Contao\Environment;
use Contao\Input;
use Contao\Controller;
use Contao\BackendUser;
use Contao\UserModel;
use Contao\PageModel;
use Contao\System;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Security;
use Alpdesk\AlpdeskFrontendediting\Utils\Utils;

class AlpdeskbackendController extends AbstractBackendController
{
    protected ContaoFramework $contaoFramework;

    private CsrfTokenManagerInterface $csrfTokenManager;
    private string $csrfTokenName;
    protected RouterInterface $router;
    private Security $security;
    private RequestStack $requestStack;

    public function __construct(
        ContaoFramework           $contaoFramework,
        CsrfTokenManagerInterface $csrfTokenManager,
        string                    $csrfTokenName,
        RouterInterface           $router,
        Security                  $security,
        RequestStack              $requestStack
    )
    {
        $this->contaoFramework = $contaoFramework;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->csrfTokenName = $csrfTokenName;
        $this->router = $router;
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    private function getCurrentSession(): SessionInterface
    {
        return $this->requestStack->getCurrentRequest()->getSession();
    }

    private function toggleFullesize(): void
    {
        $userModel = UserModel::findById(BackendUser::getInstance()->id);
        if ($userModel !== null) {

            $userModel->fullscreen = ($userModel->fullscreen == 1 ? 0 : 1);
            $userModel->save();

        }

        Controller::reload();
    }

    private function toggleLiveModus(): void
    {
        $liveModus = $this->getCurrentSession()->get('alpdeskfee_livemodus');

        if ($liveModus === true) {
            $this->getCurrentSession()->set('alpdeskfee_livemodus', false);
        } else {
            $this->getCurrentSession()->set('alpdeskfee_livemodus', true);
        }

        Controller::reload();
    }

    private function setPageAlias(mixed $id): void
    {
        if ($id !== null && $id !== '') {

            $pageModel = PageModel::findById($id);

            if ($pageModel !== null) {
                $this->getCurrentSession()->set('alpdeskfee_pageselect', $pageModel->id);
            } else {
                $this->getCurrentSession()->set('alpdeskfee_pageselect', '');
            }

        }

        Controller::redirect($this->router->generate('alpdesk_frontendediting_backend'));
    }

    private function generatePreviewUrl(): string
    {
        $url = '/preview.php';

        $id = $this->getCurrentSession()->get('alpdeskfee_pageselect');
        if ($id !== null && $id !== '') {

            $pageId = (int)$id;
            if ($pageId > 0) {

                $pageModel = PageModel::findById((int)$id);

                if ($pageModel !== null) {
                    $url .= '/' . Utils::replaceInsertTags('{{link_url::' . $pageModel->id . '}}');
                }

            }

        }

        return $url;
    }

    /**
     * @return Response
     */
    public function endpoint(): Response
    {
        $this->contaoFramework->initialize();

        $GLOBALS['TL_CSS'][] = 'bundles/alpdeskfrontendediting/css/alpdeskfrontendediting_be.css';

        $backendUser = $this->security->getUser();

        if (!$backendUser instanceof BackendUser) {
            return $this->render('@AlpdeskFrontendediting/alpdeskfee_be_error.html.twig', ['msg' => 'Permission denied']);
        }

        Utils::mergeUserGroupPersmissions($backendUser);

        if (!$backendUser->isAdmin && (int)$backendUser->alpdesk_fee_enabled !== 1) {
            return $this->render('@AlpdeskFrontendediting/alpdeskfee_be_error.html.twig', ['msg' => 'Permission denied']);
        }

        if (Input::post('toggleFullsize')) {
            $this->toggleFullesize();
        } else if (Input::post('toggleLivemodus')) {
            $this->toggleLiveModus();
        } else if (Input::get('pageselect')) {
            $this->setPageAlias(Input::get('pageselect'));
        }

        System::loadLanguageFile('default');

        $liveModus = ($this->getCurrentSession()->get('alpdeskfee_livemodus') === true);
        if (
            $backendUser->isAdmin === true &&
            $backendUser->alpdesk_fee_admin_disabled !== null &&
            $backendUser->alpdesk_fee_admin_disabled === 1
        ) {
            $liveModus = true;
        }

        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/alpdeskfrontendediting/js/alpdeskfrontendediting_be.js';
        $GLOBALS['TL_CSS'][] = 'bundles/alpdeskfrontendediting/css/angular/alpdeskfee-styles.css';

        $elements = [];
        $elementsData = Utils::getAlpdeskFeeElements(BackendUser::getInstance());
        if (\count($elementsData) > 0) {
            $elements = $elementsData;
        }
        $elements = \json_encode($elements);

        return $this->render('@AlpdeskFrontendediting/alpdeskfee_be.html.twig', [
            'token' => $this->csrfTokenManager->getToken($this->csrfTokenName)->getValue(),
            'base' => Environment::get('base'),
            'livemodus' => $liveModus,
            'url' => $this->generatePreviewUrl(),
            'cachingTime' => time(),
            'label_fullscreen' => $GLOBALS['TL_LANG']['alpdeskfee_backend_lables']['fullscreen'],
            'label_livemodus' => $GLOBALS['TL_LANG']['alpdeskfee_backend_lables']['live_mode'],
            'label_pageselect' => $GLOBALS['TL_LANG']['alpdeskfee_backend_lables']['page_select'],
            'elements' => $elements
        ]);

    }

}
