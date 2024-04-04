<?php

namespace App\Controller;

use App\Entity\Phone;
use App\Repository\PhoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface as JMSSerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'api_')]
class PhoneController extends AbstractController
{
    public function __construct(private readonly JMSSerializerInterface $jmsSerializer)
    {
    }

    #[OA\Get(
        path: '/api/phones',
        summary: 'Retrieves a paginated list of phones.',
        tags: ['Phones'],
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
                description: 'A paginated list of phones retrieved successfully.',
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
    #[Route('/phones', name: 'list_phone', requirements: ['page' => '\d+', 'limit' => '\d+'], methods: ['GET'])]
    public function getAllPhone(
        TagAwareCacheInterface $cache,
        PhoneRepository $phoneRepository,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] int $limit = 3
    ): JsonResponse {
        $idCache = 'getAllPhone'.$page.'-'.$limit;

        $phoneList = $cache->get($idCache, function (ItemInterface $item) use ($phoneRepository, $page, $limit) {
            $item->tag('phonesCache');

            return $phoneRepository->findAllPhonesWithPagination($page, $limit);
        });

        $context = SerializationContext::create()->setGroups(['phone:details']);
        $jsonContent = $this->jmsSerializer->serialize($phoneList, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json'], true);
    }

    #[OA\Get(
        path: '/api/phones/{id}',
        summary: 'Get phone details by ID.',
        tags: ['Phones'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The ID of the phone to retrieve details for.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Phone details retrieved successfully.',
                content: new OA\JsonContent(ref: new Model(type: Phone::class, groups: ['phone:details']))
            ),
            new OA\Response(
                response: 404,
                description: 'Phone not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/phones/{id}', name: 'detail_phone', methods: ['GET'])]
    public function getDetailPhone(Phone $phone): Response
    {
        $context = SerializationContext::create()->setGroups(['phone:details']);
        $jsonContent = $this->jmsSerializer->serialize($phone, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[OA\Post(
        path: '/api/phones',
        summary: 'Create a new phone',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'model', type: 'string', example: 'Model iphone16'),
                    new OA\Property(property: 'manufacturer', type: 'string', example: 'Apple'),
                    new OA\Property(property: 'processor', type: 'string', example: 'Exynos 2100'),
                    new OA\Property(property: 'ram', type: 'string', example: '8 GB'),
                    new OA\Property(property: 'storageCapacity', type: 'string', example: '265GB'),
                    new OA\Property(property: 'cameraDetails', type: 'string', example: '43MP'),
                    new OA\Property(property: 'batteryLife', type: 'string', example: '71 hours'),
                    new OA\Property(property: 'screenSize', type: 'string', example: '6.48 pouces'),
                    new OA\Property(property: 'price', type: 'string', example: '642'),
                    new OA\Property(property: 'stockQuantity', type: 'string', example: '40'),
                ],
                type: 'object'
            )
        ),
        tags: ['Phones'],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Phone created successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'UNAUTHORIZED - JWT token expired, invalid or not provided.'
            ),
            new OA\Response(
                response: 404,
                description: 'NOT FOUND'
            ),
        ]
    )]
    #[Route('/phones', name: 'create_phone', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to create a phone')]
    public function createPhone(
        Request $request,
        EntityManagerInterface $entityManager,
        JMSSerializerInterface $jmsSerializer,
        ValidatorInterface $validator
    ): JsonResponse {
        $phone = $jmsSerializer->deserialize($request->getContent(), Phone::class, 'json');

        $errors = $validator->validate($phone);
        if ($errors->count() > 0) {
            $errorsArray = [];
            foreach ($errors as $error) {
                $errorsArray[$error->getPropertyPath()] = $error->getMessage();
            }
            $errorsJson = $jmsSerializer->serialize($errorsArray, 'json');

            return $this->json($errorsJson, Response::HTTP_BAD_REQUEST);
        }

        if (!$phone instanceof Phone) {
            return $this->json(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($phone);
        $entityManager->flush();

        $context = SerializationContext::create()->setGroups(['phone:details']);
        $phoneJson = $jmsSerializer->serialize($phone, 'json', $context);

        return new JsonResponse($phoneJson, Response::HTTP_CREATED, ['Content-Type' => 'application/json'], true);
    }

    #[OA\Put(
        path: '/api/phones/{id}',
        summary: 'Updates a specific phone by ID.',
        requestBody: new OA\RequestBody(
            description: 'JSON payload to update the phone',
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: Phone::class, groups: ['phone:details'])
            )
        ),
        tags: ['Phones'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the phone to update',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Phone updated successfully.'
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid data provided.'
            ),
            new OA\Response(
                response: 404,
                description: 'Phone not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/phones/{id}', name: 'update_phone', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to update a phone')]
    public function updatePhone(
        Phone $currentPhone,
        Request $request,
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cache
    ): JsonResponse {
        $updatePhone = $this->jmsSerializer->deserialize(
            $request->getContent(),
            Phone::class,
            'json',
            DeserializationContext::create()->setAttribute('target', $currentPhone)
        );

        if (!$updatePhone instanceof Phone) {
            return new JsonResponse(['error' => 'Invalid data provided'], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($updatePhone);
        $entityManager->flush();
        $cache->invalidateTags(['phonesCache']);

        $context = SerializationContext::create()->setGroups(['phone:details']);
        $jsonContent = $this->jmsSerializer->serialize($updatePhone, 'json', $context);

        return new JsonResponse($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json'], true);
    }

    #[OA\Delete(
        path: '/api/phones/{id}',
        summary: 'Deletes a specific phone by ID.',
        tags: ['Phones'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the phone to delete',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Phone deleted successfully.'
            ),
            new OA\Response(
                response: 404,
                description: 'Phone not found.'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized - JWT token expired, invalid, or not provided.'
            ),
        ]
    )]
    #[Route('/phones/{id}', name: 'delete_phone', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to delete a phone')]
    public function deletePhone(Phone $phone, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): Response
    {
        $entityManager->remove($phone);
        $entityManager->flush();
        $cache->invalidateTags(['phonesCache']);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
