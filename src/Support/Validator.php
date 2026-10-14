<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validador sencillo con reglas separadas por "|".
 *
 * Reglas disponibles: required, email, numeric, min:n, max:n, date, in:a,b,c
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public function __construct(
        private array $data,
        private array $rules,
        private array $labels = []
    ) {
        $this->validate();
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors === [] ? null : (string) reset($this->errors);
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', (string) $field));

            foreach (explode('|', $ruleString) as $rule) {
                if ($rule === '') {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);
                $isEmpty = $value === null || $value === '';

                if ($name === 'required') {
                    if ($isEmpty) {
                        $this->errors[$field] = "El campo {$label} es obligatorio.";
                    }
                    continue;
                }

                if ($isEmpty) {
                    continue;
                }

                $message = match ($name) {
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                        ? "El campo {$label} debe ser un correo valido." : null,
                    'numeric' => ! is_numeric($value)
                        ? "El campo {$label} debe ser numerico." : null,
                    'date' => strtotime((string) $value) === false
                        ? "El campo {$label} debe ser una fecha valida." : null,
                    'min' => mb_strlen((string) $value) < (int) $parameter
                        ? "El campo {$label} debe tener al menos {$parameter} caracteres." : null,
                    'max' => mb_strlen((string) $value) > (int) $parameter
                        ? "El campo {$label} no debe superar {$parameter} caracteres." : null,
                    'in' => ! in_array((string) $value, explode(',', (string) $parameter), true)
                        ? "El valor seleccionado en {$label} no es valido." : null,
                    default => null,
                };

                if ($message !== null) {
                    $this->errors[$field] = $message;
                }
            }
        }
    }
}
