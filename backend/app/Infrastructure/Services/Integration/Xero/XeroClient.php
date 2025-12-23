<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Integration\Xero;

use App\Domain\Integration\DTOs\ExternalContactDTO;
use App\Domain\Integration\DTOs\ExternalInvoiceDTO;
use App\Domain\Integration\DTOs\ExternalInvoiceLineDTO;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XeroClient
{
    private const API_BASE_URL = 'https://api.xero.com/api.xro/2.0';
    private const CONNECTIONS_URL = 'https://api.xero.com/connections';

    private string $accessToken;
    private string $tenantId;

    public function __construct(string $accessToken, string $tenantId)
    {
        $this->accessToken = $accessToken;
        $this->tenantId = $tenantId;
    }

    // ========================================
    // Connection & Tenant Operations
    // ========================================

    /**
     * Get all connected tenants/organizations
     */
    public function getConnections(): array
    {
        $response = Http::withToken($this->accessToken)
            ->get(self::CONNECTIONS_URL);

        if (!$response->successful()) {
            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Get organization info
     */
    public function getOrganisation(): ?array
    {
        $response = $this->get('/Organisation');

        if (!$response->successful()) {
            return null;
        }

        return $response->json()['Organisations'][0] ?? null;
    }

    // ========================================
    // Contact Operations
    // ========================================

    /**
     * List all contacts with optional pagination and filtering
     */
    public function listContacts(int $page = 1, ?string $modifiedSince = null, bool $includeArchived = false): array
    {
        $params = [
            'page' => $page,
        ];

        if (!$includeArchived) {
            $params['where'] = 'ContactStatus!="ARCHIVED"';
        }

        $headers = [];
        if ($modifiedSince) {
            $headers['If-Modified-Since'] = $modifiedSince;
        }

        $response = $this->get('/Contacts', $params, $headers);

        return $this->parseContactResponse($response);
    }

    /**
     * Get a single contact by ID
     */
    public function getContact(string $contactId): ?ExternalContactDTO
    {
        $response = $this->get("/Contacts/{$contactId}");

        if (!$response->successful() || empty($response->json()['Contacts'])) {
            return null;
        }

        return $this->mapContactToDTO($response->json()['Contacts'][0]);
    }

    /**
     * Create a new contact
     */
    public function createContact(array $data): ?ExternalContactDTO
    {
        $payload = ['Contacts' => [$this->mapDTOToContact($data)]];

        $response = $this->post('/Contacts', $payload);

        if (!$response->successful() || empty($response->json()['Contacts'])) {
            Log::error('Xero: Failed to create contact', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);
            return null;
        }

        return $this->mapContactToDTO($response->json()['Contacts'][0]);
    }

    /**
     * Update an existing contact
     */
    public function updateContact(string $contactId, array $data): ?ExternalContactDTO
    {
        $contactData = $this->mapDTOToContact($data);
        $contactData['ContactID'] = $contactId;

        $payload = ['Contacts' => [$contactData]];

        $response = $this->post('/Contacts', $payload);

        if (!$response->successful() || empty($response->json()['Contacts'])) {
            Log::error('Xero: Failed to update contact', [
                'contact_id' => $contactId,
                'response' => $response->json(),
            ]);
            return null;
        }

        return $this->mapContactToDTO($response->json()['Contacts'][0]);
    }

    /**
     * Search contacts by name or email
     */
    public function searchContacts(string $searchTerm): array
    {
        $response = $this->get('/Contacts', [
            'where' => "Name.Contains(\"{$searchTerm}\") OR EmailAddress.Contains(\"{$searchTerm}\")",
        ]);

        return $this->parseContactResponse($response);
    }

    // ========================================
    // Invoice Operations
    // ========================================

    /**
     * List all invoices with optional pagination and filtering
     */
    public function listInvoices(int $page = 1, ?string $modifiedSince = null, ?string $status = null): array
    {
        $params = [
            'page' => $page,
        ];

        $where = [];
        if ($status) {
            $where[] = "Status=\"{$status}\"";
        }
        if (!empty($where)) {
            $params['where'] = implode(' AND ', $where);
        }

        $headers = [];
        if ($modifiedSince) {
            $headers['If-Modified-Since'] = $modifiedSince;
        }

        $response = $this->get('/Invoices', $params, $headers);

        return $this->parseInvoiceResponse($response);
    }

    /**
     * Get a single invoice by ID
     */
    public function getInvoice(string $invoiceId): ?ExternalInvoiceDTO
    {
        $response = $this->get("/Invoices/{$invoiceId}");

        if (!$response->successful() || empty($response->json()['Invoices'])) {
            return null;
        }

        return $this->mapInvoiceToDTO($response->json()['Invoices'][0]);
    }

    /**
     * Create a new invoice
     */
    public function createInvoice(array $data): ?ExternalInvoiceDTO
    {
        $payload = ['Invoices' => [$this->mapDTOToInvoice($data)]];

        $response = $this->post('/Invoices', $payload);

        if (!$response->successful() || empty($response->json()['Invoices'])) {
            Log::error('Xero: Failed to create invoice', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);
            return null;
        }

        return $this->mapInvoiceToDTO($response->json()['Invoices'][0]);
    }

    /**
     * Update an existing invoice
     */
    public function updateInvoice(string $invoiceId, array $data): ?ExternalInvoiceDTO
    {
        $invoiceData = $this->mapDTOToInvoice($data);
        $invoiceData['InvoiceID'] = $invoiceId;

        $payload = ['Invoices' => [$invoiceData]];

        $response = $this->post('/Invoices', $payload);

        if (!$response->successful() || empty($response->json()['Invoices'])) {
            Log::error('Xero: Failed to update invoice', [
                'invoice_id' => $invoiceId,
                'response' => $response->json(),
            ]);
            return null;
        }

        return $this->mapInvoiceToDTO($response->json()['Invoices'][0]);
    }

    /**
     * Send invoice via email
     */
    public function emailInvoice(string $invoiceId): bool
    {
        $response = $this->post("/Invoices/{$invoiceId}/Email", []);

        return $response->successful();
    }

    /**
     * Get invoice PDF
     */
    public function getInvoicePdf(string $invoiceId): ?string
    {
        $response = Http::withToken($this->accessToken)
            ->withHeaders([
                'Xero-tenant-id' => $this->tenantId,
                'Accept' => 'application/pdf',
            ])
            ->get(self::API_BASE_URL . "/Invoices/{$invoiceId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->body();
    }

    // ========================================
    // Payment Operations
    // ========================================

    /**
     * List payments
     */
    public function listPayments(int $page = 1): array
    {
        $response = $this->get('/Payments', ['page' => $page]);

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['Payments'] ?? [];
    }

    /**
     * Create a payment
     */
    public function createPayment(string $invoiceId, string $accountCode, float $amount, string $date): ?array
    {
        $payload = [
            'Payments' => [
                [
                    'Invoice' => ['InvoiceID' => $invoiceId],
                    'Account' => ['Code' => $accountCode],
                    'Amount' => $amount,
                    'Date' => $date,
                ],
            ],
        ];

        $response = $this->post('/Payments', $payload);

        if (!$response->successful()) {
            Log::error('Xero: Failed to create payment', [
                'response' => $response->json(),
            ]);
            return null;
        }

        return $response->json()['Payments'][0] ?? null;
    }

    // ========================================
    // Account & Item Operations
    // ========================================

    /**
     * List all accounts (Chart of Accounts)
     */
    public function listAccounts(): array
    {
        $response = $this->get('/Accounts');

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['Accounts'] ?? [];
    }

    /**
     * List all items (Products/Services)
     */
    public function listItems(): array
    {
        $response = $this->get('/Items');

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['Items'] ?? [];
    }

    /**
     * Get tax rates
     */
    public function listTaxRates(): array
    {
        $response = $this->get('/TaxRates');

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['TaxRates'] ?? [];
    }

    /**
     * List currencies
     */
    public function listCurrencies(): array
    {
        $response = $this->get('/Currencies');

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['Currencies'] ?? [];
    }

    // ========================================
    // HTTP Methods
    // ========================================

    private function get(string $endpoint, array $params = [], array $extraHeaders = []): Response
    {
        return $this->client($extraHeaders)->get(self::API_BASE_URL . $endpoint, $params);
    }

    private function post(string $endpoint, array $data): Response
    {
        return $this->client()->post(self::API_BASE_URL . $endpoint, $data);
    }

    private function put(string $endpoint, array $data): Response
    {
        return $this->client()->put(self::API_BASE_URL . $endpoint, $data);
    }

    private function client(array $extraHeaders = []): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->withHeaders(array_merge([
                'Xero-tenant-id' => $this->tenantId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], $extraHeaders))
            ->timeout(30);
    }

    // ========================================
    // Data Mapping
    // ========================================

    private function parseContactResponse(Response $response): array
    {
        if (!$response->successful()) {
            return [];
        }

        $contacts = [];
        $data = $response->json()['Contacts'] ?? [];

        foreach ($data as $contact) {
            $contacts[] = $this->mapContactToDTO($contact);
        }

        return $contacts;
    }

    private function parseInvoiceResponse(Response $response): array
    {
        if (!$response->successful()) {
            return [];
        }

        $invoices = [];
        $data = $response->json()['Invoices'] ?? [];

        foreach ($data as $invoice) {
            $invoices[] = $this->mapInvoiceToDTO($invoice);
        }

        return $invoices;
    }

    private function mapContactToDTO(array $contact): ExternalContactDTO
    {
        $addresses = $contact['Addresses'] ?? [];
        $billingAddr = collect($addresses)->firstWhere('AddressType', 'POBOX') ?? [];
        $shippingAddr = collect($addresses)->firstWhere('AddressType', 'STREET') ?? [];

        $phones = $contact['Phones'] ?? [];
        $defaultPhone = collect($phones)->firstWhere('PhoneType', 'DEFAULT') ?? [];
        $mobilePhone = collect($phones)->firstWhere('PhoneType', 'MOBILE') ?? [];

        return new ExternalContactDTO(
            externalId: $contact['ContactID'],
            provider: 'xero',
            displayName: $contact['Name'] ?? null,
            companyName: $contact['Name'] ?? null,
            firstName: $contact['FirstName'] ?? null,
            lastName: $contact['LastName'] ?? null,
            email: $contact['EmailAddress'] ?? null,
            phone: $this->formatXeroPhone($defaultPhone),
            mobile: $this->formatXeroPhone($mobilePhone),
            website: $contact['Website'] ?? null,
            billingAddressLine1: $billingAddr['AddressLine1'] ?? null,
            billingAddressLine2: $billingAddr['AddressLine2'] ?? null,
            billingCity: $billingAddr['City'] ?? null,
            billingState: $billingAddr['Region'] ?? null,
            billingPostalCode: $billingAddr['PostalCode'] ?? null,
            billingCountry: $billingAddr['Country'] ?? null,
            shippingAddressLine1: $shippingAddr['AddressLine1'] ?? null,
            shippingAddressLine2: $shippingAddr['AddressLine2'] ?? null,
            shippingCity: $shippingAddr['City'] ?? null,
            shippingState: $shippingAddr['Region'] ?? null,
            shippingPostalCode: $shippingAddr['PostalCode'] ?? null,
            shippingCountry: $shippingAddr['Country'] ?? null,
            taxNumber: $contact['TaxNumber'] ?? null,
            currency: $contact['DefaultCurrency'] ?? null,
            notes: null,
            isActive: ($contact['ContactStatus'] ?? 'ACTIVE') === 'ACTIVE',
            metadata: [
                'contact_number' => $contact['ContactNumber'] ?? null,
                'account_number' => $contact['AccountNumber'] ?? null,
                'is_supplier' => $contact['IsSupplier'] ?? false,
                'is_customer' => $contact['IsCustomer'] ?? false,
                'balance' => $contact['Balances']['AccountsReceivable']['Outstanding'] ?? 0,
            ],
            createdAt: isset($contact['UpdatedDateUTC'])
                ? $this->parseXeroDate($contact['UpdatedDateUTC'])
                : null,
            updatedAt: isset($contact['UpdatedDateUTC'])
                ? $this->parseXeroDate($contact['UpdatedDateUTC'])
                : null,
        );
    }

    private function mapDTOToContact(array $data): array
    {
        $contact = [];

        if (isset($data['display_name']) || isset($data['company_name'])) {
            $contact['Name'] = $data['display_name'] ?? $data['company_name'];
        }
        if (isset($data['first_name'])) {
            $contact['FirstName'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $contact['LastName'] = $data['last_name'];
        }
        if (isset($data['email'])) {
            $contact['EmailAddress'] = $data['email'];
        }
        if (isset($data['website'])) {
            $contact['Website'] = $data['website'];
        }
        if (isset($data['tax_number'])) {
            $contact['TaxNumber'] = $data['tax_number'];
        }

        // Phones
        $phones = [];
        if (isset($data['phone'])) {
            $phones[] = [
                'PhoneType' => 'DEFAULT',
                'PhoneNumber' => $data['phone'],
            ];
        }
        if (isset($data['mobile'])) {
            $phones[] = [
                'PhoneType' => 'MOBILE',
                'PhoneNumber' => $data['mobile'],
            ];
        }
        if (!empty($phones)) {
            $contact['Phones'] = $phones;
        }

        // Addresses
        $addresses = [];
        if (isset($data['billing_address_line1']) || isset($data['billing_city'])) {
            $addresses[] = array_filter([
                'AddressType' => 'POBOX',
                'AddressLine1' => $data['billing_address_line1'] ?? null,
                'AddressLine2' => $data['billing_address_line2'] ?? null,
                'City' => $data['billing_city'] ?? null,
                'Region' => $data['billing_state'] ?? null,
                'PostalCode' => $data['billing_postal_code'] ?? null,
                'Country' => $data['billing_country'] ?? null,
            ]);
        }
        if (isset($data['shipping_address_line1']) || isset($data['shipping_city'])) {
            $addresses[] = array_filter([
                'AddressType' => 'STREET',
                'AddressLine1' => $data['shipping_address_line1'] ?? null,
                'AddressLine2' => $data['shipping_address_line2'] ?? null,
                'City' => $data['shipping_city'] ?? null,
                'Region' => $data['shipping_state'] ?? null,
                'PostalCode' => $data['shipping_postal_code'] ?? null,
                'Country' => $data['shipping_country'] ?? null,
            ]);
        }
        if (!empty($addresses)) {
            $contact['Addresses'] = $addresses;
        }

        return $contact;
    }

    private function mapInvoiceToDTO(array $invoice): ExternalInvoiceDTO
    {
        $lineItems = [];
        foreach ($invoice['LineItems'] ?? [] as $line) {
            $lineItems[] = new ExternalInvoiceLineDTO(
                externalId: $line['LineItemID'] ?? null,
                description: $line['Description'] ?? null,
                quantity: $line['Quantity'] ?? null,
                unitPrice: $line['UnitAmount'] ?? null,
                amount: $line['LineAmount'] ?? null,
                taxAmount: $line['TaxAmount'] ?? null,
                taxCode: $line['TaxType'] ?? null,
                accountCode: $line['AccountCode'] ?? null,
                itemCode: $line['ItemCode'] ?? null,
                discountAmount: isset($line['DiscountRate']) ? ($line['LineAmount'] * $line['DiscountRate'] / 100) : null,
                discountPercent: $line['DiscountRate'] ?? null,
            );
        }

        return new ExternalInvoiceDTO(
            externalId: $invoice['InvoiceID'],
            provider: 'xero',
            externalCustomerId: $invoice['Contact']['ContactID'] ?? '',
            invoiceNumber: $invoice['InvoiceNumber'] ?? null,
            reference: $invoice['Reference'] ?? null,
            status: $this->mapXeroInvoiceStatus($invoice['Status'] ?? 'DRAFT'),
            currency: $invoice['CurrencyCode'] ?? null,
            subtotal: $invoice['SubTotal'] ?? null,
            taxAmount: $invoice['TotalTax'] ?? null,
            total: $invoice['Total'] ?? null,
            amountDue: $invoice['AmountDue'] ?? null,
            amountPaid: $invoice['AmountPaid'] ?? null,
            invoiceDate: isset($invoice['DateString'])
                ? new \DateTimeImmutable($invoice['DateString'])
                : null,
            dueDate: isset($invoice['DueDateString'])
                ? new \DateTimeImmutable($invoice['DueDateString'])
                : null,
            terms: null,
            notes: null,
            lineItems: array_map(fn($l) => $l->toArray(), $lineItems),
            metadata: [
                'type' => $invoice['Type'] ?? null,
                'contact_name' => $invoice['Contact']['Name'] ?? null,
                'sent_to_contact' => $invoice['SentToContact'] ?? false,
                'has_attachments' => $invoice['HasAttachments'] ?? false,
            ],
            createdAt: isset($invoice['UpdatedDateUTC'])
                ? $this->parseXeroDate($invoice['UpdatedDateUTC'])
                : null,
            updatedAt: isset($invoice['UpdatedDateUTC'])
                ? $this->parseXeroDate($invoice['UpdatedDateUTC'])
                : null,
        );
    }

    private function mapDTOToInvoice(array $data): array
    {
        $invoice = [
            'Type' => 'ACCREC', // Accounts Receivable (Sales Invoice)
            'Contact' => ['ContactID' => $data['external_customer_id']],
        ];

        if (isset($data['invoice_number'])) {
            $invoice['InvoiceNumber'] = $data['invoice_number'];
        }
        if (isset($data['reference'])) {
            $invoice['Reference'] = $data['reference'];
        }
        if (isset($data['invoice_date'])) {
            $invoice['Date'] = $data['invoice_date'];
        }
        if (isset($data['due_date'])) {
            $invoice['DueDate'] = $data['due_date'];
        }
        if (isset($data['currency'])) {
            $invoice['CurrencyCode'] = $data['currency'];
        }

        // Line items
        if (!empty($data['line_items'])) {
            $invoice['LineItems'] = [];
            foreach ($data['line_items'] as $lineItem) {
                $line = [
                    'Description' => $lineItem['description'] ?? '',
                    'Quantity' => $lineItem['quantity'] ?? 1,
                    'UnitAmount' => $lineItem['unit_price'] ?? 0,
                ];

                if (isset($lineItem['account_code'])) {
                    $line['AccountCode'] = $lineItem['account_code'];
                }
                if (isset($lineItem['item_code'])) {
                    $line['ItemCode'] = $lineItem['item_code'];
                }
                if (isset($lineItem['tax_code'])) {
                    $line['TaxType'] = $lineItem['tax_code'];
                }
                if (isset($lineItem['discount_percent'])) {
                    $line['DiscountRate'] = $lineItem['discount_percent'];
                }

                $invoice['LineItems'][] = $line;
            }
        }

        return $invoice;
    }

    private function mapXeroInvoiceStatus(string $status): string
    {
        return match ($status) {
            'DRAFT' => 'draft',
            'SUBMITTED' => 'submitted',
            'AUTHORISED' => 'sent',
            'PAID' => 'paid',
            'VOIDED' => 'voided',
            'DELETED' => 'deleted',
            default => 'draft',
        };
    }

    private function formatXeroPhone(array $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $parts = array_filter([
            $phone['PhoneCountryCode'] ?? null,
            $phone['PhoneAreaCode'] ?? null,
            $phone['PhoneNumber'] ?? null,
        ]);

        return !empty($parts) ? implode(' ', $parts) : null;
    }

    private function parseXeroDate(string $dateString): ?\DateTimeImmutable
    {
        // Xero returns dates in format: /Date(1234567890000+0000)/
        if (preg_match('/\/Date\((\d+)([+-]\d{4})?\)\//', $dateString, $matches)) {
            $timestamp = (int) ($matches[1] / 1000);
            return (new \DateTimeImmutable())->setTimestamp($timestamp);
        }

        try {
            return new \DateTimeImmutable($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }
}
