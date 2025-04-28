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
    ) {}

    public function createMainMenu(): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        $menu->addChild('menu.home', ['route' => 'homepage'])
            ->setLabel((string)t('menu.home'))
            ->setExtra('icon', 'bi bi-house-door')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.customers', ['route' => 'app_customer_index'])
            ->setLabel((string)t('menu.customers'))
            ->setExtra('icon', 'bi bi-building')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.contact', ['route' => 'app_contact_index'])
            ->setLabel((string)t('menu.contact'))
            ->setExtra('icon', 'bi bi-person-vcard')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.project', ['route' => 'app_project_index'])
            ->setLabel((string)t('menu.project'))
            ->setExtra('icon', 'bi bi-person-vcard')
            ->setExtra('safe_label', true);

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $menu->addChild('menu.user', ['route' => 'app_user_index'])
                ->setLabel((string)t('menu.user'))
                ->setExtra('icon', 'bi bi-people')
                ->setExtra('safe_label', true);
        }
        return $menu;
    }
}
