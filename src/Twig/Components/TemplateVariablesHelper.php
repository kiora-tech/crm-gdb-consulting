<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class TemplateVariablesHelper
{
    public function getVariables(): array
    {
        return [
            'Client' => [
                ['var' => '${name}', 'desc' => 'Nom du client'],
                ['var' => '${siret}', 'desc' => 'Numéro SIRET'],
                ['var' => '${leadOrigin}', 'desc' => 'Origine du lead'],
                ['var' => '${status}', 'desc' => 'Statut du prospect'],
                ['var' => '${companyGroup}', 'desc' => 'Groupe d\'entreprises'],
                ['var' => '${worth}', 'desc' => 'Valeur du contrat'],
                ['var' => '${margin}', 'desc' => 'Marge'],
            ],
            'Contacts' => [
                ['var' => '${contacts[0].firstName}', 'desc' => 'Prénom du contact'],
                ['var' => '${contacts[0].lastName}', 'desc' => 'Nom du contact'],
                ['var' => '${contacts[0].email}', 'desc' => 'Email du contact'],
                ['var' => '${contacts[0].phone}', 'desc' => 'Téléphone du contact'],
                ['var' => '${contacts[0].position}', 'desc' => 'Fonction du contact'],
            ],
            'Énergies' => [
                ['var' => '${energies[0].type}', 'desc' => 'Type d\'énergie'],
                ['var' => '${energies[0].code}', 'desc' => 'Code PDL/PCE'],
                ['var' => '${energies[0].provider}', 'desc' => 'Fournisseur'],
                ['var' => '${energies[0].contractEnd}', 'desc' => 'Date de fin de contrat'],
                ['var' => '${energies[0].powerKva}', 'desc' => 'Puissance en kVA (électricité)'],
                ['var' => '${energies[0].totalConsumption}', 'desc' => 'Consommation totale'],
            ],
            'Commentaires' => [
                ['var' => '${comments[0].note}', 'desc' => 'Texte du commentaire'],
                ['var' => '${comments[0].createdAt}', 'desc' => 'Date du commentaire'],
            ]
        ];
    }
}
