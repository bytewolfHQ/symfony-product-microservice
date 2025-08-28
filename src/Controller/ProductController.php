<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\ProductService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[OA\Tag(name: 'products', description: 'Product endpoints')]
//#[Security(name: 'Bearer')]
final class ProductController extends AbstractController
{
    public function __construct(
        private ProductService $productService,
        private ValidatorInterface $validator
    ) {}

    #[OA\Get(
        path: '/api/products',
        summary: 'List products',
        tags: ['products'],
        parameters: [
            new OA\QueryParameter(name: 'category', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'minPrice', schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\QueryParameter(name: 'maxPrice', schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\QueryParameter(name: 'isActive', schema: new OA\Schema(type: 'boolean')),
            new OA\QueryParameter(name: 'page', description: '1-based', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\QueryParameter(name: 'limit', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\QueryParameter(name: 'sort', schema: new OA\Schema(type: 'string'), example: 'createdAt,DESC'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: Product::class))
                        ),
                        new OA\Property(
                            property: 'meta',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer'),
                                new OA\Property(property: 'page',  type: 'integer'),
                                new OA\Property(property: 'limit', type: 'integer'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 204, description: 'No Content'),
        ]
    )]
    #[Route(path: '/products', name: 'product_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $onlyActive   = true;
        $defaultLimit = ProductService::DEFAULT_LIMIT;
        $limitUsed    = null;

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
        if ($request->query->get('sort')) {
            [$sort['field'], $sort['direction']] = explode(',', $request->query->get('sort'));
        }

        $hasFilters = !empty($filters);
        $hasPagination = $request->query->get('page') && $request->query->get('limit');
        $getAllProducts = $request->query->get('all');

        if ($hasFilters) {
            $products = $this->productService->getByCriteria(
                $filters,
                $sort,
            );
            $limitUsed = count($products);
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
            $limitUsed = $defaultLimit;
        }

        if (empty($products)) {
            return new JsonResponse([], 204); // No Content
        }

        $data       = array_map([$this, 'map'], $products);
        $totalCount = $this->productService->countAll($onlyActive, $filters ?? []);
        $page       = (int) $request->query->get('page', 1);

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => (int) ($limitUsed ?? $defaultLimit),
            ],
        ], 200, [
            'Content-Type'  => 'application/json',
            'X-Total-Count' => (string) $totalCount,
        ]);
    }

    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Get one product',
        tags: ['products'],
        parameters: [
            new OA\PathParameter(name: 'id', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(ref: new Model(type: Product::class))
            ),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    #[Route('/products/{id<\d+>}', name: 'product_get', methods: ['GET'])]
    public function getOne(int $id): JsonResponse
    {
        $product = $this->productService->get($id);
        if (null === $product) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        return new JsonResponse($this->map($product), 200, ['Content-Type' => 'application/json']);
    }

    /**
     * @throws \JsonException
     */
    #[OA\Post(
        path: '/api/products',
        summary: 'Create product',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name','description','price','category'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Product name'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 19.99),
                    new OA\Property(property: 'category', type: 'string', example: 'Category'),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                ],
                type: 'object'
            )
        ),
        tags: ['products'],
        responses: [
            new OA\Response(response: 201, description: 'Created',
                content: new OA\JsonContent(ref: new Model(type: Product::class))),
            new OA\Response(response: 422, description: 'Invalid request'),
        ]
    )]
    #[Route('/products', name: 'product_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        $errors = $this->validatePayload($payload, partial: false);
        if ($errors) {
            return new JsonResponse(['errors' => $errors], 422);
        }

        $product = $this->productService->create($payload);

        return new JsonResponse(
            $this->map($product),
            201,
            [
                'Content-Type' => 'application/json',
                'Location' => sprintf('/api/product/%d', $product->getId()),
            ]
        );
    }

    #[OA\Put(
        path: '/api/products/{id}',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name','description','price','category'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Product name'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 19.99),
                    new OA\Property(property: 'category', type: 'string', example: 'Category'),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                ],
                type: 'object'
            )
        ),
        tags: ['products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer'))
        ],
        responses: [ new OA\Response(response: 200, description: 'Updated') ]
    )]
    #[Route('/products/{id<\d+>}', name: 'product_put', methods: ['PUT'])]
    public function put(Request $request, int $id): JsonResponse
    {
        return $this->handleWrite($request, $id, partial: false);
    }

    /**
     * @throws \JsonException
     */
    #[OA\Patch(
        path: '/api/products/{id}',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'price', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'category', type: 'string', nullable: true),
                    new OA\Property(property: 'isActive', type: 'boolean', nullable: true),
                ],
                type: 'object'
            )
        ),
        tags: ['products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer'))
        ],
        responses: [ new OA\Response(response: 200, description: 'Patched') ]
    )]
    #[Route(path: '/products/{id<\d+>}', name: 'product_patch', methods: ['PATCH'])]
    public function patch(Request $request, int $id): JsonResponse
    {
        return $this->handleWrite($request, $id, partial: true);
    }

    #[OA\Delete(
        path: '/api/products/{id}',
        summary: 'Delete product',
        security: [['bearer' => []]],
        tags: ['products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true,
                schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    #[Route('/products/{id<\d+>}', name: 'product_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $product = $this->productService->get($id);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }

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

    private function validatePayload(array $data, bool $partial):array
    {
        $base = [
            'name'        => new Assert\NotBlank(),
            'description' => new Assert\NotBlank(),
            'price'       => [new Assert\NotBlank(), new Assert\Type(['numeric', 'type' => 'float'])],
            'category'    => [new Assert\NotBlank(), new Assert\Length(['min' => 1, 'max' => 127])],
            'isActive'    => new Assert\Type(['type' => 'boolean']),
        ];

        $constraints = $partial
            ? new Assert\Collection(fields: $base, allowMissingFields: true)
            : new Assert\Collection(fields: $base, allowMissingFields: false);

        $violations = $this->validator->validate($data, $constraints);
        if (0 === count($violations)) {
            return [];
        }
        $out = [];
        foreach ($violations as $violation) {
            $out[] = ['filed' => (string) $violation->getPropertyPath(), 'message' => $violation->getMessage()];
        }
        return $out;
    }

    private function handleWrite(Request $request, int $id, bool $partial): JsonResponse
    {
        $product = $this->productService->get($id);
        if (!$product) {
            return new JsonResponse(['error' => 'Not found'], 404);
        }

        try {
            $payload = json_decode($request->getContent() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $errors = $this->validatePayload($payload, partial: true);
        if ($errors) {
            return new JsonResponse(['errors' => $errors], 422);
        }

        $this->productService->update($product, $payload, partial: true);
        return new JsonResponse($this->map($product), 200, ['Content-Type' => 'application/json']);
    }
}
