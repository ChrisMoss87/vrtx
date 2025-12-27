<?php

declare(strict_types=1);

namespace App\Services\Document;

class DocumentTemplateVariable
{
    public static function getGroupedVariables(): array
    {
        return [
            'contact' => [
                'name' => 'Contact full name',
                'first_name' => 'Contact first name',
                'last_name' => 'Contact last name',
                'email' => 'Contact email',
                'phone' => 'Contact phone',
                'company' => 'Contact company name',
                'title' => 'Contact job title',
            ],
            'company' => [
                'name' => 'Company name',
                'address' => 'Company address',
                'city' => 'Company city',
                'state' => 'Company state',
                'country' => 'Company country',
                'phone' => 'Company phone',
                'website' => 'Company website',
            ],
            'deal' => [
                'name' => 'Deal name',
                'amount' => 'Deal amount',
                'stage' => 'Deal stage',
                'close_date' => 'Expected close date',
                'probability' => 'Win probability',
            ],
            'quote' => [
                'number' => 'Quote number',
                'date' => 'Quote date',
                'valid_until' => 'Valid until date',
                'subtotal' => 'Subtotal amount',
                'tax' => 'Tax amount',
                'total' => 'Total amount',
            ],
            'invoice' => [
                'number' => 'Invoice number',
                'date' => 'Invoice date',
                'due_date' => 'Due date',
                'subtotal' => 'Subtotal amount',
                'tax' => 'Tax amount',
                'total' => 'Total amount',
                'status' => 'Invoice status',
            ],
            'user' => [
                'name' => 'Current user name',
                'email' => 'Current user email',
                'title' => 'Current user title',
                'phone' => 'Current user phone',
            ],
            'date' => [
                'today' => 'Today\'s date',
                'current_month' => 'Current month',
                'current_year' => 'Current year',
            ],
        ];
    }
}
