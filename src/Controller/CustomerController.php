<?php

namespace App\Controller;

use App\Data\CustomerSearchData;
use App\Entity\Customer;
use App\Entity\Document;
use App\Entity\ProspectStatus;
use App\Form\CustomerSearchType;
use App\Form\CustomerType;
use App\Form\DropzoneForm;
use App\Repository\CustomerRepository;
use App\Service\ImportService;
use App\Service\PaginationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/customer')]
class CustomerController extends AbstractController
{
    #[Route('/', name: 'app_customer_index', methods: ['GET'])]
    public function index(CustomerRepository $customerRepository, PaginationService $paginationService, Request $request): Response
    {
        $data = new CustomerSearchData();
        $form = $this->createForm(CustomerSearchType::class);
        $form->handleRequest($request);

        $query = $customerRepository->search($data);

        $customers = $paginationService->paginate($query, $request);

        return $this->render('customer/index.html.twig', [
            'customers' => $customers,
            'form' => $form,
        ]);
    }

    #[Route('/customer/{id}/status/{status}', name: 'app_customer_status')]
    public function updateStatus(Customer $customer, string $status, EntityManagerInterface $entityManager): Response
    {
        $newStatus = ProspectStatus::from($status);
        $customer->setStatus($newStatus);
        $entityManager->flush();

        return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
    }
    #[Route('/new', name: 'app_customer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $customer = new Customer();
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($customer);
            $entityManager->flush();

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/new.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/upload', name: 'app_customer_upload', methods: ['GET', 'POST'])]
    public function upload(Request $request, ImportService $importService): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile $file */
            $file = $request->files->get('file');

            if ($file && $file->isValid()) {
                $filePath = $file->getPathname();
                $importService->importFromExcel($filePath);

                $this->addFlash('success', 'File uploaded and data imported successfully.');

                return $this->redirectToRoute('app_customer_index');
            }

            $this->addFlash('error', 'There was an issue with the file upload. Please try again.');
        }

        return $this->render('customer/upload.html.twig');
    }

    #[Route('/{id}', name: 'app_customer_show', methods: ['GET'])]
    public function show(Customer $customer): Response
    {
        $document = new Document();
        $formDocument = $this->createForm(DropzoneForm::class, $document);

        return $this->render('customer/show.html.twig', [
            'customer' => $customer,
            'formDocument' => $formDocument->createView(),
        ]);
    }


    #[Route('/{id}/edit', name: 'app_customer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CustomerType::class, $customer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('customer/edit.html.twig', [
            'customer' => $customer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_customer_delete', methods: ['POST'])]
    public function delete(Request $request, Customer $customer, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $customer->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($customer);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_customer_index', [], Response::HTTP_SEE_OTHER);
    }
}
