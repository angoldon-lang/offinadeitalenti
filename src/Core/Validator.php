<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function required(mixed $value, string $field, string $label): self
    {
        if ($value === null || $value === '' || (is_array($value) && $value === [])) {
            $this->errors[$field] = "{$label}: campo obbligatorio.";
        }
        return $this;
    }

    public function email(mixed $value, string $field, string $label = 'Email'): self
    {
        if ($value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "{$label}: indirizzo non valido.";
        }
        return $this;
    }

    public function minLength(mixed $value, int $min, string $field, string $label): self
    {
        if (is_string($value) && $value !== '' && mb_strlen($value) < $min) {
            $this->errors[$field] = "{$label}: almeno {$min} caratteri.";
        }
        return $this;
    }

    public function in(mixed $value, array $allowed, string $field, string $label): self
    {
        if ($value !== null && $value !== '' && !in_array($value, $allowed, true)) {
            $this->errors[$field] = "{$label}: valore non ammesso.";
        }
        return $this;
    }

    public function numericRange(mixed $value, float $min, float $max, string $field, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        $n = (float) $value;
        if ($n < $min || $n > $max) {
            $this->errors[$field] = "{$label}: valore fuori intervallo ({$min}–{$max}).";
        }
        return $this;
    }

    public function date(mixed $value, string $field, string $label): self
    {
        if ($value === null || $value === '') {
            return $this;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        if (!$d || $d->format('Y-m-d') !== $value) {
            $this->errors[$field] = "{$label}: data non valida.";
        }
        return $this;
    }

    public function rule(bool $condition, string $field, string $message): self
    {
        if (!$condition) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : reset($this->errors);
    }
}
