<?php

declare(strict_types=1);

namespace SitePortal\Controllers;

use SitePortal\Core\Config;
use SitePortal\Core\I18n;
use SitePortal\Core\Request;
use SitePortal\Core\Response;
use SitePortal\Core\View;

final class HomeController
{
    private Config $config;
    private I18n $i18n;
    private View $view;

    public function __construct(?Config $config = null, ?I18n $i18n = null, ?View $view = null)
    {
        $this->config = $config ?? Config::getInstance();
        $this->i18n = $i18n ?? I18n::getInstance();
        $this->view = $view ?? new View();
    }

    public function index(Request $request): Response
    {
        $locale = $request->getLocale();
        $this->i18n->setLocale($locale);

        $data = [
            'app' => (string) $this->config->get('app.name', 'SitePortal'),
            'version' => (string) $this->config->get('app.version', '0.1.0'),
            'env' => (string) $this->config->get('app.env', 'development'),
            'requestId' => $request->getRequestId(),
            'locale' => $locale,
            'supportedLocales' => $this->i18n->getSupportedLocales(),
            'i18n' => $this->i18n,
            'title' => $this->i18n->t('home.title', [], $locale),
            'lead' => $this->i18n->t('home.lead', [], $locale),
        ];

        $html = $this->view->renderWithLayout('home', $data);
        return Response::html($html, 200);
    }
}
