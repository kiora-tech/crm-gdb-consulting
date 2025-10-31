<?php

declare(strict_types=1);

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Bundle\SecurityBundle\Security;

use function Symfony\Component\Translation\t;

final readonly class Builder
{
    public function __construct(
        private FactoryInterface $factory,
        private Security $security,
    ) {
    }

    public function createMainMenu(): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        $menu->addChild('menu.home', ['route' => 'homepage'])
            ->setLabel((string) t('menu.home'))
            ->setExtra('icon', 'bi bi-house-door')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.outlook_calendar', ['route' => 'app_outlook_calendar_index'])
            ->setLabel((string) t('menu.outlook_calendar'))
            ->setExtra('icon', 'bi bi-calendar-event')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.customers', ['route' => 'app_customer_index'])
            ->setLabel((string) t('menu.customers'))
            ->setExtra('icon', 'bi bi-building')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.contact', ['route' => 'app_contact_index'])
            ->setLabel((string) t('menu.contact'))
            ->setExtra('icon', 'bi bi-person-vcard')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.document', ['route' => 'app_document_index'])
            ->setLabel((string) t('menu.document'))
            ->setExtra('icon', 'bi bi-file-earmark-text')
            ->setExtra('safe_label', true);

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $menu->addChild('menu.user', ['route' => 'app_user_index'])
                ->setLabel((string) t('menu.user'))
                ->setExtra('icon', 'bi bi-people')
                ->setExtra('safe_label', true);
            $menu->addChild('menu.document_type', ['route' => 'app_document_type_index'])
                ->setLabel((string) t('menu.document_type'))
                ->setExtra('icon', 'bi bi-file-earmark-ruled')
                ->setExtra('safe_label', true);
            $menu->addChild('menu.template', ['route' => 'app_template_index'])
                ->setLabel((string) t('menu.template'))
                ->setExtra('icon', 'bi bi-file-earmark-medical')
                ->setExtra('safe_label', true);
        }

        $menu->addChild('menu.energy_provider', ['route' => 'app_energy_provider_index'])
            ->setLabel((string) t('menu.energy_provider'))
            ->setExtra('icon', 'bi bi-lightning-charge')
            ->setExtra('safe_label', true);

        return $menu;
    }
}
