<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Domain\User\Entities\User;
use Illuminate\Support\Facades\Auth;

class MergeFieldService
{
    public function merge(string $content, array $data): string
    {
        // Replace merge fields with actual values
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($data) {
            $field = trim($matches[1]);
use Illuminate\Support\Facades\DB;

            // Handle formatting functions
            if (str_contains($field, '|')) {
                [$field, $format] = explode('|', $field, 2);
                $value = data_get($data, trim($field), '');
                return $this->applyFormat($value, trim($format));
            }

            return data_get($data, $field, '');
        }, $content);
    }

    public function getRecordData(string $recordType, int $recordId): array
    {
        $data = [];

        // Get module record data
        $record = ModuleRecord::with(['module'])->find($recordId);

        if ($record) {
            $data['record'] = $record->data ?? [];
            $data['record']['id'] = $record->id;
            $data['record']['created_at'] = $record->created_at?->format('Y-m-d');
            $data['record']['updated_at'] = $record->updated_at?->format('Y-m-d');
        }

        // Get contact data if linked
        if ($contactId = $data['record']['contact_id'] ?? null) {
            $contact = DB::table('module_records')->where('id', $contactId)->first();
            if ($contact) {
                $data['contact'] = $contact->data ?? [];
                $data['contact']['id'] = $contact->id;
                $data['contact']['name'] = ($contact->data['first_name'] ?? '') . ' ' . ($contact->data['last_name'] ?? '');
            }
        }

        // Get company data if linked
        if ($companyId = $data['record']['company_id'] ?? null) {
            $company = DB::table('module_records')->where('id', $companyId)->first();
            if ($company) {
                $data['company'] = $company->data ?? [];
                $data['company']['id'] = $company->id;
            }
        }

        // Get deal data if applicable
        if ($dealId = $data['record']['deal_id'] ?? null) {
            $deal = DB::table('module_records')->where('id', $dealId)->first();
            if ($deal) {
                $data['deal'] = $deal->data ?? [];
                $data['deal']['id'] = $deal->id;
            }
        }

        // Get current user data
        $user = Auth::user();
        if ($user) {
            $data['user'] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'title' => $user->title ?? '',
            ];
        }

        // Add system variables
        $data['system'] = [
            'date' => now()->format('Y-m-d'),
            'datetime' => now()->format('Y-m-d H:i:s'),
            'date_long' => now()->format('F j, Y'),
            'year' => now()->format('Y'),
            'month' => now()->format('F'),
            'day' => now()->format('j'),
        ];

        return $data;
    }

    public function getSampleData(): array
    {
        return [
            'record' => [
                'id' => 12345,
                'name' => 'Sample Record',
                'created_at' => now()->format('Y-m-d'),
            ],
            'contact' => [
                'id' => 1,
                'name' => 'John Doe',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+1 (555) 123-4567',
                'title' => 'CEO',
            ],
            'company' => [
                'id' => 1,
                'name' => 'Acme Corporation',
                'website' => 'https://acme.example.com',
                'phone' => '+1 (555) 987-6543',
                'address' => '123 Main Street',
                'city' => 'New York',
                'state' => 'NY',
                'zip' => '10001',
                'country' => 'USA',
            ],
            'deal' => [
                'id' => 1,
                'name' => 'Enterprise Deal',
                'amount' => 50000,
                'stage' => 'Negotiation',
                'close_date' => now()->addDays(30)->format('Y-m-d'),
            ],
            'user' => [
                'id' => 1,
                'name' => 'Sales Rep',
                'email' => 'sales@company.com',
                'phone' => '+1 (555) 555-5555',
                'title' => 'Account Executive',
            ],
            'system' => [
                'date' => now()->format('Y-m-d'),
                'datetime' => now()->format('Y-m-d H:i:s'),
                'date_long' => now()->format('F j, Y'),
                'year' => now()->format('Y'),
                'month' => now()->format('F'),
                'day' => now()->format('j'),
            ],
        ];
    }

    protected function applyFormat($value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($format) {
            'uppercase' => strtoupper((string) $value),
            'lowercase' => strtolower((string) $value),
            'capitalize' => ucwords(strtolower((string) $value)),
            'currency' => '$' . number_format((float) $value, 2),
            'currency_eur' => '€' . number_format((float) $value, 2),
            'currency_gbp' => '£' . number_format((float) $value, 2),
            'number' => number_format((float) $value),
            'percent' => number_format((float) $value, 1) . '%',
            'date' => $this->formatDate($value, 'Y-m-d'),
            'date_long' => $this->formatDate($value, 'F j, Y'),
            'date_short' => $this->formatDate($value, 'm/d/Y'),
            'time' => $this->formatDate($value, 'g:i A'),
            'datetime' => $this->formatDate($value, 'F j, Y g:i A'),
            default => (string) $value,
        };
    }

    protected function formatDate($value, string $format): string
    {
        try {
            $date = is_string($value) ? new \DateTime($value) : $value;
            return $date->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
