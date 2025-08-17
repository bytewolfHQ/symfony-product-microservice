<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class HealthController
{
    #[Route('/healthz', name: 'healthz', methods: ['GET'])]
    public function healthz(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
