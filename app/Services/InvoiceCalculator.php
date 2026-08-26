<?php

declare(strict_types=1);

namespace App\Services;

final class InvoiceCalculator
{
    private const MAX_ITEMS = 200;

    public function fromRequest(array $data): array
    {
        $descriptions = array_slice((array) ($data['item_description'] ?? []), 0, self::MAX_ITEMS, true);
        $quantities = (array) ($data['item_quantity'] ?? []);
        $prices = (array) ($data['item_unit_price'] ?? []);
        $discountTypes = (array) ($data['item_discount_type'] ?? []);
        $discountValues = (array) ($data['item_discount_value'] ?? []);
        $taxes = (array) ($data['item_tax_rate'] ?? []);

        $items = [];
        $errors = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $lineNumber = 0;

        foreach ($descriptions as $index => $description) {
            $description = trim((string) $description);
            if ($description === '') {
                continue;
            }

            $lineNumber++;
            $discountType = (string) ($discountTypes[$index] ?? 'percent');
            $fields = [
                'Quantity' => $quantities[$index] ?? 1,
                'Unit price' => $prices[$index] ?? 0,
                'Discount' => $discountValues[$index] ?? 0,
                'Tax rate' => $taxes[$index] ?? 0,
            ];

            $invalid = !in_array($discountType, ['percent', 'amount'], true);
            if ($invalid) {
                $errors[] = "Line {$lineNumber} ({$description}): Discount type is invalid.";
            }
            foreach ($fields as $label => $value) {
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $errors[] = "Line {$lineNumber} ({$description}): {$label} must be a number.";
                    $invalid = true;
                }
            }
            if ($invalid) {
                continue;
            }

            $quantity = max(0, (float) $fields['Quantity']);
            $unitPrice = max(0, (float) $fields['Unit price']);
            $discountValue = (float) $fields['Discount'];
            $taxRate = (float) $fields['Tax rate'];

            if ($discountValue < 0) {
                $errors[] = "Line {$lineNumber} ({$description}): Discount cannot be negative.";
                $invalid = true;
            }
            if (!$this->hasAtMostTwoDecimals($fields['Discount'])) {
                $errors[] = "Line {$lineNumber} ({$description}): Discount may have at most two decimal places.";
                $invalid = true;
            }
            if ($discountType === 'percent' && $discountValue > 100) {
                $errors[] = "Line {$lineNumber} ({$description}): Percentage discount cannot exceed 100%.";
                $invalid = true;
            }
            if ($taxRate < 0 || $taxRate > 100) {
                $errors[] = "Line {$lineNumber} ({$description}): Tax rate must be between 0 and 100.";
                $invalid = true;
            }
            if (!$this->hasAtMostTwoDecimals($fields['Tax rate'])) {
                $errors[] = "Line {$lineNumber} ({$description}): Tax rate may have at most two decimal places.";
                $invalid = true;
            }

            $lineSubtotal = round($quantity * $unitPrice, 2);
            if ($discountType === 'amount' && $discountValue > $lineSubtotal) {
                $errors[] = "Line {$lineNumber} ({$description}): Amount discount cannot exceed the line subtotal.";
                $invalid = true;
            }
            if ($invalid) {
                continue;
            }

            $discountValue = round($discountValue, 2);
            $taxRate = round($taxRate, 2);
            $lineDiscount = $discountType === 'percent'
                ? round($lineSubtotal * ($discountValue / 100), 2)
                : $discountValue;
            $taxable = max(0, $lineSubtotal - $lineDiscount);
            $lineTax = round($taxable * ($taxRate / 100), 2);
            $lineTotal = round($taxable + $lineTax, 2);

            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'tax_rate' => $taxRate,
                'line_total' => $lineTotal,
                'sort_order' => count($items),
            ];

            $subtotal += $lineSubtotal;
            $discountTotal += $lineDiscount;
            $taxTotal += $lineTax;
        }

        return [
            'items' => $items,
            'errors' => $errors,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal - $discountTotal + $taxTotal, 2),
        ];
    }

    private function hasAtMostTwoDecimals(mixed $value): bool
    {
        $value = trim((string) $value);
        return $value === '' || preg_match('/^-?(?:\d+|\d*\.\d{1,2})$/D', $value) === 1;
    }
}
