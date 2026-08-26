<?php

declare(strict_types=1);

namespace App\Services;

final class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = app()->db()->fetch('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
        return $row['setting_value'] ?? $default;
    }

    public function set(string $key, mixed $value, bool $private = false): void
    {
        app()->db()->execute(
            'INSERT INTO settings (setting_key, setting_value, is_private, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_private = VALUES(is_private), updated_at = NOW()',
            [$key, (string) $value, $private ? 1 : 0]
        );
    }

    public function business(): array
    {
        return app()->db()->fetch('SELECT * FROM business_settings ORDER BY id LIMIT 1') ?? [];
    }

    public function defaultTax(): ?array
    {
        $taxId = (int) $this->get('default_tax_id', 0);
        if ($taxId <= 0) {
            return null;
        }

        return app()->db()->fetch('SELECT * FROM taxes WHERE id = ? AND is_active = 1', [$taxId]) ?: null;
    }

    public function updateBusiness(array $data): void
    {
        app()->db()->execute(
            'UPDATE business_settings
             SET business_name = ?, legal_name = ?, email = ?, phone = ?, website = ?, address_line1 = ?, address_line2 = ?,
                 city = ?, region = ?, postal_code = ?, country = ?, tax_number = ?, brand_color = ?, accent_color = ?,
                 default_currency = ?, default_payment_terms = ?, privacy_policy = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $data['business_name'], $data['legal_name'], $data['email'], $data['phone'], $data['website'],
                $data['address_line1'], $data['address_line2'], $data['city'], $data['region'], $data['postal_code'],
                $data['country'], $data['tax_number'], $data['brand_color'] ?: '#0ea394', $data['accent_color'] ?: '#8b5cf6',
                $data['default_currency'], (int) $data['default_payment_terms'], $data['privacy_policy'], (int) $data['id'],
            ]
        );
    }
}
