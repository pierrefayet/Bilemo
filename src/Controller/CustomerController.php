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
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Psr\Cache\InvalidArgumentException;
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
        private readonly SerializerInterface $jmsSerializer
    ) {
    }

    /**
     * Retrieves customer details.
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns the details of a customer",
     *
     *     @OA\JsonContent(
     *         type="object",
     *
     *         @OA\Property(property="data", type="array", @OA\Items(ref=@Model(type=Customer::class, groups={"getDetailCustomer"})))
     *     )
     * )
     *
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     description="The ID of the customer to retrieve",
     *
     *     @OA\Schema(type="integer")
     * )
     *
     * @OA\Tag(name="Customer")
     *
     * @Security(name="Bearer")
     */
    #[Route('/customers/{id}', name: 'detail_customer', methods: ['GET'])]
    public function getDetailCustomer(Customer $customer): Response
    {
        $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
        $jsonContent = $this->jmsSerializer->serialize($customer, 'json', $context);

        return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
    }

    /**
     * This method retrieves all customers.
     *
     * @OA\Response(
     *     response=200,
     *     description="Return to customer list",
     *
     *     @OA\JsonContent(
     *        type="array",
     *
     *        @OA\Items(ref=@Model(type=Customer::class, groups={'customer:details', 'user:details'}))
     *     )
     * )
     *
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="The page you want to retrieve",
     *
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="The number of elements to be retrieved",
     *
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Tag(name="Customer")
     *
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @param CustomerRepository $customerRepository
     * @return Response
     * @throws InvalidArgumentException
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

    /**
     * Creates a new client and associates it with an existing user.
     *
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
     * //
     * #[Route('/customers/{id}', name: 'detail_customer', methods: ['GET'])]
     * public function getDetailCustomer(Customer $customer): Response
     * {
     * $context = SerializationContext::create()->setGroups(['customer:details', 'user:details']);
     * $jsonContent = $this->jmsSerializer->serialize($customer, 'json', $context);
     *
     * return new Response($jsonContent, Response::HTTP_OK, ['Content-Type' => 'application/json']);
     * }
     * /**
     * This code allows you to create a customer.
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

    /**
     * Updates an existing customer with the information provided in the request.
     *
     * This method updates the details of a specific customer identified by its ID in the URL.
     * It deserializes the request body into a Customer entity and associates a User specified by userId.
     * If the specified user does not exist, an error is returned.
     * Cache tags associated with customers are invalidated after the update.
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
     * "email": "tgilles@traore.fr",
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

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Deletes a customer specified by its ID.
     *
     * This method deletes an existing customer from the database.
     * The client ID is provided in the request URL. Once the client has been deleted, the method returns an
     * HTTP response with status 204 to indicate that the action has been performed successfully.
     * If the ID provided in the URL doesn't correspond to any client, Symfony will generate
     * automatically generate a 404 response thanks to the param converter mechanism.
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
