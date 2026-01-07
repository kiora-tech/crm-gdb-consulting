<?php

declare(strict_types=1);

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class Builder
{
    public function __construct(
        private FactoryInterface $factory,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    public function createMainMenu(): ItemInterface
    {
        $menu = $this->factory->createItem('root');
        $menu->addChild('home', ['route' => 'homepage', 'label' => $this->translator->trans('menu.home')])
            ->setExtra('icon', 'bi bi-house-door')
            ->setExtra('safe_label', true);

        $menu->addChild('outlook_calendar', ['route' => 'app_outlook_calendar_index', 'label' => $this->translator->trans('menu.outlook_calendar')])
            ->setExtra('icon', 'bi bi-calendar-event')
            ->setExtra('safe_label', true);

        $menu->addChild('customers', ['route' => 'app_customer_index', 'label' => $this->translator->trans('menu.customers')])
            ->setExtra('icon', 'bi bi-building')
            ->setExtra('safe_label', true);

        $menu->addChild('document', ['route' => 'app_document_index', 'label' => $this->translator->trans('menu.document')])
            ->setExtra('icon', 'bi bi-file-earmark-text')
            ->setExtra('safe_label', true);

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $menu->addChild('user', ['route' => 'app_user_index', 'label' => $this->translator->trans('menu.user')])
                ->setExtra('icon', 'bi bi-people')
                ->setExtra('safe_label', true);
            $menu->addChild('import', ['route' => 'app_import_index', 'label' => $this->translator->trans('menu.import')])
                ->setExtra('icon', 'bi bi-cloud-upload')
                ->setExtra('safe_label', true);
            $menu->addChild('template', ['route' => 'app_template_index', 'label' => $this->translator->trans('menu.template')])
                ->setExtra('icon', 'bi bi-file-earmark-medical')
                ->setExtra('safe_label', true);
        }

        $menu->addChild('energy_provider', ['route' => 'app_energy_provider_index', 'label' => $this->translator->trans('menu.energy_provider')])
            ->setExtra('icon', 'bi bi-lightning-charge')
            ->setExtra('safe_label', true);

        return $menu;
    }
}
