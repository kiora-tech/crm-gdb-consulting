<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\Customer;
use App\Entity\Energy;
use App\Entity\EnergyType;
use App\Entity\Prospect;
use App\Entity\Contact;
use App\Entity\BusinessEntity;
use App\Entity\ProspectOrigin;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Psr\Log\LoggerInterface;

readonly class ImportService
{
    public function __construct(private EntityManagerInterface $entityManager, private LoggerInterface $logger)
    {
    }

    public function importFromExcel(string $filePath): void
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        foreach ($rows as $index => $row) {
            if ($index === 0) { // Skip header row
                continue;
            }

            if (empty($row[1])) { // Ensure customer information is valid
                $this->logger->error('Customer name is missing', ['row' => $index]);
                continue;
            }
            $customer = $this->getOrCreateCustomer($row[1], $row[5]);
            $customer->setSiret((string) $row[0]);

            // Creating or updating Contact entity
            $existingContact = $this->entityManager->getRepository(Contact::class)
                ->findOneBy(['email' => $row[3]]);

            if (!$existingContact) {
                $contact = new Contact();
                $names = explode(' ',(string)$row[2], 2);

                $contact->setFirstName($names[0]);
                $contact->setLastName($names[1]??'');
                $contact->setEmail((string)$row[3]); // MAIL
                $contact->setPhone((string)$row[4]); // NUMERO
                $contact->setCustomer($customer);
                $this->entityManager->persist($contact);
            }

            if (!empty($row[15]) || !empty($row[14])) {
                // Creating or updating Prospect entity
                $comment = $this->entityManager->getRepository(Comment::class)
                    ->findOneBy(['customer' => $customer]);

                if (!$comment) {
                    $comment = new Comment();
                    $comment->setCustomer($customer);
                    $comment->setNote($row[15] === '' ? null : $row[15]); // COMMENTAIRES
                    $this->entityManager->persist($comment);
                } else {
                    $comment->setNote($row[15] === '' ? null : $row[15]);
                }
            }

            // Creating or updating Energy entity if applicable
            if ($row[9]) { // Check if PDL / PCE is provided
                $existingEnergy = $this->entityManager->getRepository(Energy::class)
                    ->findOneBy(['code' => $row[9], 'customer' => $customer]);

                if (!$existingEnergy) {
                    $energy = new Energy();
                    if ($row[6]) {
                        $energy->setProvider($row[6]); // FOURNISSEUR
                    }
                    if (!empty($row[7])) {
                        $date = \DateTime::createFromFormat('d/m/Y', $row[7]);
                        $energy->setContractEnd($date); // ECHEANCE
                    }
                    $energy->setType(EnergyType::tryFrom($row[8]) ?? EnergyType::ELEC); // ELEC / GAZ
                    if($row[9]) {
                        $energy->setCode((int)$row[9]); // PDL / PCE
                    }
                    $energy->setCustomer($customer);
                    $this->entityManager->persist($energy);
                } else {
                    if ($row[6]) {
                        $existingEnergy->setProvider($row[6]);
                    }
                    if (!empty($row[7])) {
                        $date = \DateTime::createFromFormat('d/m/Y', $row[7]);
                        $existingEnergy->setContractEnd($date);
                    }
                    $existingEnergy->setType($row[8]);
                }
            }
        }

        // Flush to save all entities
        $this->entityManager->flush();
    }


    private function getOrCreateCustomer(string $name, string $leadOrigin): Customer
    {
        $customer = $this->entityManager->getRepository(Customer::class)
            ->findOneBy(['name' => $name]);

        if (!$customer) {
            $customer = new Customer();
            $customer->setName($name);
            $customer->setLeadOrigin($leadOrigin);
            $customer->setOrigin(ProspectOrigin::LISTING);
            $this->entityManager->persist($customer);
            $this->entityManager->flush();
        }

        return $customer;
    }
}
