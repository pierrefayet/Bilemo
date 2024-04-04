<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'api_')]
class UserController extends CustomAbstractController
{
    #[OA\Get(
        path: '/api/users/{id}',
        summary: 'Retrieve user details by ID.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the user to retrieve.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details retrieved successfully.',
                content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:details']))
            ),
            new OA\Response(
                response: 404,
                description: 'User not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/users/{id}', name: 'get_users_by_customer', methods: ['GET'])]
    public function getUsersByCustomer(User $user, SerializerInterface $jmsSerializer): Response
    {
        $users = $this->getCustomer()->getUsers()->toArray();

        if (!in_array($user, $users) && !in_array('ROLE_ADMIN', $this->getCustomer()->getRoles())) {
            throw new \Exception('Access denied');
        }

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $jmsSerializer->serialize($user, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[OA\Get(
        path: '/api/users',
        description: 'Fetches a paginated list of users, allowing consumers to browse through the customer data stored in the system. Pagination parameters "page" and "limit" can be used to navigate through the user list.',
        summary: 'Retrieves a list of users with pagination',
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
                description: 'A paginated list of users.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['user:details']))
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
    #[Route('/users', name: 'get_users_list_by_customer', methods: ['GET'])]
    public function getListUserByCustomer(
        SerializerInterface $jmsSerializer,
        TagAwareCacheInterface $cache,
        UserRepository $userRepository,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 3
    ): Response {
        $idCache = 'getAllUser'.$page.'-'.$limit;
        $customer = $this->getCustomer()->getId();

        $userList = $cache->get($idCache, function (ItemInterface $item) use ($userRepository, $customer, $page, $limit) {
            $item->tag('userCache');


            return $userRepository->findAllUserWithPagination($customer, $page, $limit);
        });

        $context = SerializationContext::create()->setGroups(['user:details']);
        $jsonContent = $jmsSerializer->serialize($userList, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[OA\Post(
        path: '/api/users',
        summary: 'Create a new users.',
        requestBody: new OA\RequestBody(
            description: 'User data in JSON format',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'first_name', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'last_name', type: 'string', example: 'Jean'),
                    new OA\Property(property: 'email', type: 'string', example: 'jean.dupont@example.com'),
                ],
                type: 'object'
            )
        ),
        tags: ['Users'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created successfully. Returns the created customer data.',
                content: new OA\JsonContent(ref: new Model(type: User::class, groups: ['user:details']))
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
    #[Route('/users', name: 'create_user', methods: ['POST'])]
    public function createUser(
        Request $request,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache,
        SerializerInterface $jmsSerializer,
        EntityManagerInterface $entityManager
    ): Response {
        $newUser = $jmsSerializer->deserialize($request->getContent(), User::class, 'json');

        if (!$newUser instanceof User) {
            return $this->json(['error' => 'Invalid data provided'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $validator->validate($newUser);

        if ($errors->count() > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($newUser);
        $entityManager->flush();
        $cache->invalidateTags(['customersCache']);

        $context = SerializationContext::create()->setGroups(['user:details']);
        $jsonContent = $jmsSerializer->serialize($newUser, 'json', $context);

        return new Response($jsonContent, Response::HTTP_CREATED, ['Content-Type' => 'application/json']);
    }

    #[OA\Put(
        path: '/api/users/{id}',
        summary: 'update a users by ID.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the user to update.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'User update successfully.'
            ),
            new OA\Response(
                response: 404,
                description: 'User not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/users/{id}', name: 'update_user', methods: ['PUT'])]
    public function updateCustomer(
        Request $request,
        User $currentUser,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache,
        SerializerInterface $jmsSerializer,
        EntityManagerInterface $entityManager
    ): Response {
        $updateUser = $jmsSerializer->deserialize(
            $request->getContent(),
            User::class,
            'json',
            DeserializationContext::create()->setAttribute('target', $currentUser)
        );

        $userCollection = $this->getCustomer()->getUsers()->toArray();

        if (!in_array($currentUser, $userCollection) && !in_array('ROLE_ADMIN', $this->getCustomer()->getRoles())) {
            throw new \Exception('Access denied');
        }

        $errors = $validator->validate($updateUser);

        if ($errors->count() > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $cache->invalidateTags(['customersCache']);
        $entityManager->flush();

        $context = SerializationContext::create()->setGroups(['user:details']);
        $jsonContent = $jmsSerializer->serialize($updateUser, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json'], true);
    }

    #[OA\Delete(
        path: '/api/users/{id}',
        summary: 'Deletes a users by ID.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the user to delete.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'User deleted successfully.'
            ),
            new OA\Response(
                response: 404,
                description: 'User not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/users/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(User $user, TagAwareCacheInterface $cache, EntityManagerInterface $entityManager): Response
    {
        $users = $this->getCustomer()->getUsers()->toArray();

        if (!in_array($user, $users)) {
            throw new \Exception('access denied');
        }

        $userAssociativeWithCustomer = count($user->getCustomers());
        $cache->invalidateTags(['customersCache']);

        if (1 === $userAssociativeWithCustomer) {
            $entityManager->remove($user);
        }

        $this->getCustomer()->removeUser($user);
        $entityManager->flush();

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }
}
