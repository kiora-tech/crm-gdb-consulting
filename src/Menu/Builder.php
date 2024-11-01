<?php

declare(strict_types=1);

namespace App\Menu;

use App\Entity\Company;
use App\Entity\User;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;

use function Symfony\Component\Translation\t;

final readonly class Builder
{
    public function __construct(
        private FactoryInterface $factory,
        private Security $security,
        #[Autowire(service: 'security.impersonate_url_generator', lazy: true)]
        private ImpersonateUrlGenerator $impersonateUrlGenerator,
        #[Autowire(lazy: true)]
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    private function getCompany(): Company
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User is not valid.');
        }

        return $user->getCompany()
            ?? throw new \LogicException('Company is not valid.');
    }

    public function createMainMenu(): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        $menu->addChild('menu.home', ['route' => 'homepage'])
            ->setLabel((string) t('menu.home'))
            ->setExtra('icon', 'bi bi-grid')
            ->setExtra('safe_label', true);

            $menu->addChild('menu.companies', ['route' => 'app_company_index'])
                ->setLabel((string) t('menu.companies'))
                ->setExtra('icon', 'bi bi-journal-text')
                ->setExtra('safe_label', true);
        
    #   $menu->addChild('menu.client', ['route' => 'app_client_index'])
    #       ->setLabel((string) t('menu.client'))
    #       ->setExtra('icon', 'bi bi-journal-text')
    #       ->setExtra('safe_label', true);

    #       $menu->addChild('menu.invite', ['route' => 'invite_prospect'])
    #           ->setLabel((string) t('menu.invite'))
    #           ->setExtra('icon', 'bi bi-door-open')
    #           ->setExtra('safe_label', true);
    #   
    #   $menu->addChild('menu.template', ['route' => 'app_template_index'])
    #       ->setLabel((string) t('menu.template'))
    #       ->setExtra('icon', 'bi bi-file-plus')
    #       ->setExtra('safe_label', true);
    #   $menu->addChild('menu.client_document', ['route' => 'app_client_document_index'])
    #       ->setLabel((string) t('menu.client_document'))
    #       ->setExtra('icon', 'bi bi-file-pdf')
    #       ->setExtra('safe_label', true);
    #   $menu->addChild('menu.document_signature', ['route' => 'app_document_signature_index'])
    #       ->setLabel((string) t('menu.document_signature'))
    #       ->setExtra('icon', 'bi bi-files')
    #       ->setExtra('safe_label', true);

        return $menu;
    }
}
