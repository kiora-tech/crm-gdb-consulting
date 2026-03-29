<?php

namespace App\Mcp\Resource;

use App\Entity\EnergyProvider;
use App\Entity\Fta;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Content\TextContent;

class ReferenceResource
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** @return list<TextContent> */
    #[McpResource(
        uri: 'crm://reference/energy-providers',
        name: 'energy-providers',
        description: 'Liste de tous les fournisseurs d\'energie disponibles (EDF, Engie, Total Energies, etc.).',
        mimeType: 'application/json'
    )]
    public function getEnergyProviders(): array
    {
        $providers = $this->entityManager->getRepository(EnergyProvider::class)->findBy([], ['name' => 'ASC']);

        $data = array_map(fn (EnergyProvider $p) => [
            'id' => $p->getId(),
            'nom' => $p->getName(),
        ], $providers);

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return list<TextContent> */
    #[McpResource(
        uri: 'crm://reference/fta',
        name: 'fta-list',
        description: 'Liste des FTA (formules tarifaires d\'acheminement) avec leurs couts et tarifs de reservation de puissance.',
        mimeType: 'application/json'
    )]
    public function getFta(): array
    {
        $ftas = $this->entityManager->getRepository(Fta::class)->findAll();

        $data = array_map(fn (Fta $f) => [
            'id' => $f->getId(),
            'label' => $f->getLabel(),
            'cout_fixe' => $f->getFixedCost(),
        ], $ftas);

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }

    /** @return list<TextContent> */
    #[McpResource(
        uri: 'crm://reference/enums',
        name: 'reference-enums',
        description: 'Liste des valeurs possibles pour les statuts, origines, types d\'energie, segments, etc.',
        mimeType: 'application/json'
    )]
    public function getEnums(): array
    {
        $data = [
            'statuts_client' => array_map(fn ($s) => $s->value, \App\Entity\ProspectStatus::cases()),
            'origines_client' => array_map(fn ($o) => $o->value, \App\Entity\ProspectOrigin::cases()),
            'types_energie' => array_map(fn ($t) => $t->value, \App\Entity\EnergyType::cases()),
            'canaux_signature' => array_map(fn ($c) => $c->value, \App\Entity\CanalSignature::cases()),
            'segments_electricite' => array_map(fn ($s) => $s->value, \App\Entity\Segment::cases()),
            'tarifs_transport_gaz' => array_map(fn ($t) => $t->value, \App\Entity\GasTransportRate::cases()),
        ];

        return [new TextContent(text: json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))];
    }
}
