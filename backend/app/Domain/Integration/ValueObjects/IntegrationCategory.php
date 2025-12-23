<?php

declare(strict_types=1);

namespace App\Domain\Integration\ValueObjects;

enum IntegrationCategory: string
{
    case ACCOUNTING = 'accounting';
    case PAYMENTS = 'payments';
    case ESIGNATURE = 'esignature';
    case COMMUNICATION = 'communication';
    case CALENDAR = 'calendar';
    case MARKETING = 'marketing';
    case TELEPHONY = 'telephony';
    case STORAGE = 'storage';
    case ECOMMERCE = 'ecommerce';
    case SUPPORT = 'support';
    case PROJECT_MANAGEMENT = 'project_management';
    case LEAD_GENERATION = 'lead_generation';

    public function label(): string
    {
        return match ($this) {
            self::ACCOUNTING => 'Accounting & Finance',
            self::PAYMENTS => 'Payment Processing',
            self::ESIGNATURE => 'E-Signature',
            self::COMMUNICATION => 'Communication',
            self::CALENDAR => 'Calendar & Scheduling',
            self::MARKETING => 'Marketing Automation',
            self::TELEPHONY => 'Telephony & VoIP',
            self::STORAGE => 'File Storage',
            self::ECOMMERCE => 'E-Commerce',
            self::SUPPORT => 'Customer Support',
            self::PROJECT_MANAGEMENT => 'Project Management',
            self::LEAD_GENERATION => 'Lead Generation',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACCOUNTING => 'calculator',
            self::PAYMENTS => 'credit-card',
            self::ESIGNATURE => 'file-signature',
            self::COMMUNICATION => 'message-square',
            self::CALENDAR => 'calendar',
            self::MARKETING => 'megaphone',
            self::TELEPHONY => 'phone',
            self::STORAGE => 'folder',
            self::ECOMMERCE => 'shopping-cart',
            self::SUPPORT => 'headphones',
            self::PROJECT_MANAGEMENT => 'kanban',
            self::LEAD_GENERATION => 'user-plus',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ACCOUNTING => 'Sync invoices, contacts, and payments with accounting software',
            self::PAYMENTS => 'Process payments and manage subscriptions',
            self::ESIGNATURE => 'Send documents for electronic signatures',
            self::COMMUNICATION => 'Team messaging and video conferencing',
            self::CALENDAR => 'Sync meetings and manage scheduling',
            self::MARKETING => 'Email marketing and automation',
            self::TELEPHONY => 'Voice calls and SMS messaging',
            self::STORAGE => 'Cloud file storage and sharing',
            self::ECOMMERCE => 'E-commerce platform integration',
            self::SUPPORT => 'Help desk and customer support',
            self::PROJECT_MANAGEMENT => 'Task and project tracking',
            self::LEAD_GENERATION => 'Lead enrichment and prospecting',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::ACCOUNTING => 1,
            self::PAYMENTS => 2,
            self::ESIGNATURE => 3,
            self::CALENDAR => 4,
            self::COMMUNICATION => 5,
            self::MARKETING => 6,
            self::TELEPHONY => 7,
            self::STORAGE => 8,
            self::ECOMMERCE => 9,
            self::SUPPORT => 10,
            self::PROJECT_MANAGEMENT => 11,
            self::LEAD_GENERATION => 12,
        };
    }
}
