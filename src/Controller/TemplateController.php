<?php

namespace App\Controller;

use App\Entity\Template;
use App\Entity\TemplateType;
use App\Form\TemplateType as TemplateFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Psr\Log\LoggerInterface;

#[Route('/template')]
class TemplateController extends BaseCrudController
{
    #[Route('/', name: 'app_template_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $qb = $this->entityManager
            ->getRepository($this->getEntityClass())
            ->createQueryBuilder('e');

        $pagination = $this->paginator->paginate(
            $qb,
            $request->query->getInt('page', 1)
        );

        return $this->render('template/index.html.twig', [
            'pagination' => $pagination,
            'columns' => $this->getColumns(),
            'page_prefix' => $this->getPagePrefix(),
            'page_title' => 'template.title',
            'new_route' => $this->getNewRoute(),
            'table_routes' => $this->getRoute()
        ]);
    }

    #[Route('/new', name: 'app_template_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/public/uploads/templates')] string $uploadDirectory,
    ): Response {
        $template = new Template();
        $form = $this->createForm(TemplateFormType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    $template->setType(TemplateType::fromMimeType($file->getMimeType()));
                    $template->setMimeType($file->getMimeType());
                    $file->move($uploadDirectory, $newFilename);

                    $template->setPath('templates/' . $newFilename);
                    $template->setOriginalFilename($file->getClientOriginalName());

                    $this->entityManager->persist($template);
                    $this->entityManager->flush();

                    return $this->redirectToRoute('app_template_index');
                } catch (FileException $e) {
                    $logger->error('Upload error: ' . $e->getMessage());
                    $this->addFlash('error', 'template.upload.error');
                }
            }
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $template));
    }

    #[Route('/{id}/edit', name: 'app_template_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Template $template,
        SluggerInterface $slugger,
        LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%/public/uploads/templates')] string $uploadDirectory,
    ): Response {
        $form = $this->createForm(TemplateFormType::class, $template);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();

            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

                try {
                    // Supprimer l'ancien fichier
                    $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $template->getPath();
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                    $template->setType(TemplateType::fromMimeType($file->getMimeType()));
                    $template->setMimeType($file->getMimeType());

                    $file->move($uploadDirectory, $newFilename);


                    $template->setPath('templates/' . $newFilename);
                    $template->setOriginalFilename($file->getClientOriginalName());

                    $this->entityManager->flush();

                    return $this->redirectToRoute('app_template_index');
                } catch (FileException $e) {
                    $logger->error('Upload error: ' . $e->getMessage());
                    $this->addFlash('error', 'template.upload.error');
                }
            }
        }

        return $this->render('crud/form.html.twig', $this->getFormVars($form, $template));
    }

    #[Route('/{id}/download', name: 'app_template_download', methods: ['GET'])]
    public function download(
        Template $template,
        MimeTypesInterface $mimeTypes
    ): Response {
        $response = new Response();
        $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $template->getPath();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Le fichier demandé n\'existe pas');
        }

        $typeMime = $mimeTypes->guessMimeType($filePath);
        $baseName = $template->getOriginalFilename();

        $response->headers->set('Content-Type', $typeMime);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $baseName . '"');
        $response->setContent(file_get_contents($filePath));

        return $response;
    }

    #[Route('/{id}', name: 'app_template_delete', methods: ['POST'])]
    public function delete(Request $request, Template $template): Response
    {
        if ($this->isCsrfTokenValid('delete'.$template->getId(), $request->request->get('_token'))) {
            $filePath = $this->getParameter('kernel.project_dir') . '/public/' . $template->getPath();

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $this->entityManager->remove($template);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('app_template_index', [], Response::HTTP_SEE_OTHER);
    }

    protected function getColumns(): array
    {
        return [
            ['field' => 'label', 'label' => 'template.label', 'sortable' => true],
            ['field' => 'originalFilename', 'label' => 'template.filename', 'sortable' => true],
        ];
    }

    protected function getRoute(): array
    {
        return [
            'edit' => 'app_template_edit',
            'delete' => 'app_template_delete',
            'show' => 'app_template_download'
        ];
    }

    protected function getEntityClass(): string
    {
        return Template::class;
    }

    protected function getFormVars($form, ?object $entity = null): array
    {
        $routePrefix = $this->getRoutePrefix();

        return [
            'form' => $form->createView(),
            'entity' => $entity,
            'back_route' => $routePrefix . '_index',
            'delete_route' => $routePrefix . '_delete',
            'page_prefix' => $this->getPagePrefix(),
            'template_path' => null
        ];
    }
}