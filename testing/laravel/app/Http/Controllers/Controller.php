<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

/**
 * @OA\Info(
 *     title="WordVel API",
 *     version="0.1.0",
 *     description="Laravel-owned API for content stored and edited in WordPress."
 * )
 *
 * @OA\Tag(
 *     name="Pages",
 *     description="Page content assembled from WordPress and returned as DTO-backed block data."
 * )
 */
abstract class Controller extends BaseController
{
}
