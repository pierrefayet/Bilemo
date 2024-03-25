<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
        TagAwareCacheInterface $cache, CustomerRepository $customerRepository): Response
    {
        $page = max(filter_var(
            $request->get('page', 1),
            FILTER_VALIDATE_INT,
            ['options' => ['default' => 1]]),
            1);
        $limit = max(filter_var(
            $request->get('limit', 3),
            FILTER_VALIDATE_INT,
            ['options' => ['default' => 3]]),
            1);

        $idCache = 'getAllCustomer'.$page.'-'.$limit;

        $customerList = $cache->get($idCache, function (ItemInterface $item) use ($customerRepository, $page, $limit) {
            $item->tag('customersCache');
            $customers = $customerRepository->findAllCustomersWithPagination($page, $limit);

            return $customerRepository->findUsersByCustomer($customers);
        });

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->serializer->serialize($customerList, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * This code allows you to retrieve a customer.
     */
    #[Route('/customers/{id}', name: 'detail_customer', methods: ['GET'])]
    public function getDetailCustomer(Customer $customer): Response
    {
        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->serializer->serialize($customer, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * This code allows you to create a customer.
     */
    #[Route('/customers', name: 'create_customer', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to create a customer')]
    public function createCustomer(Request $request, UserRepository $userRepository, ValidatorInterface $validator): Response
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

        $user = $userRepository->find($idUser);

        if (null === $user) {
            return $this->json(
                ['error' => 'user is not found'], Response::HTTP_BAD_REQUEST
            );
        }

        $newCustomer->addUser($user);

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->serializer->serialize($newCustomer, 'json', $context);

        return new Response($jsonContent, Response::HTTP_CREATED, ['Content-Type' => 'application/json']);
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
    ): Response {
        $updateCustomer = $this->serializer->deserialize(
            $request->getContent(),
            Customer::class,
            'json',
            DeserializationContext::create()->setAttribute('target', $currentCustomer)
        );

        $content = $request->toArray();
        $userId = $content['userId'] ?? -1;

        if ($userId) {
            $user = $userRepository->find($userId);
            if (!$user) {
                return $this->json(['error' => 'User not found'], Response::HTTP_BAD_REQUEST);
            }

            $currentCustomer->addUser($user);
        }

        $cache->invalidateTags(['customersCache']);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->serializer->serialize($updateCustomer, 'json', $context);

        return new Response($jsonContent, Response::HTTP_CREATED, ['Content-Type' => 'application/json']);
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
