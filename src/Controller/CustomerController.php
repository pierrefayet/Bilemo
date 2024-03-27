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
        private readonly SerializerInterface $jmsSerializer
    ) {
    }

    #[OA\Response(
        response: 200,
        description: 'Returns the customer detail of the requested page.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['customer:details', 'user:details']))
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'NOT FOUND',
    )
    ]
    #[OA\Response(
        response: 401,
        description: 'UNAUTHORIZED - JWT token expired, invalid or not provided.',
    )
    ]
    #[OA\Parameter(
        name: 'page',
        description: 'Requested result page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 1),
        example: '1'
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Number of results per page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 10),
        example: '10'
    )]
    #[OA\Tag(name: 'Customers')]
    /**
     * Retrieves the customer with pagination.
     *
     * This method returns a paginated  of the customer. It supports pagination via the
     * page' and 'limit' parameters in the request. The results are cached to
     * improve performance.
     *
     * @return Response the customer in JSON format
     *
     * @example Request: GET /customers?page=2&limit=5
     */
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
                schema: new OA\Schema(type: 'integer', default: 10)
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
    /**
     * This method returns a paginated list of the customers. It supports pagination via the
     * page' and 'limit' parameters in the request. The results are cached to
     * improve performance.
     *
     * @return Response the customer in JSON format
     *
     * @throws InvalidArgumentException
     *
     * @example Request: GET /customers?page=2&limit=5
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
        $jsonContent = $this->jmsSerializer->serialize($customerList, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[OA\Response(
        response: 204,
        description: 'Creates a new client and associates it with an existing user.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['customer:details', 'user:details']))
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'NOT FOUND',
    )
    ]
    #[OA\Response(
        response: 401,
        description: 'UNAUTHORIZED - JWT token expired, invalid or not provided.',
    )
    ]
    #[OA\Parameter(
        name: 'page',
        description: 'Requested result page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 1),
        example: '1'
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Number of results per page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 10),
        example: '10'
    )]
    #[OA\Tag(name: 'Customers')]
    /**
     * This method waits for the customer data in JSON format in the request body.
     * It deserializes this data into a Customer entity, validates it, and if no validation
     *  error is found, associates the customer with a user specified by `userId` in the request body.
     * in the query body before persisting the customer in the database.
     *
     * @param Request            $request        the HTTP request containing the client data
     * @param UserRepository     $userRepository the repository for retrieving User entities
     * @param ValidatorInterface $validator      the validation service for checking client data
     *
     * @return Response the HTTP response, with the client created in JSON format if creation is successful,
     *                  or with validation error messages if applicable
     *
     * @example Request body for creation :
     * {
     * "firstName": "Jean",
     * lastName": "Dupont",
     * "email": "jean.dupont@example.com",
     * "userId": 31
     * }
     */
    #[Route('/customers', name: 'create_customer', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to create a customer')]
    public function createCustomer(Request $request, UserRepository $userRepository, ValidatorInterface $validator): Response
    {
        $newCustomer = $this->jmsSerializer->deserialize($request->getContent(), Customer::class, 'json');
        if (!$newCustomer instanceof Customer) {
            return $this->json(['error' => 'Invalid data provided'], Response::HTTP_BAD_REQUEST);
        }

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
        $jsonContent = $this->jmsSerializer->serialize($newCustomer, 'json', $context);

        return new Response($jsonContent, Response::HTTP_CREATED, ['Content-Type' => 'application/json']);
    }

    #[OA\Response(
        response: 200,
        description: 'Updates an existing customer with the information provided in the request.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['customer:details', 'user:details']))
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'NOT FOUND',
    )
    ]
    #[OA\Response(
        response: 401,
        description: 'UNAUTHORIZED - JWT token expired, invalid or not provided.',
    )
    ]
    #[OA\Parameter(
        name: 'page',
        description: 'Requested result page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 1),
        example: '1'
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Number of results per page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 10),
        example: '10'
    )]
    #[OA\Tag(name: 'Customers')]
    /**
     * Updates a customer by ID from request body, associating with a user by userId. Returns an error if user doesn't exist. Invalidates customer cache tags post-update.
     *
     * @param Customer               $currentCustomer the Customer entity automatically resolved by Symfony from the ID in the URL
     * @param Request                $request         the HTTP request containing the update data in JSON format
     * @param UserRepository         $userRepository  the repository for accessing User entities
     * @param TagAwareCacheInterface $cache           the cache service for invalidating cache tags
     *
     * @return Response an HTTP response with status 204 (No Content) if successful, or 400 (Bad Request) if the user is not found
     *
     * @throws InvalidArgumentException
     *
     * @example Request body for the update:
     * {
     * "firstName": "Pierre",
     * lastName": "Fayet",
     * "email": "p.fayet@gmail.com",
     * "userId": 31
     * }
     * @example Possible answers:
     * - HTTP 204 No Content: The update was successful.
     * - HTTP 400 Bad Request: If the user ID specified in `userId` is not found.
     */
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
        $jsonContent = $this->jmsSerializer->serialize($updateCustomer, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json'], true);
    }

    #[OA\Response(
        response: 204,
        description: 'Deletes a customer specified by its ID.
     *',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['customer:details', 'user:details']))
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'NOT FOUND',
    )
    ]
    #[OA\Response(
        response: 401,
        description: 'UNAUTHORIZED - JWT token expired, invalid or not provided.',
    )
    ]
    #[OA\Parameter(
        name: 'page',
        description: 'Requested result page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 1),
        example: '1'
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Number of results per page',
        in: 'query',
        schema: new OA\Schema(type: 'int', default: 10),
        example: '10'
    )]
    #[OA\Tag(name: 'Customers')]
    /**
     * Deletes a customer by ID, returning HTTP 204 on success. Requires ADMIN role.
     *
     * @param Customer $customer the Customer entity automatically resolved by Symfony from the ID in the URL
     *
     * @return Response an HTTP response with status 204 (No Content) to indicate successful deletion
     *
     * @example Request URL for deletion:
     * DELETE /api/customers/{id}
     * @example Possible responses:
     * - HTTP 204 No Content: Deletion was successful.
     * - HTTP 404 Not Found: No client matching the provided ID was found.
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
