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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class PhoneController extends AbstractController
{
    public function __construct(private readonly PhoneRepository $phoneRepository)
    {
    }

    #[Route('/phones', name: 'list_phones', methods: ['GET'])]
    public function getAllPhone(): JsonResponse
    {
        $phoneList = $this->phoneRepository->findAll();

        return $this->json([
            $phoneList, Response::HTTP_OK, true,
        ]);
    }

    #[Route('/phones/{id}', name: 'detail_phone', methods: ['GET'])]
    public function getDetailPhone(Phone $phone): JsonResponse
    {
        return $this->json([
            $phone, Response::HTTP_OK, true,
        ]);
    }

    #[Route('/phones', name: 'create_phone', methods: ['POST'])]
    public function createPhone(
        Request $request,
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        SerializerInterface $serializer
    ): JsonResponse {
        $phone = $serializer->deserialize($request->getContent(), Phone::class, 'json');
        $entityManager->persist($phone);
        $entityManager->flush();

        $location = $urlGenerator->generate('create_phone', ['id' => $phone->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->json([
            $phone, Response::HTTP_CREATED, ['location' => $location], true,
        ]);
    }

    #[Route('/phones/{id}', name: 'update_phones', methods: ['PUT'])]
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

    #[Route('/phones/{id}', name: 'delete_phone', methods: ['DELETE'])]
    public function deletePhone(Phone $phone, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($phone);
        $entityManager->flush();

        return new Response(
            null, Response::HTTP_NO_CONTENT
        );
    }
}
