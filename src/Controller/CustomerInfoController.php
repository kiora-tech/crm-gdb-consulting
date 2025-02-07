<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use function Symfony\Component\String\u;

abstract class CustomerInfoController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly SluggerInterface $slugger)
    {
    }

    /**
     * @phpstan-return class-string
     * @return string
     */
    protected abstract function getEntityClass(): string;

    /**
     * Replace Entity form namespace by Form
     * @phpstan-return class-string
     * @return string
     */
    protected function getFormTypeClass(): string
    {
        return str_replace('\Entity\\', '\Form\\', $this->getEntityClass()) . 'Type';
    }

    public function getBaseRouteName(): string
    {
        return 'app_' . $this->getName();
    }

    protected function getName(): string
    {
        $shortName = (new \ReflectionClass($this->getEntityClass()))->getShortName();

        return u($shortName)->snake()->toString();
    }


    protected function getRepository(): ObjectRepository
    {
        return $this->entityManager->getRepository($this->getEntityClass());
    }

    #[Route('/new/{customer?}', name: '_new', methods: ['GET', 'POST'])]
    public function new(Request $request, ?Customer $customer = null): Response
    {
        $entity = new ($this->getEntityClass());
        if ($customer) {
            $entity->setCustomer($customer);
        }
        $form = $this->createForm($this->getFormTypeClass(), $entity, ['customer' => $customer]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            if ($customer) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()],
                    Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render($this->getName().'/new.html.twig', [
            'entity' => $entity,
            'form' => $form,
            'customer' => $customer, 
        ]);
    }
    #[Route(name: '_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render($this->getName().'/index.html.twig', [
            'entities' => $this->getRepository()->findAll(),
        ]);
    }

    #[Route('/{id}/edit/{customer?}', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);
        
        if ($customer) {
            $entity->setCustomer($customer);
        }
    
        $form = $this->createForm($this->getFormTypeClass(), $entity, ['customer' => $customer]);
        $form->handleRequest($request);
    
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            
            if ($customer) {
                return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
            }
    
            return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
        }
    
        return $this->render($this->getName().'/edit.html.twig', [
            'entity' => $entity,
            'form' => $form,
            'customer' => $customer, 
        ]);
    }
    


    #[Route('/{id}/{customer?}', name: '_delete', methods: ['POST'])]
    public function delete(Request $request, int $id, ?Customer $customer = null): Response
    {
        $entity = $this->getRepository()->find($id);
        if ($customer) {
            $entity->setCustomer($customer);
        }
        if ($this->isCsrfTokenValid('delete'.$id, $request->getPayload()->getString('_token'))) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
        if ($customer) {
            return $this->redirectToRoute('app_customer_show', ['id' => $customer->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute($this->getBaseRouteName().'_index', [], Response::HTTP_SEE_OTHER);
    }

}
