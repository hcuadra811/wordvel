<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Support\WordPress\WordPressPageRepository;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PageController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WordPressPageRepository $pages,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/pages/{slug}",
     *     summary="Get a page by slug",
     *     description="Returns a page resource built by Laravel from WordPress page content and WordVel block DTOs.",
     *     tags={"Pages"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="WordPress page slug.",
     *         example="home",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(response="200", description="Success", @OA\JsonContent(ref="#/components/schemas/PageResponse")),
     *     @OA\Response(response="404", description="Page not found")
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $page = $this->pages->findBySlug($slug)
            ?? throw new NotFoundHttpException('Page not found.');

        return $this->resourceResponse($page);
    }
}
