<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Trait for consistent API responses across all controllers.
 *
 * Provides standardized success and error response methods
 * to ensure consistent API format throughout the application.
 */
trait ApiResponse
{
    /**
     * Return a success response with data.
     */
    protected function successResponse(
        mixed $data,
        string $message = null,
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        $response = ['data' => $data];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a success response for a single item.
     */
    protected function itemResponse(
        mixed $item,
        string $key = 'data',
        string $message = null,
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        $response = [
            'success' => true,
            $key => $item,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a success response for a list of items.
     */
    protected function listResponse(
        mixed $items,
        string $key = 'data',
        array $meta = null
    ): JsonResponse {
        $response = [
            'success' => true,
            $key => $items,
        ];

        if ($meta !== null) {
            $response['meta'] = $meta;
        }

        return response()->json($response);
    }

    /**
     * Return a paginated response.
     */
    protected function paginatedResponse(
        mixed $paginator,
        string $key = 'data'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            $key => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Return a created response (201).
     */
    protected function createdResponse(
        mixed $data,
        string $message = 'Resource created successfully',
        string $key = 'data'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            $key => $data,
        ], Response::HTTP_CREATED);
    }

    /**
     * Return a no content response (204).
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return a message-only success response.
     */
    protected function messageResponse(
        string $message,
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Return an error response.
     *
     * IMPORTANT: This method does NOT expose sensitive error details.
     * The actual exception details are logged, but a generic message
     * is returned to the client.
     */
    protected function errorResponse(
        string $message,
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $errors = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Handle an exception and return a safe error response.
     *
     * This method logs the full exception details but returns
     * only a generic message to the client, preventing sensitive
     * information exposure.
     */
    protected function handleException(
        Throwable $exception,
        string $userMessage = 'An error occurred while processing your request',
        string $logContext = null,
        int $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR
    ): JsonResponse {
        // Log the full exception with context
        Log::error($logContext ?? 'API Exception', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Return a safe response without sensitive details
        return $this->errorResponse($userMessage, $statusCode);
    }

    /**
     * Return a not found response.
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a validation error response.
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Return an unauthorized response.
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden response.
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a conflict response (409).
     */
    protected function conflictResponse(string $message = 'Resource conflict'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_CONFLICT);
    }

    /**
     * Return a bad request response (400).
     */
    protected function badRequestResponse(string $message = 'Bad request'): JsonResponse
    {
        return $this->errorResponse($message, Response::HTTP_BAD_REQUEST);
    }
}
