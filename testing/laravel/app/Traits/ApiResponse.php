<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    private array $simpleResponse = [
        'status' => true,
    ];

    private array $errorResponse = [
        'status' => false,
        'data' => [],
        'error' => '',
    ];

    public function response(mixed $data, int $code): JsonResponse
    {
        return response()
            ->json($data, $code)
            ->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function resourceResponse(mixed $data, array $additionalData = [], int $code = 200): JsonResponse
    {
        if ($additionalData !== []) {
            $this->simpleResponse = [...$this->simpleResponse, ...$additionalData];
        }

        $this->simpleResponse['data'] = $data;

        return $this->response($this->simpleResponse, $code);
    }

    protected function successResponse(mixed $data = false, int $code = 200): JsonResponse
    {
        if ($data !== false) {
            $this->simpleResponse['data'] = $data;
        }

        return $this->response($this->simpleResponse, $code);
    }

    protected function errorResponse(array|string $error, int $code): JsonResponse
    {
        if (is_array($error)) {
            $this->errorResponse['error'] = $error['message'] ?? reset($error);

            if (app()->environment('local')) {
                $this->errorResponse['debug'] = $error;
            }
        } else {
            $this->errorResponse['error'] = $error;
        }

        return $this->response($this->errorResponse, $code);
    }

    protected function noContentResponse(int $code = 204): JsonResponse
    {
        return $this->response([], $code);
    }
}
