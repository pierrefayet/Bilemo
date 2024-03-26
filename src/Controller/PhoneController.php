<?php

namespace App\Controller;

use App\Entity\Phone;
use App\Repository\PhoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface as JMSSerializerInterface;
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
class PhoneController extends AbstractController
{
    public function __construct(private readonly JMSSerializerInterface $jmsSerializer)
    {
    }

    /**
     * Retrieves the list of all phones with pagination.
     *
     * This method returns a paginated list of phones. It supports pagination via the
     * page' and 'limit' parameters in the request. The results are cached to
     * improve performance. Caching is based on the pagination parameters,
     * ensuring that different pages are cached separately.
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
    public function getAllPhone(Request $request, TagAwareCacheInterface $cache, PhoneRepository $phoneRepository): Response
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

        $idCache = 'getAllPhone'.$page.'-'.$limit;

        $phoneList = $cache->get($idCache, function (ItemInterface $item) use ($phoneRepository, $page, $limit) {
            $item->tag('phonesCache');

            return $phoneRepository->findAllPhonesWithPagination($page, $limit);
        });

        $context = SerializationContext::create()->setGroups(['phone:details']);
        $jsonContent = $this->jmsSerializer->serialize($phoneList, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * Récupère les détails d'un téléphone par son ID.
     *
     * Utilise l'ID dans l'URL pour chercher et retourner les détails d'un téléphone spécifique.
     * Les données sont filtrées par le groupe de sérialisation 'phone:details'.
     *
     * @param Phone $phone L'entité Phone résolue par Symfony grâce à l'ID dans l'URL
     *
     * @return Response les détails du téléphone en JSON (HTTP 200)
     */
    #[Route('/phones/{id}', name: 'detail_phone', methods: ['GET'])]
    public function getDetailPhone(Phone $phone): Response
    {
        $context = SerializationContext::create()->setGroups(['phone:details']);
        $jsonContent = $this->jmsSerializer->serialize($phone, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * Create a new phone.
     *
     * Validates and persists phone data provided in JSON. Returns the created phone or
     * validation errors.
     *
     * @param Request $request contains JSON with phone data
     *
     * @return JsonResponse the phone created (HTTP 201) or validation errors (HTTP 400)
     *
     * @example JSON for creation :
     * {
     * "model": "Model hcbyefsqefsefd",
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

        $entityManager->persist($phone);
        $entityManager->flush();

        $context = SerializationContext::create()->setGroups(['phone:details']);
        $phoneJson = $jmsSerializer->serialize($phone, 'json', $context);

        return new JsonResponse($phoneJson, Response::HTTP_CREATED, ['Content-Type' => 'application/json'], true);
    }

    /**
     * Updates the details of a specific phone.
     *
     * This method expects data in JSON format in the body of the PUT request.
     * It deserializes this data to update an existing Phone object,
     * validates the updated object, and persists it in the database. The
     * associated cache tags are invalidated to reflect the changes.
     * The updated Phone object is then serialized and returned in the response.
     *
     * @param Phone                  $currentPhone  the Phone object (automatically resolved by Symfony) to be updated
     * @param Request                $request       the HTTP request containing the update data in JSON format
     * @param JMSSerializerInterface $serializer    the JMS Serializer service for serialization/deserialization
     * @param EntityManagerInterface $entityManager the entity manager for data persistence
     * @param TagAwareCacheInterface $cache         the cache service for invalidating cache tags
     *
     * @return Response the HTTP response containing the updated Phone object in JSON format
     *
     * @throws InvalidArgumentException
     */
    #[Route('/phones/{id}', name: 'update_phone', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to update a phone')]
    public function updatePhone(
        Phone $currentPhone,
        Request $request,
        JMSSerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        TagAwareCacheInterface $cache
    ): Response {
        $updatePhone = $serializer->deserialize(
            $request->getContent(),
            Phone::class,
            'json',
            DeserializationContext::create()->setAttribute('target', $currentPhone)
        );

        $entityManager->persist($updatePhone);
        $entityManager->flush();
        $cache->invalidateTags(['phonesCache']);

        $context = SerializationContext::create()->setGroups(['phone:details']);
        $jsonContent = $this->jmsSerializer->serialize($updatePhone, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * Deletes a phone specified by its ID.
     *
     * This method uses the phone ID provided in the URL to search for the phone and delete it from the database.
     * and delete it from the database. An HTTP response with status 204 (No Content)
     * is returned to indicate that the action has been performed successfully. Access to this
     * method is secure and requires the user to have the ADMIN role.
     *
     * @param Phone                  $phone         the Phone entity automatically resolved by Symfony from the ID in the URL
     * @param EntityManagerInterface $entityManager the entity manager for interacting with the database
     *
     * @return Response an HTTP response with status 204 (No Content)
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
