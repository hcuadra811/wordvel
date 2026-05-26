<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\WordvelSiteContent;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SiteController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly WordvelSiteContent $site,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/site",
     *     summary="Get site chrome",
     *     description="Returns theme options and WordPress menus needed by the React shell around page content.",
     *     tags={"Site"},
     *     @OA\Response(response="200", description="Success", @OA\JsonContent(ref="#/components/schemas/SiteResponse"))
     * )
     */
    public function show(): JsonResponse
    {
        return $this->resourceResponse($this->site->site());
    }
}
