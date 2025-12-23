<?php

declare(strict_types=1);

namespace App\Infrastructure\Services\Integration\QuickBooks;

use App\Domain\Integration\DTOs\ExternalContactDTO;
use App\Domain\Integration\DTOs\ExternalInvoiceDTO;
use App\Domain\Integration\DTOs\ExternalInvoiceLineDTO;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuickBooksClient
{
    private const API_BASE_URL = 'https://quickbooks.api.intuit.com/v3/company';
    private const SANDBOX_BASE_URL = 'https://sandbox-quickbooks.api.intuit.com/v3/company';

    private string $accessToken;
    private string $realmId;
    private bool $sandbox;

    public function __construct(string $accessToken, string $realmId, bool $sandbox = false)
    {
        $this->accessToken = $accessToken;
        $this->realmId = $realmId;
        $this->sandbox = $sandbox;
    }

    // ========================================
    // Customer Operations
    // ========================================

    /**
     * List all customers with optional pagination
     */
    public function listCustomers(int $startPosition = 1, int $maxResults = 100, ?string $modifiedSince = null): array
    {
        $query = "SELECT * FROM Customer";

        if ($modifiedSince) {
            $query .= " WHERE MetaData.LastUpdatedTime > '{$modifiedSince}'";
        }

        $query .= " STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";

        $response = $this->query($query);

        return $this->parseCustomerResponse($response);
    }

    /**
     * Get a single customer by ID
     */
    public function getCustomer(string $customerId): ?ExternalContactDTO
    {
        $response = $this->get("/customer/{$customerId}");

        if (!$response->successful() || !isset($response->json()['Customer'])) {
            return null;
        }

        return $this->mapCustomerToDTO($response->json()['Customer']);
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): ?ExternalContactDTO
    {
        $payload = $this->mapDTOToCustomer($data);

        $response = $this->post('/customer', $payload);

        if (!$response->successful() || !isset($response->json()['Customer'])) {
            Log::error('QuickBooks: Failed to create customer', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);
            return null;
        }

        return $this->mapCustomerToDTO($response->json()['Customer']);
    }

    /**
     * Update an existing customer
     */
    public function updateCustomer(string $customerId, array $data): ?ExternalContactDTO
    {
        // First get current customer to get SyncToken
        $current = $this->get("/customer/{$customerId}");
        if (!$current->successful()) {
            return null;
        }

        $payload = $this->mapDTOToCustomer($data);
        $payload['Id'] = $customerId;
        $payload['SyncToken'] = $current->json()['Customer']['SyncToken'];

        $response = $this->post('/customer', $payload);

        if (!$response->successful() || !isset($response->json()['Customer'])) {
            Log::error('QuickBooks: Failed to update customer', [
                'customer_id' => $customerId,
                'response' => $response->json(),
            ]);
            return null;
        }

        return $this->mapCustomerToDTO($response->json()['Customer']);
    }

    /**
     * Get customers modified since a given date using Change Data Capture
     */
    public function getChangedCustomers(string $changedSince): array
    {
        $response = $this->get('/cdc', [
            'entities' => 'Customer',
            'changedSince' => $changedSince,
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $customers = [];

        if (isset($data['CDCResponse'][0]['QueryResponse'][0]['Customer'])) {
            foreach ($data['CDCResponse'][0]['QueryResponse'][0]['Customer'] as $customer) {
                $customers[] = $this->mapCustomerToDTO($customer);
            }
        }

        return $customers;
    }

    // ========================================
    // Invoice Operations
    // ========================================

    /**
     * List all invoices with optional pagination
     */
    public function listInvoices(int $startPosition = 1, int $maxResults = 100, ?string $modifiedSince = null): array
    {
        $query = "SELECT * FROM Invoice";

        if ($modifiedSince) {
            $query .= " WHERE MetaData.LastUpdatedTime > '{$modifiedSince}'";
        }

        $query .= " STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";

        $response = $this->query($query);

        return $this->parseInvoiceResponse($response);
    }

    /**
     * Get a single invoice by ID
     */
    public function getInvoice(string $invoiceId): ?ExternalInvoiceDTO
    {
        $response = $this->get("/invoice/{$invoiceId}");

        if (!$response->successful() || !isset($response->json()['Invoice'])) {
            return null;
        }

        return $this->mapInvoiceToDTO($response->json()['Invoice']);
    }

    /**
     * Create a new invoice
     */
    public function createInvoice(array $data): ?ExternalInvoiceDTO
    {
        $payload = $this->mapDTOToInvoice($data);

        $response = $this->post('/invoice', $payload);

        if (!$response->successful() || !isset($response->json()['Invoice'])) {
            Log::error('QuickBooks: Failed to create invoice', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);
            return null;
        }

        return $this->mapInvoiceToDTO($response->json()['Invoice']);
    }

    /**
     * Send invoice via email
     */
    public function sendInvoice(string $invoiceId, ?string $email = null): bool
    {
        $endpoint = "/invoice/{$invoiceId}/send";
        if ($email) {
            $endpoint .= "?sendTo={$email}";
        }

        $response = $this->post($endpoint, []);

        return $response->successful();
    }

    /**
     * Get invoices modified since a given date
     */
    public function getChangedInvoices(string $changedSince): array
    {
        $response = $this->get('/cdc', [
            'entities' => 'Invoice',
            'changedSince' => $changedSince,
        ]);

        if (!$response->successful()) {
            return [];
        }

        $data = $response->json();
        $invoices = [];

        if (isset($data['CDCResponse'][0]['QueryResponse'][0]['Invoice'])) {
            foreach ($data['CDCResponse'][0]['QueryResponse'][0]['Invoice'] as $invoice) {
                $invoices[] = $this->mapInvoiceToDTO($invoice);
            }
        }

        return $invoices;
    }

    // ========================================
    // Payment Operations
    // ========================================

    /**
     * List payments
     */
    public function listPayments(int $startPosition = 1, int $maxResults = 100): array
    {
        $query = "SELECT * FROM Payment STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}";

        $response = $this->query($query);

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['QueryResponse']['Payment'] ?? [];
    }

    /**
     * Record a payment against an invoice
     */
    public function createPayment(string $customerId, string $invoiceId, float $amount): ?array
    {
        $payload = [
            'CustomerRef' => ['value' => $customerId],
            'TotalAmt' => $amount,
            'Line' => [
                [
                    'Amount' => $amount,
                    'LinkedTxn' => [
                        [
                            'TxnId' => $invoiceId,
                            'TxnType' => 'Invoice',
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->post('/payment', $payload);

        if (!$response->successful()) {
            Log::error('QuickBooks: Failed to create payment', [
                'response' => $response->json(),
            ]);
            return null;
        }

        return $response->json()['Payment'] ?? null;
    }

    // ========================================
    // Account & Item Operations
    // ========================================

    /**
     * List all accounts (Chart of Accounts)
     */
    public function listAccounts(): array
    {
        $response = $this->query("SELECT * FROM Account MAXRESULTS 1000");

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['QueryResponse']['Account'] ?? [];
    }

    /**
     * List all items (Products/Services)
     */
    public function listItems(): array
    {
        $response = $this->query("SELECT * FROM Item MAXRESULTS 1000");

        if (!$response->successful()) {
            return [];
        }

        return $response->json()['QueryResponse']['Item'] ?? [];
    }

    /**
     * Get company info
     */
    public function getCompanyInfo(): ?array
    {
        $response = $this->get("/companyinfo/{$this->realmId}");

        if (!$response->successful()) {
            return null;
        }

        return $response->json()['CompanyInfo'] ?? null;
    }

    // ========================================
    // HTTP Methods
    // ========================================

    private function query(string $query): Response
    {
        return $this->client()->get($this->baseUrl() . '/query', [
            'query' => $query,
        ]);
    }

    private function get(string $endpoint, array $params = []): Response
    {
        $url = $this->baseUrl() . $endpoint;

        return $this->client()->get($url, $params);
    }

    private function post(string $endpoint, array $data): Response
    {
        $url = $this->baseUrl() . $endpoint;

        return $this->client()->post($url, $data);
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30);
    }

    private function baseUrl(): string
    {
        $base = $this->sandbox ? self::SANDBOX_BASE_URL : self::API_BASE_URL;

        return "{$base}/{$this->realmId}";
    }

    // ========================================
    // Data Mapping
    // ========================================

    private function parseCustomerResponse(Response $response): array
    {
        if (!$response->successful()) {
            return [];
        }

        $customers = [];
        $data = $response->json()['QueryResponse']['Customer'] ?? [];

        foreach ($data as $customer) {
            $customers[] = $this->mapCustomerToDTO($customer);
        }

        return $customers;
    }

    private function parseInvoiceResponse(Response $response): array
    {
        if (!$response->successful()) {
            return [];
        }

        $invoices = [];
        $data = $response->json()['QueryResponse']['Invoice'] ?? [];

        foreach ($data as $invoice) {
            $invoices[] = $this->mapInvoiceToDTO($invoice);
        }

        return $invoices;
    }

    private function mapCustomerToDTO(array $customer): ExternalContactDTO
    {
        $billingAddr = $customer['BillAddr'] ?? [];
        $shippingAddr = $customer['ShipAddr'] ?? [];

        return new ExternalContactDTO(
            externalId: (string) $customer['Id'],
            provider: 'quickbooks',
            displayName: $customer['DisplayName'] ?? null,
            companyName: $customer['CompanyName'] ?? null,
            firstName: $customer['GivenName'] ?? null,
            lastName: $customer['FamilyName'] ?? null,
            email: $customer['PrimaryEmailAddr']['Address'] ?? null,
            phone: $customer['PrimaryPhone']['FreeFormNumber'] ?? null,
            mobile: $customer['Mobile']['FreeFormNumber'] ?? null,
            website: $customer['WebAddr']['URI'] ?? null,
            billingAddressLine1: $billingAddr['Line1'] ?? null,
            billingAddressLine2: $billingAddr['Line2'] ?? null,
            billingCity: $billingAddr['City'] ?? null,
            billingState: $billingAddr['CountrySubDivisionCode'] ?? null,
            billingPostalCode: $billingAddr['PostalCode'] ?? null,
            billingCountry: $billingAddr['Country'] ?? null,
            shippingAddressLine1: $shippingAddr['Line1'] ?? null,
            shippingAddressLine2: $shippingAddr['Line2'] ?? null,
            shippingCity: $shippingAddr['City'] ?? null,
            shippingState: $shippingAddr['CountrySubDivisionCode'] ?? null,
            shippingPostalCode: $shippingAddr['PostalCode'] ?? null,
            shippingCountry: $shippingAddr['Country'] ?? null,
            taxNumber: $customer['ResaleNum'] ?? null,
            currency: $customer['CurrencyRef']['value'] ?? 'USD',
            notes: $customer['Notes'] ?? null,
            isActive: $customer['Active'] ?? true,
            metadata: [
                'sync_token' => $customer['SyncToken'] ?? null,
                'balance' => $customer['Balance'] ?? 0,
                'fully_qualified_name' => $customer['FullyQualifiedName'] ?? null,
            ],
            createdAt: isset($customer['MetaData']['CreateTime'])
                ? new \DateTimeImmutable($customer['MetaData']['CreateTime'])
                : null,
            updatedAt: isset($customer['MetaData']['LastUpdatedTime'])
                ? new \DateTimeImmutable($customer['MetaData']['LastUpdatedTime'])
                : null,
        );
    }

    private function mapDTOToCustomer(array $data): array
    {
        $customer = [];

        if (isset($data['display_name'])) {
            $customer['DisplayName'] = $data['display_name'];
        }
        if (isset($data['company_name'])) {
            $customer['CompanyName'] = $data['company_name'];
        }
        if (isset($data['first_name'])) {
            $customer['GivenName'] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $customer['FamilyName'] = $data['last_name'];
        }
        if (isset($data['email'])) {
            $customer['PrimaryEmailAddr'] = ['Address' => $data['email']];
        }
        if (isset($data['phone'])) {
            $customer['PrimaryPhone'] = ['FreeFormNumber' => $data['phone']];
        }
        if (isset($data['mobile'])) {
            $customer['Mobile'] = ['FreeFormNumber' => $data['mobile']];
        }
        if (isset($data['website'])) {
            $customer['WebAddr'] = ['URI' => $data['website']];
        }
        if (isset($data['notes'])) {
            $customer['Notes'] = $data['notes'];
        }

        // Billing Address
        if (isset($data['billing_address_line1']) || isset($data['billing_city'])) {
            $customer['BillAddr'] = array_filter([
                'Line1' => $data['billing_address_line1'] ?? null,
                'Line2' => $data['billing_address_line2'] ?? null,
                'City' => $data['billing_city'] ?? null,
                'CountrySubDivisionCode' => $data['billing_state'] ?? null,
                'PostalCode' => $data['billing_postal_code'] ?? null,
                'Country' => $data['billing_country'] ?? null,
            ]);
        }

        // Shipping Address
        if (isset($data['shipping_address_line1']) || isset($data['shipping_city'])) {
            $customer['ShipAddr'] = array_filter([
                'Line1' => $data['shipping_address_line1'] ?? null,
                'Line2' => $data['shipping_address_line2'] ?? null,
                'City' => $data['shipping_city'] ?? null,
                'CountrySubDivisionCode' => $data['shipping_state'] ?? null,
                'PostalCode' => $data['shipping_postal_code'] ?? null,
                'Country' => $data['shipping_country'] ?? null,
            ]);
        }

        return $customer;
    }

    private function mapInvoiceToDTO(array $invoice): ExternalInvoiceDTO
    {
        $lineItems = [];
        foreach ($invoice['Line'] ?? [] as $line) {
            if (($line['DetailType'] ?? '') === 'SalesItemLineDetail') {
                $detail = $line['SalesItemLineDetail'] ?? [];
                $lineItems[] = new ExternalInvoiceLineDTO(
                    externalId: $line['Id'] ?? null,
                    description: $line['Description'] ?? null,
                    quantity: $detail['Qty'] ?? null,
                    unitPrice: $detail['UnitPrice'] ?? null,
                    amount: $line['Amount'] ?? null,
                    itemCode: $detail['ItemRef']['value'] ?? null,
                    metadata: [
                        'item_name' => $detail['ItemRef']['name'] ?? null,
                        'tax_code' => $detail['TaxCodeRef']['value'] ?? null,
                    ],
                );
            }
        }

        return new ExternalInvoiceDTO(
            externalId: (string) $invoice['Id'],
            provider: 'quickbooks',
            externalCustomerId: (string) ($invoice['CustomerRef']['value'] ?? ''),
            invoiceNumber: $invoice['DocNumber'] ?? null,
            status: $this->mapQBInvoiceStatus($invoice),
            currency: $invoice['CurrencyRef']['value'] ?? 'USD',
            subtotal: $invoice['TxnTaxDetail']['TotalTax'] ?? null
                ? ($invoice['TotalAmt'] ?? 0) - ($invoice['TxnTaxDetail']['TotalTax'] ?? 0)
                : $invoice['TotalAmt'] ?? null,
            taxAmount: $invoice['TxnTaxDetail']['TotalTax'] ?? null,
            total: $invoice['TotalAmt'] ?? null,
            amountDue: $invoice['Balance'] ?? null,
            amountPaid: ($invoice['TotalAmt'] ?? 0) - ($invoice['Balance'] ?? 0),
            invoiceDate: isset($invoice['TxnDate'])
                ? new \DateTimeImmutable($invoice['TxnDate'])
                : null,
            dueDate: isset($invoice['DueDate'])
                ? new \DateTimeImmutable($invoice['DueDate'])
                : null,
            terms: $invoice['SalesTermRef']['name'] ?? null,
            notes: $invoice['CustomerMemo']['value'] ?? null,
            privateNotes: $invoice['PrivateNote'] ?? null,
            lineItems: array_map(fn($l) => $l->toArray(), $lineItems),
            metadata: [
                'sync_token' => $invoice['SyncToken'] ?? null,
                'email_status' => $invoice['EmailStatus'] ?? null,
                'customer_name' => $invoice['CustomerRef']['name'] ?? null,
            ],
            createdAt: isset($invoice['MetaData']['CreateTime'])
                ? new \DateTimeImmutable($invoice['MetaData']['CreateTime'])
                : null,
            updatedAt: isset($invoice['MetaData']['LastUpdatedTime'])
                ? new \DateTimeImmutable($invoice['MetaData']['LastUpdatedTime'])
                : null,
        );
    }

    private function mapDTOToInvoice(array $data): array
    {
        $invoice = [
            'CustomerRef' => ['value' => $data['external_customer_id']],
        ];

        if (isset($data['invoice_number'])) {
            $invoice['DocNumber'] = $data['invoice_number'];
        }
        if (isset($data['invoice_date'])) {
            $invoice['TxnDate'] = $data['invoice_date'];
        }
        if (isset($data['due_date'])) {
            $invoice['DueDate'] = $data['due_date'];
        }
        if (isset($data['notes'])) {
            $invoice['CustomerMemo'] = ['value' => $data['notes']];
        }
        if (isset($data['private_notes'])) {
            $invoice['PrivateNote'] = $data['private_notes'];
        }

        // Line items
        if (!empty($data['line_items'])) {
            $invoice['Line'] = [];
            foreach ($data['line_items'] as $lineItem) {
                $line = [
                    'DetailType' => 'SalesItemLineDetail',
                    'Amount' => $lineItem['amount'] ?? ($lineItem['quantity'] * $lineItem['unit_price']),
                    'Description' => $lineItem['description'] ?? null,
                    'SalesItemLineDetail' => [
                        'Qty' => $lineItem['quantity'] ?? 1,
                        'UnitPrice' => $lineItem['unit_price'] ?? null,
                    ],
                ];

                if (isset($lineItem['item_code'])) {
                    $line['SalesItemLineDetail']['ItemRef'] = ['value' => $lineItem['item_code']];
                }

                $invoice['Line'][] = $line;
            }
        }

        return $invoice;
    }

    private function mapQBInvoiceStatus(array $invoice): string
    {
        $balance = $invoice['Balance'] ?? 0;
        $total = $invoice['TotalAmt'] ?? 0;

        if ($balance == 0 && $total > 0) {
            return 'paid';
        }

        if ($balance < $total && $balance > 0) {
            return 'partial';
        }

        $dueDate = isset($invoice['DueDate']) ? new \DateTime($invoice['DueDate']) : null;
        if ($dueDate && $dueDate < new \DateTime() && $balance > 0) {
            return 'overdue';
        }

        if ($invoice['EmailStatus'] === 'EmailSent') {
            return 'sent';
        }

        return 'draft';
    }
}
