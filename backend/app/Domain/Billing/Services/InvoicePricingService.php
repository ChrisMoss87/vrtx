<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Entities\Invoice;
use App\Domain\Billing\ValueObjects\Money;

/**
 * Domain service for invoice pricing calculations.
 */
final class InvoicePricingService
{
    /**
     * Calculate the subtotal for all line items.
     */
    public function calculateSubtotal(Invoice $invoice): Money
    {
        $subtotal = Money::zero($invoice->getCurrency());

        foreach ($invoice->getLineItems() as $lineItem) {
            $subtotal = $subtotal->add($lineItem->calculateSubtotal());
        }

        return $subtotal;
    }

    /**
     * Calculate the total tax for all line items.
     */
    public function calculateTax(Invoice $invoice): Money
    {
        $taxAmount = Money::zero($invoice->getCurrency());

        foreach ($invoice->getLineItems() as $lineItem) {
            $taxAmount = $taxAmount->add($lineItem->calculateTax());
        }

        return $taxAmount;
    }

    /**
     * Calculate the grand total.
     */
    public function calculateTotal(Invoice $invoice): Money
    {
        $subtotal = $this->calculateSubtotal($invoice);
        $discount = $invoice->getDiscountAmount();
        $tax = $this->calculateTax($invoice);

        return $subtotal->subtract($discount)->add($tax);
    }

    /**
     * Calculate the total amount paid.
     */
    public function calculateAmountPaid(Invoice $invoice): Money
    {
        $amountPaid = Money::zero($invoice->getCurrency());

        foreach ($invoice->getPayments() as $payment) {
            $amountPaid = $amountPaid->add($payment->getAmount());
        }

        return $amountPaid;
    }

    /**
     * Calculate the balance due.
     */
    public function calculateBalanceDue(Invoice $invoice): Money
    {
        $total = $this->calculateTotal($invoice);
        $amountPaid = $this->calculateAmountPaid($invoice);

        return $total->subtract($amountPaid);
    }

    /**
     * Calculate the payment completion percentage.
     */
    public function calculatePaymentPercentage(Invoice $invoice): float
    {
        $total = $this->calculateTotal($invoice);

        if ($total->isZero()) {
            return 0.0;
        }

        $amountPaid = $this->calculateAmountPaid($invoice);
        return ($amountPaid->amount() / $total->amount()) * 100;
    }

    /**
     * Calculate days until due (negative if overdue).
     */
    public function calculateDaysUntilDue(Invoice $invoice): int
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($invoice->getDueDate());

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Calculate days since issue.
     */
    public function calculateDaysSinceIssue(Invoice $invoice): int
    {
        $now = new \DateTimeImmutable();
        return $now->diff($invoice->getIssueDate())->days;
    }

    /**
     * Validate that all line items are valid.
     *
     * @return array<string> List of validation errors
     */
    public function validateLineItems(Invoice $invoice): array
    {
        $errors = [];

        if (empty($invoice->getLineItems())) {
            $errors[] = 'Invoice must have at least one line item';
        }

        foreach ($invoice->getLineItems() as $index => $lineItem) {
            if ($lineItem->getQuantity() <= 0) {
                $errors[] = "Line item {$index}: Quantity must be greater than 0";
            }

            if ($lineItem->getUnitPrice()->amount() < 0) {
                $errors[] = "Line item {$index}: Unit price cannot be negative";
            }

            if ($lineItem->getDiscountPercent() < 0 || $lineItem->getDiscountPercent() > 100) {
                $errors[] = "Line item {$index}: Discount percent must be between 0 and 100";
            }

            if ($lineItem->getTaxRate() < 0 || $lineItem->getTaxRate() > 100) {
                $errors[] = "Line item {$index}: Tax rate must be between 0 and 100";
            }
        }

        return $errors;
    }

    /**
     * Validate that payments don't exceed the total.
     *
     * @return array<string> List of validation errors
     */
    public function validatePayments(Invoice $invoice): array
    {
        $errors = [];

        $total = $this->calculateTotal($invoice);
        $amountPaid = $this->calculateAmountPaid($invoice);

        if ($amountPaid->greaterThan($total)) {
            $overpayment = $amountPaid->subtract($total);
            $errors[] = "Payments exceed invoice total by {$overpayment->format()}";
        }

        return $errors;
    }
}
