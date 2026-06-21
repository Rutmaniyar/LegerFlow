<?php

declare(strict_types=1);

namespace App\Services;

final class InvoiceCalculator
{
    private const MAX_ITEMS = 200;

    public function fromRequest(array $data): array
    {
        $descriptions = array_slice((array) ($data['item_description'] ?? []), 0, self::MAX_ITEMS, true);
        $quantities = $data['item_quantity'] ?? [];
        $prices = $data['item_unit_price'] ?? [];
        $discounts = $data['item_discount_rate'] ?? [];
        $taxes = $data['item_tax_rate'] ?? [];

        $items = [];
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($descriptions as $index => $description) {
            $description = trim((string) $description);
            if ($description === '') {
                continue;
            }

            $quantity = max(0, (float) ($quantities[$index] ?? 1));
            $unitPrice = max(0, (float) ($prices[$index] ?? 0));
            $discountRate = max(0, (float) ($discounts[$index] ?? 0));
            $taxRate = max(0, (float) ($taxes[$index] ?? 0));

            $lineSubtotal = round($quantity * $unitPrice, 2);
            $lineDiscount = round($lineSubtotal * ($discountRate / 100), 2);
            $taxable = max(0, $lineSubtotal - $lineDiscount);
            $lineTax = round($taxable * ($taxRate / 100), 2);
            $lineTotal = round($taxable + $lineTax, 2);

            $items[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_rate' => $discountRate,
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
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal - $discountTotal + $taxTotal, 2),
        ];
    }
}
