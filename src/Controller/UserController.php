<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\User;
use App\Form\UserProfilType;
use App\Form\UserType;
use App\Notification\NewCollaboratorNotification;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/user')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository, PaginatorInterface $paginator, Request $request): Response
    {
        $pagination = $paginator->paginate(
            $userRepository->createQueryBuilder('e'),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('user/index.html.twig', [
            'users' => $pagination,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        NotifierInterface $notifier,
        UrlGeneratorInterface $urlGenerator,
    ): Response {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword(
                $user,
                $form->get('plainPassword')->getData()
            ));

            $entityManager->persist($user);
            $entityManager->flush();

            $creator = $this->getUser();
            if (!$creator instanceof User) {
                throw new \LogicException('Creator user not found');
            }

            $loginUrl = $urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $notifier->send(
                new NewCollaboratorNotification(
                    $user,
                    $creator,
                    $loginUrl
                ),
                $user
            );

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/company/{id}', name: 'app_user_company_index', requirements: ['id' => Requirement::POSITIVE_INT], methods: ['GET'])]
    public function companyIndex(Company $company): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User is not valid');
        }

        return $this->render('user/index.html.twig', [
            'users' => $company->getUsers(),
            'impersonate' => true,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $newPassword = $form->get('plainPassword')->getData();
            if ($newPassword) {
                $user->setPassword($passwordHasher->hashPassword(
                    $user,
                    $newPassword
                ));
            }

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/edit/profile', name: 'app_user_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User is not valid');
        }

        // Render the profile template directly, without redirecting
        return $this->render('user/profile.html.twig', [
            'form' => $this->createForm(UserProfilType::class, $user)->createView(),
        ]);
    }

    #[Route('/profile/delete-picture', name: 'app_user_delete_profile_picture', methods: ['POST'])]
    public function deleteProfilePicture(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('User not found');
        }

        $user->setProfilePicture(null);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash_message.profile_updated.deleted_profile_picture', [], 'messages'));

        return $this->redirectToRoute('app_user_profile');
    }
}
