<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Phone;
use App\Repository\PhoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface as JMSSerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
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

    #[OA\Response(
        response: 200,
        description: 'Retrieves the list of all phones with pagination.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['phone:details']))
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
    #[OA\Tag(name: 'Phones')]
    /**
     * Fetches phones with support for pagination and caching for enhanced performance.
     *
     * @param Request                $request         the HTTP request containing pagination parameters
     * @param TagAwareCacheInterface $cache           the cache service
     * @param PhoneRepository        $phoneRepository the repository for accessing phone data
     *
     * @return JsonResponse the phone list in JSON format
     *
     * @throws InvalidArgumentException
     *
     * @example Request: GET /api/phones?page=2&limit=5
     */
    #[Route('/phones', name: 'list_phone', requirements: ['page' => '\d+', 'limit' => '\d+'], methods: ['GET'])]
    public function getAllPhone(
        Request $request,
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

    #[OA\Response(
        response: 200,
        description: 'Retrieves phone details by ID.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['phone:details']))
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
    #[OA\Tag(name: 'Phones')]
    /**
     * Uses the ID in the URL to search for and return the details of a specific phone.
     * Data is filtered by the 'phone:details' serialization group.
     *
     * @param Phone $phone the Phone entity resolved by Symfony using the ID in the URL
     *
     * @return Response phone details in JSON (HTTP 200)
     */
    #[Route('/phones/{id}', name: 'detail_phone', methods: ['GET'])]
    public function getDetailPhone(Phone $phone): Response
    {
        $context = SerializationContext::create()->setGroups(['phone:details']);
        $jsonContent = $this->jmsSerializer->serialize($phone, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    #[OA\Response(
        response: 204,
        description: 'Create a new phone.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['phone:details']))
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
    #[OA\Tag(name: 'Phones')]
    /**
     * Validates and persists phone data provided in JSON. Returns the created phone or
     * validation errors.
     *
     * @param Request $request contains JSON with phone data
     *
     * @return JsonResponse the phone created (HTTP 201) or validation errors (HTTP 400)
     *
     * @example JSON for creation :
     * {
     * "model": "Model iphone16",
     * "manufacturer": "Apple",
     * "processor": "Exynos 2100",
     * "ram": "8 GB",
     * "storageCapacity": "265GB",
     * "cameraDetails": "43MP",
     * "batteryLife": "71 hours",
     * "screenSize": "6.48 pouces",
     * "price": "642",
     * "stockQuantity": "40"
     * }
     */
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

    #[OA\Response(
        response: 204,
        description: 'Updates the details of a specific phone.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['phone:details']))
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
    #[OA\Tag(name: 'Phones')]
    /**
     * Updates a phone using JSON data, validates it, and returns the updated object after cache invalidation.
     *
     * @param Phone                  $currentPhone  the Phone object (automatically resolved by Symfony) to be updated
     * @param Request                $request       the HTTP request containing the update data in JSON format
     * @param EntityManagerInterface $entityManager the entity manager for data persistence
     * @param TagAwareCacheInterface $cache         the cache service for invalidating cache tags
     *
     * @return JsonResponse the HTTP response containing the updated Phone object in JSON format
     *
     * @throws InvalidArgumentException
     */
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

    #[OA\Response(
        response: 204,
        description: 'Deletes a phone specified by its ID.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Customer::class, groups: ['phone:details']))
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
    #[OA\Tag(name: 'Phones')]
    /**
     * Deletes a phone by ID, returning HTTP 204 on success. Requires ADMIN role.
     *
     * @param Phone                  $phone         the Phone entity automatically resolved by Symfony from the ID in the URL
     * @param EntityManagerInterface $entityManager the entity manager for interacting with the database
     *
     * @return Response an HTTP response with status 204 (No Content)
     *
     * @throws InvalidArgumentException
     */
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
