<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class TemplateVariablesHelper
{
    /**
     * @return array<string, array<int, array<string, string>>>
     */
    public function getVariables(): array
    {
        return [
            'Date et Heure' => [
                ['var' => '${date.today}', 'desc' => 'Date du jour (format JJ/MM/AAAA)'],
                ['var' => '${date.todayLong}', 'desc' => 'Date du jour en format long (ex: 26 juin 2025)'],
                ['var' => '${time.now}', 'desc' => 'Heure actuelle (format HH:MM)'],
                ['var' => '${date.dayName}', 'desc' => 'Nom du jour en français (ex: Jeudi)'],
                ['var' => '${date.month}', 'desc' => 'Nom du mois en français (ex: Juin)'],
                ['var' => '${date.year}', 'desc' => 'Année en cours (ex: 2025)'],
            ],
            'Utilisateur connecté' => [
                ['var' => '${user.name}', 'desc' => 'Nom complet de l\'utilisateur'],
                ['var' => '${user.firstName}', 'desc' => 'Prénom de l\'utilisateur'],
                ['var' => '${user.lastName}', 'desc' => 'Nom de l\'utilisateur'],
                ['var' => '${user.email}', 'desc' => 'Email professionnel'],
                ['var' => '${user.phone}', 'desc' => 'Téléphone professionnel'],
                ['var' => '${user.title}', 'desc' => 'Fonction/titre (ex: Conseiller énergie)'],
                ['var' => '${user.signature}', 'desc' => 'Bloc signature complet'],
            ],
            'Client' => [
                ['var' => '${customer.name}', 'desc' => 'Nom du client'],
                ['var' => '${customer.siret}', 'desc' => 'Numéro SIRET'],
                ['var' => '${customer.legalForm}', 'desc' => 'Forme juridique (SARL, SAS, etc.)'],
                ['var' => '${customer.leadOrigin}', 'desc' => 'Origine du lead'],
                ['var' => '${customer.status}', 'desc' => 'Statut du prospect'],
                ['var' => '${customer.companyGroup}', 'desc' => 'Groupe d\'entreprises'],
                ['var' => '${customer.worth}', 'desc' => 'Valeur du contrat'],
                ['var' => '${customer.margin}', 'desc' => 'Marge'],
                ['var' => '${customer.action}', 'desc' => 'Action commerciale'],
            ],
            'Adresse Client' => [
                ['var' => '${customer.addressFull}', 'desc' => 'Adresse complète sur une ligne'],
                ['var' => '${customer.addressMultiline}', 'desc' => 'Adresse sur plusieurs lignes'],
                ['var' => '${customer.addressNumber}', 'desc' => 'Numéro de rue'],
                ['var' => '${customer.addressStreet}', 'desc' => 'Nom de la rue'],
                ['var' => '${customer.addressPostalCode}', 'desc' => 'Code postal'],
                ['var' => '${customer.addressCity}', 'desc' => 'Ville'],
            ],
            'Contact Principal' => [
                ['var' => '${customer.primaryContact.firstName}', 'desc' => 'Prénom du contact principal'],
                ['var' => '${customer.primaryContact.lastName}', 'desc' => 'Nom du contact principal'],
                ['var' => '${customer.primaryContact.email}', 'desc' => 'Email du contact principal'],
                ['var' => '${customer.primaryContact.phone}', 'desc' => 'Téléphone du contact principal'],
                ['var' => '${customer.primaryContact.mobilePhone}', 'desc' => 'Mobile du contact principal'],
                ['var' => '${customer.primaryContact.position}', 'desc' => 'Fonction du contact principal'],
                ['var' => '${customer.primaryContact.addressFull}', 'desc' => 'Adresse complète du contact'],
                ['var' => '${customer.primaryContact.addressNumber}', 'desc' => 'Numéro de rue du contact'],
                ['var' => '${customer.primaryContact.addressStreet}', 'desc' => 'Rue du contact'],
                ['var' => '${customer.primaryContact.addressPostalCode}', 'desc' => 'Code postal du contact'],
                ['var' => '${customer.primaryContact.addressCity}', 'desc' => 'Ville du contact'],
            ],
            'Tous les Contacts' => [
                ['var' => '${customer.contacts[0].firstName}', 'desc' => 'Prénom du contact'],
                ['var' => '${customer.contacts[0].lastName}', 'desc' => 'Nom du contact'],
                ['var' => '${customer.contacts[0].email}', 'desc' => 'Email du contact'],
                ['var' => '${customer.contacts[0].phone}', 'desc' => 'Téléphone du contact'],
                ['var' => '${customer.contacts[0].mobilePhone}', 'desc' => 'Mobile du contact'],
                ['var' => '${customer.contacts[0].position}', 'desc' => 'Fonction du contact'],
                ['var' => '${customer.contacts[0].isPrimary}', 'desc' => 'Est le contact principal (Oui/Non)'],
            ],
            'Toutes les Énergies' => [
                ['var' => '${customer.energies[0].type}', 'desc' => 'Type d\'énergie (ELEC/GAZ)'],
                ['var' => '${customer.energies[0].code}', 'desc' => 'Code PDL/PCE'],
                ['var' => '${customer.energies[0].energyProvider.name}', 'desc' => 'Nom du fournisseur'],
                ['var' => '${customer.energies[0].contractEnd}', 'desc' => 'Date de fin de contrat'],
                ['var' => '${customer.energies[0].powerKva}', 'desc' => 'Puissance en kVA (électricité)'],
                ['var' => '${customer.energies[0].totalConsumption}', 'desc' => 'Consommation totale (gaz)'],
                ['var' => '${customer.energies[0].hpConsumption}', 'desc' => 'Consommation HP (électricité)'],
                ['var' => '${customer.energies[0].hcConsumption}', 'desc' => 'Consommation HC (électricité)'],
            ],
            'Énergies Électriques' => [
                ['var' => '${customer.energiesElec[0].code}', 'desc' => 'Code PDL (électricité uniquement)'],
                ['var' => '${customer.energiesElec[0].energyProvider.name}', 'desc' => 'Fournisseur électricité'],
                ['var' => '${customer.energiesElec[0].contractEnd}', 'desc' => 'Date fin contrat électricité'],
                ['var' => '${customer.energiesElec[0].powerKva}', 'desc' => 'Puissance en kVA'],
                ['var' => '${customer.energiesElec[0].hpConsumption}', 'desc' => 'Consommation HP'],
                ['var' => '${customer.energiesElec[0].hcConsumption}', 'desc' => 'Consommation HC'],
            ],
            'Énergies Gaz' => [
                ['var' => '${customer.energiesGaz[0].code}', 'desc' => 'Code PCE (gaz uniquement)'],
                ['var' => '${customer.energiesGaz[0].energyProvider.name}', 'desc' => 'Fournisseur gaz'],
                ['var' => '${customer.energiesGaz[0].contractEnd}', 'desc' => 'Date fin contrat gaz'],
                ['var' => '${customer.energiesGaz[0].totalConsumption}', 'desc' => 'Consommation totale gaz'],
            ],
            'Contrats Futurs (toutes énergies)' => [
                ['var' => '${customer.energiesFuture[0].type}', 'desc' => 'Type (contrats non expirés)'],
                ['var' => '${customer.energiesFuture[0].code}', 'desc' => 'Code PDL/PCE (contrats futurs)'],
                ['var' => '${customer.energiesFuture[0].energyProvider.name}', 'desc' => 'Fournisseur (contrats futurs)'],
                ['var' => '${customer.energiesFuture[0].contractEnd}', 'desc' => 'Date fin (contrats futurs)'],
            ],
            'Contrats Futurs Électricité' => [
                ['var' => '${customer.energiesFutureElec[0].code}', 'desc' => 'Code PDL (contrats élec non expirés)'],
                ['var' => '${customer.energiesFutureElec[0].energyProvider.name}', 'desc' => 'Fournisseur élec (futurs)'],
                ['var' => '${customer.energiesFutureElec[0].contractEnd}', 'desc' => 'Date fin contrat élec (futurs)'],
                ['var' => '${customer.energiesFutureElec[0].powerKva}', 'desc' => 'Puissance kVA (futurs)'],
            ],
            'Contrats Futurs Gaz' => [
                ['var' => '${customer.energiesFutureGaz[0].code}', 'desc' => 'Code PCE (contrats gaz non expirés)'],
                ['var' => '${customer.energiesFutureGaz[0].energyProvider.name}', 'desc' => 'Fournisseur gaz (futurs)'],
                ['var' => '${customer.energiesFutureGaz[0].contractEnd}', 'desc' => 'Date fin contrat gaz (futurs)'],
                ['var' => '${customer.energiesFutureGaz[0].totalConsumption}', 'desc' => 'Consommation gaz (futurs)'],
            ],
            'Commentaires' => [
                ['var' => '${customer.comments[0].note}', 'desc' => 'Texte du commentaire'],
                ['var' => '${customer.comments[0].createdAt}', 'desc' => 'Date du commentaire'],
            ],
        ];
    }
}
