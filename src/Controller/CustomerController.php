<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'api_')]
class CustomerController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SerializerInterface $jmsSerializer
    ) {
    }

    #[OA\Get(
        path: '/api/customers/{id}',
        summary: 'Retrieve customer details by ID.',
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the customer to retrieve.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Customer details retrieved successfully.',
                content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ['customer:details']))
            ),
            new OA\Response(
                response: 404,
                description: 'Customer not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/customers/{id}', name: 'detail_customer', methods: ['GET'])]
    public function getDetailCustomer(Customer $customer): Response
    {
        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->jmsSerializer->serialize($customer, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[OA\Get(
        path: '/api/customers',
        description: 'Fetches a paginated list of customers, allowing consumers to browse through the customer data stored in the system. Pagination parameters "page" and "limit" can be used to navigate through the customer list.',
        summary: 'Retrieves a list of customers with pagination',
        security: [['bearerAuth' => []]],
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'The page number to fetch.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'The number of items to fetch per page.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 3)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A paginated list of customers.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Customer::class, groups: ['customer:details', 'user:details']))
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token not provided or expired.'
            ),
            new OA\Response(
                response: 404,
                description: 'Not Found - The requested page does not exist.'
            ),
        ]
    )]
    #[Route('/customers', name: 'list_customer', methods: ['GET'])]
    public function getAllCustomer(
        TagAwareCacheInterface $cache,
        CustomerRepository $customerRepository,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 3
    ): Response {
        $idCache = 'getAllCustomer'.$page.'-'.$limit;

        $customerList = $cache->get($idCache, function (ItemInterface $item) use ($customerRepository, $page, $limit) {
            $item->tag('customersCache');
            $customers = $customerRepository->findAllCustomersWithPagination($page, $limit);

            return $customerRepository->findUsersByCustomer($customers);
        });

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->jmsSerializer->serialize($customerList, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[OA\Post(
        path: '/api/customers',
        summary: 'Create a new customer and optionally associate it with an existing user.',
        requestBody: new OA\RequestBody(
            description: 'Customer data in JSON format',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                    new OA\Property(property: 'userId', description: 'Optional user ID to associate with this customer', type: 'integer', example: 1),
                ],
                type: 'object'
            )
        ),
        tags: ['Customers'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Customer created successfully. Returns the created customer data.',
                content: new OA\JsonContent(ref: new Model(type: Customer::class, groups: ['customer:details']))
            ),
            new OA\Response(
                response: 400,
                description: 'Bad Request - Validation error messages.'
            ),
            new OA\Response(
                response: 404,
                description: 'Not Found - The specified user does not exist.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/customers', name: 'create_customer', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to create a customer')]
    public function createCustomer(
        Request $request,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHashed,
        TagAwareCacheInterface $cache
    ): Response {
        $password = Uuid::v4();

        $newCustomer = $this->jmsSerializer->deserialize($request->getContent(), Customer::class, 'json');

        if (!$newCustomer instanceof Customer) {
            return $this->json(['error' => 'Invalid data provided'], Response::HTTP_BAD_REQUEST);
        }

        $hashPassword = $passwordHashed->hashPassword($newCustomer, $password);
        $newCustomer->setPassword($hashPassword);
        $newCustomer->setRoles(['ROLE_USER']);

        $errors = $validator->validate($newCustomer);

        if ($errors->count() > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($newCustomer);
        $this->entityManager->flush();
        $cache->invalidateTags(['customersCache']);

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->jmsSerializer->serialize(['password' => $password, 'customer' => $newCustomer], 'json', $context);

        return new Response($jsonContent, Response::HTTP_CREATED, ['Content-Type' => 'application/json']);
    }

    #[OA\Patch(
        path: '/api/customers/{id}',
        summary: 'Update an existing customer',
        requestBody: new OA\RequestBody(
            description: 'Data for updating an existing customer',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Pierre'),
                    new OA\Property(property: 'email', type: 'string', example: 'p.fayet@gmail.com'),
                    new OA\Property(property: 'userId', type: 'integer', example: 31),
                ],
                type: 'object'
            )
        ),
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the customer to update',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Customer updated successfully. No content to return.'
            ),
            new OA\Response(
                response: 400,
                description: 'Bad Request - Validation error or user ID not found.'
            ),
            new OA\Response(
                response: 404,
                description: 'Not Found - The specified customer does not exist.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/customers/{id}', name: 'update_customer', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to update a customer')]
    public function updateCustomer(
        Customer $currentCustomer,
        Request $request,
        UserRepository $userRepository,
        TagAwareCacheInterface $cache
    ): Response {
        $updateCustomer = $this->jmsSerializer->deserialize(
            $request->getContent(),
            Customer::class,
            'json',
            DeserializationContext::create()->setAttribute('target', $currentCustomer)
        );

        $content = $request->toArray();
        $userId = $content['userId'] ?? null;

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
        $jsonContent = $this->jmsSerializer->serialize($updateCustomer, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json'], true);
    }

    #[OA\Delete(
        path: '/api/customers/{id}',
        summary: 'Deletes a customer by ID.',
        tags: ['Customers'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the customer to delete.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Customer deleted successfully.'
            ),
            new OA\Response(
                response: 404,
                description: 'Customer not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/customers/{id}', name: 'delete_customer', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to delete a customer')]
    public function deleteCustomer(Customer $customer, TagAwareCacheInterface $cache): Response
    {
        $this->entityManager->remove($customer);
        $this->entityManager->flush();
        $cache->invalidateTags(['customersCache']);

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }
}
