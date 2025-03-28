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
            ->setLabel((string)t('menu.home'))
            ->setExtra('icon', 'bi bi-grid')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.customers', ['route' => 'app_customer_index'])
            ->setLabel((string)t('menu.customers'))
            ->setExtra('icon', 'bi bi-person')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.contact', ['route' => 'app_contact_index'])
            ->setLabel((string)t('menu.contact'))
            ->setExtra('icon', 'bi bi-person-lines-fill')
            ->setExtra('safe_label', true);

        $menu->addChild('menu.document', ['route' => 'app_document_index'])
            ->setLabel((string)t('menu.document'))
            ->setExtra('icon', 'bi bi-file-earmark-text')
            ->setExtra('safe_label', true);

//        $menu->addChild('menu.document_signature', ['route' => 'app_document_signature_index'])
//            ->setLabel((string)t('menu.document_signature'))
//            ->setExtra('icon', 'bi bi-file-earmark-text')
//            ->setExtra('safe_label', true);

        if ($this->security->isGranted('ROLE_ADMIN')) {
            $menu->addChild('menu.user', ['route' => 'app_user_index'])
                ->setLabel((string)t('menu.user'))
                ->setExtra('icon', 'bi bi-door-open')
                ->setExtra('safe_label', true);
            $menu->addChild('menu.document_type', ['route' => 'app_document_type_index'])
                ->setLabel((string)t('menu.document_type'))
                ->setExtra('icon', 'bi bi-file')
                ->setExtra('safe_label', true);
            $menu->addChild('menu.template', ['route' => 'app_template_index'])
                ->setLabel((string)t('menu.template'))
                ->setExtra('icon', 'bi bi-file-earmark-text')
                ->setExtra('safe_label', true);
        }
        return $menu;
    }
}
