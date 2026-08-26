<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function __construct(private array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        if (!isset($this->data[$field]) || trim((string) $this->data[$field]) === '') {
            $this->errors[$field] = "{$label} is required.";
        }

        return $this;
    }

    public function email(string $field, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label} must be a valid email address.";
        }

        return $this;
    }

    public function numeric(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->errors[$field] = "{$label} must be a number.";
        }

        return $this;
    }

    public function decimalPlaces(string $field, int $places, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value !== '' && is_numeric($value) && preg_match('/^-?(?:\d+|\d*\.\d{1,' . $places . '})$/D', $value) !== 1) {
            $this->errors[$field] = "{$label} may have at most {$places} decimal places.";
        }

        return $this;
    }

    public function between(string $field, float $minimum, float $maximum, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && is_numeric($value) && ((float) $value < $minimum || (float) $value > $maximum)) {
            $this->errors[$field] = "{$label} must be between {$minimum} and {$maximum}.";
        }

        return $this;
    }

    public function integer(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->errors[$field] = "{$label} must be a whole number.";
        }

        return $this;
    }

    public function date(string $field, string $label): self
    {
        $value = trim((string) ($this->data[$field] ?? ''));
        if ($value === '') {
            return $this;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $this->errors[$field] = "{$label} must be a valid date.";
        }

        return $this;
    }

    public function in(string $field, array $allowed, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if ($value !== '' && !in_array($value, array_map('strval', $allowed), true)) {
            $this->errors[$field] = "{$label} is invalid.";
        }

        return $this;
    }

    public function max(string $field, int $length, string $label): self
    {
        if (mb_strlen((string) ($this->data[$field] ?? '')) > $length) {
            $this->errors[$field] = "{$label} may not exceed {$length} characters.";
        }

        return $this;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }
}
