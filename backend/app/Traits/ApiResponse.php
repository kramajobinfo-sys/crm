<?php
namespace App\Traits;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message];
        if ($data !== null) $payload['data'] = $data;
        if (!empty($meta)) $payload['meta'] = $meta;
        return response()->json($payload, $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];
        if (!empty($errors)) $payload['errors'] = $errors;
        return response()->json($payload, $status);
    }

    protected function paginated($paginator, $resource = null): JsonResponse
    {
        $data = $resource ? $resource::collection($paginator) : $paginator->items();
        return response()->json([
            'success' => true, 'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
                'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
