<?php

declare(strict_types=1);

namespace App\Services\Workflow\Actions;

use Illuminate\Support\Facades\Http;

/**
 * Action to call an external webhook.
 */
class WebhookAction implements ActionInterface
{
    public function execute(array $config, array $context): array
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];
        $bodyType = $config['body_type'] ?? 'json';

        if (!$url) {
            throw new \InvalidArgumentException('Webhook URL is required');
        }

        // Build request body
        $body = $this->buildBody($config['body'] ?? [], $bodyType, $context);

        // Make HTTP request
        $request = Http::timeout(30)->withHeaders($headers);

        $response = match ($method) {
            'GET' => $request->get($url, $body),
            'POST' => $bodyType === 'json'
                ? $request->post($url, $body)
                : $request->asForm()->post($url, $body),
            'PUT' => $request->put($url, $body),
            'PATCH' => $request->patch($url, $body),
            'DELETE' => $request->delete($url, $body),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if ($response->failed()) {
            throw new \RuntimeException(
                "Webhook failed with status {$response->status()}: {$response->body()}"
            );
        }

        return [
            'success' => true,
            'status_code' => $response->status(),
            'response' => $response->json() ?? $response->body(),
        ];
    }

    public static function getConfigSchema(): array
    {
        return [
            'fields' => [
                [
                    'name' => 'url',
                    'label' => 'Webhook URL',
                    'type' => 'text',
                    'required' => true,
                    'supports_variables' => true,
                ],
                [
                    'name' => 'method',
                    'label' => 'HTTP Method',
                    'type' => 'select',
                    'required' => true,
                    'options' => [
                        ['value' => 'POST', 'label' => 'POST'],
                        ['value' => 'GET', 'label' => 'GET'],
                        ['value' => 'PUT', 'label' => 'PUT'],
                        ['value' => 'PATCH', 'label' => 'PATCH'],
                        ['value' => 'DELETE', 'label' => 'DELETE'],
                    ],
                    'default' => 'POST',
                ],
                [
                    'name' => 'headers',
                    'label' => 'Headers',
                    'type' => 'key_value',
                    'required' => false,
                ],
                [
                    'name' => 'body_type',
                    'label' => 'Body Type',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'json', 'label' => 'JSON'],
                        ['value' => 'form', 'label' => 'Form Data'],
                    ],
                    'default' => 'json',
                ],
                [
                    'name' => 'body',
                    'label' => 'Request Body',
                    'type' => 'json_editor',
                    'required' => false,
                    'supports_variables' => true,
                ],
            ],
        ];
    }

    public function validate(array $config): array
    {
        $errors = [];

        if (empty($config['url'])) {
            $errors['url'] = 'Webhook URL is required';
        } elseif (!filter_var($config['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Invalid URL format';
        }

        return $errors;
    }

    protected function buildBody(mixed $body, string $bodyType, array $context): array
    {
        if (is_string($body)) {
            // Interpolate variables
            $body = preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
                $path = trim($matches[1]);
                return $this->getValueByPath($context, $path) ?? '';
            }, $body);

            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : ['data' => $body];
        }

        if (is_array($body)) {
            return $this->interpolateArray($body, $context);
        }

        // Default: send record data
        return $context['record']['data'] ?? [];
    }

    protected function interpolateArray(array $data, array $context): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->interpolateArray($value, $context);
            } elseif (is_string($value)) {
                $result[$key] = preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($context) {
                    $path = trim($matches[1]);
                    return $this->getValueByPath($context, $path) ?? '';
                }, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    protected function getValueByPath(array $context, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }
}
