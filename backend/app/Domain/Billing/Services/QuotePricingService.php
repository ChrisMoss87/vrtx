<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Entities\Quote;
use App\Domain\Billing\Entities\QuoteLineItem;
use App\Domain\Billing\ValueObjects\Money;

/**
 * Domain service for quote pricing calculations.
 */
final class QuotePricingService
{
    /**
     * Calculate the subtotal for all line items.
     */
    public function calculateSubtotal(Quote $quote): Money
    {
        $subtotal = Money::zero($quote->getCurrency());

        foreach ($quote->getLineItems() as $lineItem) {
            $subtotal = $subtotal->add($lineItem->calculateSubtotal());
        }

        return $subtotal;
    }

    /**
     * Calculate the total tax for all line items.
     */
    public function calculateTax(Quote $quote): Money
    {
        $taxAmount = Money::zero($quote->getCurrency());

        foreach ($quote->getLineItems() as $lineItem) {
            $taxAmount = $taxAmount->add($lineItem->calculateTax());
        }

        return $taxAmount;
    }

    /**
     * Calculate the discount amount based on discount type and value.
     */
    public function calculateDiscount(Quote $quote): Money
    {
        $subtotal = $this->calculateSubtotal($quote);
        return $quote->getDiscountType()->calculateDiscount(
            $subtotal->amount(),
            $quote->getDiscountType()->value === 'fixed'
                ? $quote->getDiscountAmount()->amount()
                : $quote->getDiscountPercent()
        );
    }

    /**
     * Calculate the grand total.
     */
    public function calculateTotal(Quote $quote): Money
    {
        $subtotal = $this->calculateSubtotal($quote);
        $discount = $this->calculateDiscount($quote);
        $tax = $this->calculateTax($quote);

        return $subtotal->subtract($discount)->add($tax);
    }

    /**
     * Validate that all line items are valid.
     *
     * @return array<string> List of validation errors
     */
    public function validateLineItems(Quote $quote): array
    {
        $errors = [];

        if (empty($quote->getLineItems())) {
            $errors[] = 'Quote must have at least one line item';
        }

        foreach ($quote->getLineItems() as $index => $lineItem) {
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
     * Calculate the effective discount percentage.
     */
    public function calculateEffectiveDiscountPercentage(Quote $quote): float
    {
        $subtotal = $this->calculateSubtotal($quote);

        if ($subtotal->isZero()) {
            return 0.0;
        }

        $discount = $this->calculateDiscount($quote);
        return ($discount->amount() / $subtotal->amount()) * 100;
    }

    /**
     * Calculate estimated profit margin (requires cost information).
     */
    public function calculateProfitMargin(Quote $quote, Money $totalCost): float
    {
        $total = $this->calculateTotal($quote);

        if ($total->isZero()) {
            return 0.0;
        }

        $profit = $total->subtract($totalCost);
        return ($profit->amount() / $total->amount()) * 100;
    }
}
