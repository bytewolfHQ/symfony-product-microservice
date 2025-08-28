<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ApiController
{
    #[Route('/ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return new JsonResponse(['pong' => true]);
    }
}
