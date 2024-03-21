<?php

namespace App\Controller;

use App\Entity\Phone;
use App\Repository\PhoneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'api_')]
class PhoneController extends AbstractController
{
    public function __construct()
    {
    }

    /**
     * This code allows you to retrieve all phones.
     */
    #[Route('/phones', name: 'list_phones', requirements: ['page' => '\d+', 'limit' => '\d+'], methods: ['GET'])]
    public function getAllPhone(Request $request, TagAwareCacheInterface $cache, PhoneRepository $phoneRepository): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        if (!is_int($page) || !is_int($limit)) {
            return $this->json(['error' => 'invalid arguments'], Response::HTTP_BAD_REQUEST);
        }

        $idCache = 'getAllPhone'.$page.'-'.$limit;

        $phoneList = $cache->get($idCache, function (ItemInterface $item) use ($phoneRepository, $page, $limit) {
            echo "This element is not in cache ! \n";
            $item->tag('phonesCache');

            return $phoneRepository->findAllPhonesWithPagination($page, $limit);
        });

        return $this->json([
            $phoneList, Response::HTTP_OK, true,
        ]);
    }

    /**
     * This code allows you to retrieve a phone.
     */
    #[Route('/phones/{id}', name: 'detail_phone', methods: ['GET'])]
    public function getDetailPhone(Phone $phone): JsonResponse
    {
        return $this->json([
            $phone, Response::HTTP_OK, true,
        ]);
    }

    /**
     * This code allows you to create a phone.
     */
    #[Route('/phones', name: 'create_phone', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to create a phone')]
    public function createPhone(
        Request $request,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ): JsonResponse {
        $phone = $serializer->deserialize($request->getContent(), Phone::class, 'json');
        $errors = $validator->validate($phone);

        if ($errors->count() > 0) {
            return $this->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $entityManager->persist($phone);
        $entityManager->flush();

        $location = $urlGenerator->generate('api_detail_phone', ['id' => $phone->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json([
            $phone, Response::HTTP_CREATED, ['location' => $location],
        ]);
    }

    /**
     * This code allows you to update a phone.
     */
    #[Route('/phones/{id}', name: 'update_phones', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to update a phone')]
    public function updatePhone(
        Phone $currentPhone,
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager
    ): Response {
        $updatePhone = $serializer->deserialize(
            $request->getContent(),
            Phone::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentPhone]
        );

        $entityManager->persist($updatePhone);
        $entityManager->flush();

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }

    /**
     * This code allows you to delete a phone.
     */
    #[Route('/phones/{id}', name: 'delete_phone', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'you don\'t the necessary rights to delete a phone')]
    public function deletePhone(Phone $phone, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($phone);
        $entityManager->flush();

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }
}
