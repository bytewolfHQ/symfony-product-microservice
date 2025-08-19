<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\ProductService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private ProductService $productService,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'product_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $onlyActive = !filter_var($request->query->get('all', 'false'), FILTER_VALIDATE_BOOLEAN);
        $products = $this->productService->getAll($onlyActive);

        $data = array_map(fn (Product $product) => [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'category' => $product->getCategory(),
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
            'isActive' => $product->isActive(),
        ], $products);

        if (!$data) {
            return new JsonResponse([], 204);
        }

        return new JsonResponse(['data' => $data], 200);
    }

    #[Route('/{id}', name: 'product_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $product = $this->productService->getById($id);
        if (!$product) return new JsonResponse(['error' => 'Not found'], 404);

        return new JsonResponse([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'category' => $product->getCategory(),
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
            'isActive' => $product->isActive(),
        ], 200);
    }

    #[Route('', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        foreach (['name', 'description', 'price', 'category'] as $key) {
            if (!isset($data[$key])) return new JsonResponse(['error' => 'Missing field ' . $key], 400);
        }

        $product = $this->productService->create(
            $data['name'],
            $data['description'],
            (float)$data['price'],
            $data['category'],
            (bool)($data['isActive'] ?? true)
        );

        $errors = $this->validator->validate($product);
        if (\count($errors) > 0) {
            return new JsonResponse(['errors' => (string)$errors], 422);
        }

        $response = new JsonResponse([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'category' => $product->getCategory(),
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
            'isActive' => $product->isActive(),
        ], 201);
        $response->headers->set('Location', '/api/products/' . $product->getId());
        return $response;
    }

    #[Route('/{id}', name: 'product_update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $product = $this->productService->getById($id);
        if (!$product) return new JsonResponse(['error' => 'Not found'], 404);

        $data = json_decode($request->getContent(), true) ?? [];
        isset($data['name'])        && $product->setName($data['name']);
        isset($data['description']) && $product->setDescription($data['description']);
        isset($data['price'])       && $product->setPrice((float)$data['price']);
        isset($data['category'])    && $product->setCategory($data['category']);
        isset($data['isActive'])    && $product->setIsActive($data['isActive']);

        $errors = $this->validator->validate($product);
        if (\count($errors) > 0) {
            return new JsonResponse(['errors' => (string)$errors], 422);
        }

        $this->productService->save($product);

        return new JsonResponse([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'category' => $product->getCategory(),
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
            'isActive' => $product->isActive(),
        ], 201);
    }

    #[Route('/{id}', name: 'product_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $product = $this->productService->getById($id);
        if (!$product) return new JsonResponse(['error' => 'Not found'], 404);
        $this->productService->delete($product);
        return new JsonResponse(null, 204);
    }
}
