<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class CustomerController extends AbstractController
{
    public function __construct(
        private readonly CustomerRepository $customerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer
    ) {
    }

    #[Route('/customers', name: 'list_customer', methods: ['GET'])]
    public function getAllCustomer(): JsonResponse
    {
        $customerList = $this->customerRepository->findAll();

        return $this->json(
            $customerList, Response::HTTP_OK, [], ['groups' => 'getCustomers']
        );
    }

    #[Route('/customers/{id}', name: 'detail_customer', methods: ['GET'])]
    public function getDetailCustomer(Customer $customer): JsonResponse
    {
        return $this->json(
            $customer, Response::HTTP_OK, [], ['groups' => 'getCustomers']
        );
    }

    #[Route('/customers', name: 'create_customer', methods: ['POST'])]
    public function createCustomer(Request $request, UserRepository $userRepository): JsonResponse
    {
        $newCustomer = $this->serializer->deserialize($request->getContent(), Customer::class, 'json');
        $this->entityManager->persist($newCustomer);
        $this->entityManager->flush();

        $content = $request->toArray();
        $idUser = $content['id'] ?? -1;

        $newCustomer->setUser($userRepository->find($idUser));

        return $this->json(
            $newCustomer, Response::HTTP_CREATED, [], ['groups' => 'getCustomers']
        );
    }

    #[Route('/customers/{id}', name: 'update_customer', methods: ['PUT'])]
    public function updateCustomer(
        Customer $currentCustomer,
        Request $request,
        UserRepository $userRepository
    ): JsonResponse {
        $updateCustomer = $this->serializer->deserialize(
            $request->getContent(),
            Customer::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentCustomer]
        );

        $content = $request->toArray();
        $idUser = $content['id'] ?? -1;

        $updateCustomer->setUser($userRepository->find($idUser));

        $this->entityManager->persist($updateCustomer);
        $this->entityManager->flush();

        return $this->json(
            $updateCustomer, Response::HTTP_NO_CONTENT
        );
    }

    #[Route('/customers/{id}', name: 'delete_customer', methods: ['DELETE'])]
    public function deleteCustomer(Customer $customer): Response
    {
        $this->entityManager->remove($customer);
        $this->entityManager->flush();

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }
}
