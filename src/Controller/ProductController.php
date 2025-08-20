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
        $filters = array_filter([
            'category' => $request->get('category'),
            'minPrice' => $request->get('minPrice'),
            'maxPrice' => $request->get('maxPrice'),
            'isActive' => $request->get('isActive') ? filter_var($request->get('isActive'), FILTER_VALIDATE_BOOLEAN) : null,
        ], fn($v)=>$v!==null && $v!=='');
        $sort = ['field'=>'createdAt','direction'=>'DESC'];
        if ($request->query->has('sort')) [$sort['field'],$sort['direction']] = explode(',',$request->query->get('sort'));

        if ($request->get('page') && $request->query->get('limit')) {
            $page = max(1, (int) $request->query->get('page'));
            $limit = max(1, (int) $request->query->get('limit'));
            $data = $this->productService->getPaginated(true, $page, $limit);
            $total = $this->productService->countAll(true, $filters);
            return $this->json([
                'data' => array_map([$this, 'map'], $data),
                'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit],
            ]);
        }

        $items = $filters ? $this->productService->getByCriteria($filters, $sort) : $this->productService->getAll(true);
        if (!$items) return new JsonResponse([], 204);

        $response = $this->json(array_map([$this, 'map'], $items));
        $response->headers->set('X-Total-Count', (string)$this->productService->countAll(true, $filters));
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
