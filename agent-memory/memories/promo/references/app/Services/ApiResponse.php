<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    /**
     * @param  int  $status
     */
    public static function apiResponse($data = null, $meta = [], $message = 'OK', $status = \Illuminate\Http\Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => $status >= 200 && $status < 300 ? true : false,
            'statusCode' => $status,
            'message' => $message,
            'data' => $data,
            'meta' => [],
        ];

        if (! empty($meta)) {
            $response['meta'] = array_merge($response['meta'], $meta);
        }

        if (app()->environment(['local', 'testing', 'development'])) {
            $debug = [
                'memoryusage' => (string) memory_get_usage(),
                'elapstime' => microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : microtime(true)),
                'timestamp' => now()->timestamp,
            ];
            $response['meta'] = array_merge($response['meta'], $debug);
        }

        return response()->json($response, $status);
    }

    /**
     * @param  int  $status
     * @return JsonResponse
     */
    public static function json($data = null, $message = 'OK', $meta = [], $status = Response::HTTP_OK)
    {
        return self::apiResponse($data, $meta, $message, $status);
    }

    /**
     * @return JsonResponse
     */
    public static function paginate($data, array $meta = [], string $message = 'OK', int $status = Response::HTTP_OK)
    {
        if (! method_exists($data->resource, 'total')) {
            return self::apiResponse($data, $meta, $message, $status);
        }

        $meta['attributes'] = self::getPaginationMeta($data);
        $meta['filtered'] = self::getRequestFilters();

        return self::apiResponse($data, $meta, $message, $status);
    }

    public static function cursorPaginate($data, array $meta = [], string $message = 'OK', int $status = Response::HTTP_OK)
    {
        if (! method_exists($data->resource, 'nextCursor')) {
            return self::apiResponse($data, $meta, $message, $status);
        }

        $meta['attributes'] = self::getCursorPaginationMeta($data);
        $meta['filtered'] = self::getCursorRequestFilters();

        return self::apiResponse($data, $meta, $message, $status);
    }

    private static function getCursorPaginationMeta($paginatedData): array
    {
        return [
            'per_page' => $paginatedData->perPage(),
            'next_cursor' => $paginatedData->nextCursor()?->encode(),
            'prev_cursor' => $paginatedData->previousCursor()?->encode(),
            'has_more' => $paginatedData->hasMorePages(),
        ];
    }

    private static function getCursorRequestFilters(): array
    {
        return [
            'load' => (int) request('load', ''),
            'type' => request('type', ''),
            'cursor' => request('cursor', ''),
        ];
    }

    /**
     * Get pagination metadata.
     */
    private static function getPaginationMeta($paginatedData): array
    {
        return [
            'total' => $paginatedData->total(),
            'per_page' => $paginatedData->perPage(),
            'current_page' => $paginatedData->currentPage(),
            'last_page' => $paginatedData->lastPage(),
        ];
    }

    /**
     * Get filtered request parameters.
     */
    private static function getRequestFilters(): array
    {
        return [
            'load' => (int) request('load', ''),
            'q' => request('q', ''),
            'page' => request('page', 1),
            'field' => request('field', 'created_at'),
            'direction' => request('direction', 'desc'),
        ];
    }

    /**
     * @param  $meta
     * @param  int  $status
     * @return JsonResponse
     */
    public static function errorWithData($data = null, $message = 'Error', $status = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        return self::apiResponse($data, [], [], $message, $status);
    }

    public static function created($data = null, $message = 'OK')
    {
        return self::apiResponse($data, $meta = [], $message, Response::HTTP_CREATED);
    }

    public static function accepted($data = null, $message = 'OK')
    {
        return self::apiResponse($data, $meta = [], $message, Response::HTTP_ACCEPTED);
    }

    public static function deleted($message = 'OK')
    {
        return self::apiResponse(null, $meta = [], $message, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param  $data
     */
    public static function validationError($message, $errors)
    {
        return self::apiResponse($errors, $meta = [], $message, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function messageOnly($message = '', $status = Response::HTTP_OK)
    {
        if ($status == 500 && app()->environment(['production', 'prod'])) {
            $message = 'Terjadi kesalahan pada server [1]';
        }
        return self::apiResponse(null, $meta = [], $message, $status);
    }

    public static function devOnly($dev = [])
    {
        return self::apiResponse(null, [], [
            'dev' => $dev,
        ]);
    }

    public static function error($message = 'Error', $status = Response::HTTP_INTERNAL_SERVER_ERROR)
    {
        return self::apiResponse(null, $meta = [], $message, $status);
    }

    /**
     * Basic error response handler
     */
    public static function errorResponse($statusCode, $message, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'statusCode' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function successResponse($statusCode, $message, $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'statusCode' => $statusCode,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function succesResponseCollection($data): array
    {
        return [
            'success' => true,
            'statusCode' => 200,
            'message' => 'OK',
            'data' => $data,
        ];
    }
}
