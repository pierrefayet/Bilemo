<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Phone;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'api_')]
class UserController extends AbstractController
{
    #[OA\Get(
        path: '/api/users',
        summary: 'Retrieves users.',
        tags: ['Users'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'The page number of the results to retrieve.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'The number of results per page.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 10)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'A paginated list of users retrieved successfully.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Phone::class, groups: ['phone:details']))
                )
            ),
            new OA\Response(
                response: 404,
                description: 'No phones found.'
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
        $users = $this->getUser()->getUsers()->toArray();

        if (!in_array($user, $users)) {
            throw new \Exception('access denied');
        }

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $jmsSerializer->serialize($user, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[Route('/users', name: 'get_users_list_by_customer', methods: ['GET'])]
    public function getListUserByCustomer(SerializerInterface $jmsSerializer): Response
    {
        $users = $this->getUser()->getUsers()->toArray();

        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $jmsSerializer->serialize($users, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[Route('/users', name: 'create_user', methods: ['POST'])]
    public function createUser(
        Request $request,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache,
        SerializerInterface $jmsSerializer,
        EntityManagerInterface $entityManager
    ): Response {
        $password = Uuid::v4();

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

        $userCollection = $this->getUser()->getUsers()->toArray();

        if (!in_array($currentUser, $userCollection) && !in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
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
        $users = $this->getUser()->getUsers()->toArray();

        if (!in_array($user, $users)) {
            throw new \Exception('access denied');
        }

        $userAssociativeWithCustomer = count($user->getCustomers());
        $cache->invalidateTags(['customersCache']);

        if (1 === $userAssociativeWithCustomer) {
            $entityManager->remove($user);
        }

        $this->getUser()->removeUser($user);
        $entityManager->flush();

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }
}
