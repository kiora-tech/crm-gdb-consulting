<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Document;
use App\Form\DocumentType;
use App\Form\DropzoneForm;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
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

#[Route('/document', name: 'app_document')]
class DocumentController extends CustomerInfoController
{
    #[Route('/{id}/new', name: '_new', methods: ['POST'])]
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
                    $documentFile->move($uploadDirectory, $newFilename);

                    $document->setName($originalFilename);
                    $document->setPath($uploadDirectory . '/' . $newFilename);
                    $document->setCustomer($customer);

                    $entityManager->persist($document);
                    $entityManager->flush();

                    return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()]);
                } catch (FileException $e) {
                    $logger->error('There was an issue with the file upload: ' . $e->getMessage());
                    $this->addFlash('error', 'There was an issue with the file upload. Please try again.');
                }
            }
        }


        return $this->render('document/upload_response.html.twig', compact('form', 'customer'));
    }

    #[Route('/{id}/download', name: '_download', methods: ['GET'])]
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

    #[Route('/{id}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ?Customer $customer = null): Response
    {
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            $document = $this->getRepository()->find($id);
            $path = $document->getPath();
            if (file_exists($path)) {
                unlink($path);
            }
            $customer = $document->getCustomer();
            $this->entityManager->remove($document);
            $this->entityManager->flush();

            if (TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->redirectToRoute('app_document_index', [], Response::HTTP_SEE_OTHER);
    }

    protected function getEntityClass(): string
    {
        return Document::class;
    }

    protected function getFormTypeClass(): string
    {
        return DropzoneForm::class;
    }

    protected function customizeQueryBuilder(QueryBuilder $qb): void
    {
        $qb->leftJoin('e.customer', 'c')
            ->leftJoin('e.type', 't');
    }
}