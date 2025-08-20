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
        $onlyActive = true;
        $defaultLimit = ProductService::DEFAULT_LIMIT;
        $limitUsed = null;

        $filters = [];
        if (null !== $request->query->get('category')) {
            $filters['category'] = $request->query->get('category');
        }
        if (null !== $request->query->get('minPrice')) {
            $filters['minPrice'] = (float) $request->query->get('minPrice');
        }
        if (null !== $request->query->get('maxPrice')) {
            $filters['maxPrice'] = (float) $request->query->get('maxPrice');
        }
        if (null !== $request->query->get('isActive')) {
            $filters['isActive'] = filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN);
        }

        $sort = ['field' => 'createdAt','direction' => 'DESC'];
        if ($request->query->get('sort')) [$sort['field'],$sort['direction']] = explode(',',$request->query->get('sort'));

        $hasFilters = !empty($filters);
        $hasPagination = $request->get('page') && $request->query->get('limit');
        $getAllProducts = $request->query->get('all');

        if ($hasFilters) {
            $items = $filters ? $this->productService->getByCriteria($filters, $sort) : $this->productService->getAll(true);
            $products = $this->productService->getByCriteria(
                $filters,
                $sort,
            );
        } elseif ($hasPagination) {
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = max(1, (int) $request->query->get('limit', $defaultLimit));
            $products = $this->productService->getPaginated(true, $page, $limit);
            $limitUsed = $limit;
        } elseif ($getAllProducts) {
            $onlyActive = false;
            $products = $this->productService->getAll(false);
            $limitUsed = $defaultLimit;
        } else {
            $products = $this->productService->getAll();
        }

        if (empty($products)) {
            return new JsonResponse([], 204); // No Content
        }

        $data = array_map([$this, 'map'], $products);
        $totalCount = $this->productService->countAll($onlyActive, $filters ?? []);
        $page = $request->query->get('page', 1);

        $response = new JsonResponse([
            'data' => $data,
            'meta' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limitUsed,
            ],
        ]);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('X-Total-Count', (int) $totalCount);

        return $response;
    }

    #[Route('/{id}', name: 'product_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $product = $this->productService->getById($id);
        return $product ? $this->json($this->map($product)) : $this->json(['error' => 'product not found'], 404);
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

        return $this->json($this->map($product), 201);
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

        return $this->json($this->map($product), 201);
    }

    #[Route('/{id}', name: 'product_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $product = $this->productService->getById($id);
        if (!$product) return new JsonResponse(['error' => 'Not found'], 404);
        $this->productService->delete($product);
        return new JsonResponse(null, 204);
    }

    private function map(Product $product): array
    {
        return [
            'id'=>$product->getId(),'name'=>$product->getName(),'description'=>$product->getDescription(),
            'price'=>$product->getPrice(),'category'=>$product->getCategory(),
            'createdAt'=>$product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt'=>$product->getUpdatedAt()->format(DATE_ATOM),
            'isActive'=>$product->isActive(),
        ];
    }
}
