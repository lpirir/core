<?php

/*
 * This file is part of the Zikula package.
 *
 * Copyright Zikula Foundation - http://zikula.org/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Zikula\Bundle\CoreInstallerBundle\Stage\Install;

use Zikula\Common\Translator\TranslatorTrait;
use Zikula\Component\Wizard\InjectContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Zikula\Component\Wizard\StageInterface;
use Zikula\Component\Wizard\WizardCompleteInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Swift_Mailer;
use Symfony\Component\Routing\RouterInterface;

class CompleteStage implements StageInterface, WizardCompleteInterface, InjectContainerInterface
{
    use TranslatorTrait;

    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->setTranslator($container->get('translator.default'));
    }

    public function setTranslator($translator)
    {
        $this->translator = $translator;
    }

    public function getName()
    {
        return 'complete';
    }

    public function getTemplateName()
    {
        return '';
    }

    public function isNecessary()
    {
        return true;
    }

    public function getTemplateParams()
    {
        return [];
    }

    public function getResponse(Request $request)
    {
        $router = $this->container->get('router');
        if ($this->sendEmailToAdmin($request)) {
            $request->getSession()->getFlashBag()->add('success', $this->__('Congratulations! Zikula has been successfully installed.'));
            $request->getSession()->getFlashBag()->add('info', $this->__f(
                'Session are currently configured to use the filesystem. It is recommended that you change this to use the database. Click %here% to configure.',
                ['%here%' => '<a href="' . $router->generate('zikulasecuritycentermodule_config_config') . '">' . $this->__('Security Center') . '</a>']
            ));

            return new RedirectResponse($router->generate('zikulaadminmodule_admin_adminpanel', [], RouterInterface::ABSOLUTE_URL));
        } else {
            $request->getSession()->getFlashBag()->add('warning', $this->__('Email settings are not yet configured. Please configure them below.'));

            return new RedirectResponse($router->generate('zikulamailermodule_config_config', [], RouterInterface::ABSOLUTE_URL));
        }
    }

    private function sendEmailToAdmin(Request $request)
    {
        $adminUser = $this->container->get('zikula_users_module.user_repository')->find(2);
        $uName = $adminUser->getUname();
        $url = $request->getSchemeAndHttpHost() . $request->getBasePath();

        $body = <<<EOF
<html>
<head></head>
<body>
<h1>Hi $uName!</h1>
<p>Zikula has been successfully installed at <a href="$url">$url</a>. If you have further questions,
visit <a href="http://zikula.org">zikula.org</a></p>
</body>
EOF;
        $message = \Swift_Message::newInstance()
            ->setSubject($this->__('Zikula installation completed!'))
            ->setFrom($adminUser->getEmail())
            ->setTo($adminUser->getEmail())
            ->setBody($body)
            ->setContentType('text/html')
        ;
        /**
         * @var Swift_Mailer
         */
        $mailer = $this->container->get('mailer');

        return $mailer->send($message);
    }
}
