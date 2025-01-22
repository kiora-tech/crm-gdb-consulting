<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Document;
use App\Form\DocumentType;
use App\Form\DropzoneForm;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\UX\Turbo\TurboBundle;

#[Route('/document')]
class DocumentController extends AbstractController
{
    #[Route('/{id}/new', name: 'app_document', methods: ['POST'])]
    public function uploadDocument(
        Request                $request,
        SluggerInterface       $slugger,
        Customer               $customer,
        LoggerInterface        $logger,
        EntityManagerInterface $entityManager,
        #[Autowire('%kernel.project_dir%/public/uploads/documents')]
        string                 $uploadDirectory,
    ): Response
    {
        $document = new Document();
        $form = $this->createForm(DropzoneForm::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $documentFile */
            $documentFile = $form->get('file')->getData();

            if ($documentFile) {
                $originalFilename = pathinfo($documentFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $documentFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    //test if folder $uploadDirectory exist
                    if(!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0777, true);
                    }
                    $documentFile->move($uploadDirectory, $newFilename);

                    $document->setName($originalFilename);
                    $document->setPath($uploadDirectory . '/' . $newFilename);
                    $document->setCustomer($customer);

                    $entityManager->persist($document);
                    $entityManager->flush();
                } catch (FileException $e) {
                    $logger->error('There was an issue with the file upload: ' . $e->getMessage());
                    $this->addFlash('error', 'There was an issue with the file upload. Please try again.');
                }
            }
        }
        $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);

        return $this->render('document/upload_response.html.twig', compact('form', 'customer'));
    }

    #[Route('/{id}/download', name: 'app_document_download', methods: ['GET'])]
    public function downloadDocument(
        Document $document,
        MimeTypesInterface $mimeTypes
    ): Response
    {
        $response = new Response();

        //gettype
        $typeMime = $mimeTypes->guessMimeType($document->getPath());
        $baseName = basename($document->getPath());
        $response->headers->set('Content-Type', $typeMime);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $baseName . '"');
        $response->setContent(file_get_contents($document->getPath()));

        return $response;
    }

    #[Route(name: 'app_document_index', methods: ['GET'])]
    public function index(DocumentRepository $documentRepository): Response
    {
        return $this->render('docu/index.html.twig', [
            'documents' => $documentRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_docu_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $document = new Document();
        $form = $this->createForm(DropzoneForm::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($document);
            $entityManager->flush();

            return $this->redirectToRoute('app_docu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('docu/new.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_docu_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DropzoneForm::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_docu_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('docu/edit.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->getPayload()->getString('_token'))) {

            $path = $document->getPath();
            if (file_exists($path)) {
                unlink($path);
            }
            $customer = $document->getCustomer();
            $entityManager->remove($document);
            $entityManager->flush();

            if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
            }
        }


        return $this->redirectToRoute('app_document_index', [], Response::HTTP_SEE_OTHER);
    }
}