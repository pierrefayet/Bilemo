<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PhoneController extends AbstractController
{
    #[Route('/phones', name: 'list_phone', methods: ['GET'])]
    public function getAllCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
        ]);
    }

    #[Route('/phones/{id}', name: 'detail_phone', methods: ['GET'])]
    public function getDetailCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
        ]);
    }

    #[Route('/phones/', name: 'create_phone', methods: ['POST'])]
    public function createCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
        ]);
    }

    #[Route('/phones/{id}', name: 'update_phones', methods: ['PUT'])]
    public function updateCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
        ]);
    }

    #[Route('/phones/{id}', name: 'update_phone', methods: ['DELETE'])]
    public function deleteCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
        ]);
    }
}
