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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'api_')]
class CustomerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * This code allows you to retrieve all customers.
     */
    #[Route('/customers', name: 'list_customer', methods: ['GET'])]
    public function getAllCustomer(
        Request $request,
        TagAwareCacheInterface $cache, CustomerRepository $customerRepository): JsonResponse
    {
        $page = (int) $request->get('page', 1);
        $limit = (int) $request->get('limit', 3);

        if (0 === $page || 0 === $limit) {
            return $this->json(['error' => 'invalid arguments'], Response::HTTP_BAD_REQUEST);
        }

        $idCache = 'getAllCustomer'.$page.'-'.$limit;

        $customerList = $cache->get($idCache, function (ItemInterface $item) use ($customerRepository, $page, $limit) {
            $item->tag('customersCache');
            $customers = $customerRepository->findAllCustomersWithPagination($page, $limit);

            return $customerRepository->findCustomerById($customers);
        });

        return $this->json($customerList, Response::HTTP_OK, [], ['groups' => ['customer:details', 'user:details']]);
    }

    /**
     * This code allows you to retrieve a customer.
     */
    #[Route('/customers/{id}', name: 'detail_customer', methods: ['GET'])]
    public function getDetailCustomer(Customer $customer): JsonResponse
    {
        return $this->json(
            $customer, Response::HTTP_OK, [], ['groups' => 'customer:details', 'user:details']
        );
    }

    /**
     * This code allows you to create a customer.
     */
    #[Route('/customers', name: 'create_customer', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to create a customer')]
    public function createCustomer(Request $request, UserRepository $userRepository, ValidatorInterface $validator): JsonResponse
    {
        $newCustomer = $this->serializer->deserialize($request->getContent(), Customer::class, 'json');
        $errors = $validator->validate($newCustomer);

        if ($errors->count() > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $this->entityManager->persist($newCustomer);
        $this->entityManager->flush();

        $content = $request->toArray();
        $idUser = $content['userId'] ?? -1;

        $newCustomer->addUser($userRepository->find($idUser));

        return $this->json(
            $newCustomer, Response::HTTP_CREATED, [], ['groups' => ['customer:details', 'user:details']]
        );
    }

    /**
     * This code allows you to create a customer.
     */
    #[Route('/customers/{id}', name: 'update_customer', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to update a customer')]
    public function updateCustomer(
        Customer $currentCustomer,
        Request $request,
        UserRepository $userRepository,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $updateCustomer = $this->serializer->deserialize(
            $request->getContent(),
            Customer::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentCustomer]
        );

        $content = $request->toArray();
        $idUser = $content['userId'] ?? -1;

        $updateCustomer->addUser($userRepository->find($idUser));
        $cache->invalidateTags(['customersCache']);

        $this->entityManager->persist($updateCustomer);
        $this->entityManager->flush();

        return $this->json(
            $updateCustomer, Response::HTTP_NO_CONTENT, [], ['groups' => ['customer:details', 'user:details']]
        );
    }

    /**
     * This code allows you to delete a customer.
     */
    #[Route('/customers/{id}', name: 'delete_customer', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to delete a customer')]
    public function deleteCustomer(Customer $customer): Response
    {
        $this->entityManager->remove($customer);
        $this->entityManager->flush();

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }
}
