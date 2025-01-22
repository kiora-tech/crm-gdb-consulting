<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Document;
use App\Entity\ClientSigningDocument;
use App\Entity\User;
use App\Message\AskSignatureMessage;
use App\Repository\ClientSigningDocumentRepository;
use App\Security\ClientVoterTrait;
use App\Service\PaginationService;
use App\Service\Yousign\YousignApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/document/signature', name: 'app_document_signature_')]
class DocumentSignatureController extends AbstractController
{
    use ClientVoterTrait;

    /**
     * @throws ExceptionInterface
     */
    #[Route('/new/{id}', name: 'ask', methods: ['GET'])]
    public function ask(
        Document $clientDocument,
        MessageBusInterface $bus,
        TranslatorInterface $tr
    ): Response {

        $id = $clientDocument->getId();

        if (null === $id) {
            throw $this->createNotFoundException('Document not found');
        }

        $bus->dispatch(new AskSignatureMessage($id));

        $this->addFlash('success', $tr->trans('document_signature.asked.success_message'));

        return $this->redirectToRoute('app_client_document_show', ['id' => $id]);
    }

    /**
     * @param PaginationService<int, mixed> $paginationService
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(ClientSigningDocumentRepository $repository, PaginationService $paginationService, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('The user is not valid.');
        }

        $query =
            $user->isAdmin()
            ? $repository->findByCompany(
                $user->getCompany()
                            ?? throw new \LogicException('The company is not valid.'))
            : $repository->findByUser($user);

        $documents = $paginationService->paginate($query, $request);

        return $this->render('document_signature/index.html.twig', [
            'documents' => $documents,
        ]);
    }

    #[Route('/{id}', name: 'document_status', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function documentStatus(ClientSigningDocument $clientSigningDocument): Response
    {
        return $this->render('document_signature/document_status.html.twig', [
            'document' => $clientSigningDocument,
        ]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/{id}/cancel', name: 'document_cancel', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function documentCancel(
        ClientSigningDocument $clientSigningDocument,
        Request $request,
        YousignApiService $yousignApiService,
        TranslatorInterface $tr,
    ): Response {

        if ($this->isCsrfTokenValid('cancel-'.$clientSigningDocument->getId(), (string) $request->request->get('_token'))) {
            $result = $yousignApiService->cancelSignature($clientSigningDocument);

            if ($result) {
                $this->addFlash('success', $tr->trans('document_signature.cancel.success_message'));
            } else {
                $this->addFlash('danger', $tr->trans('document_signature.cancel.error_message'));
            }
        }

        return $this->redirectToRoute('app_document_signature_document_status', ['id' => $clientSigningDocument->getId()]);
    }

    #[Route('/{id}/download', name: 'document_download', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function documentDownload(
        ClientSigningDocument $clientSigningDocument,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
    ): Response {

        $pdfPath = $clientSigningDocument->getDocument()?->getPath();
        $pdfName = $clientSigningDocument->getDocument()?->getName();

        if (null === $pdfPath || null === $pdfName) {
            throw $this->createNotFoundException('Document not found');
        }

        return $this->file($projectDir.'/'.$pdfPath, $pdfName);
    }
}
