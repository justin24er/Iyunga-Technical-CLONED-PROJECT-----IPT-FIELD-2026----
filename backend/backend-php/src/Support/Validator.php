<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A small, dependency-free stand-in for the original Zod schemas
 * (lib/validation.ts). Not a general-purpose validation framework — just
 * enough rule types to cover this project's inputs, in the same spirit:
 * declare a schema once, validate, get back either clean data or a list
 * of field errors shaped like { field, message }.
 *
 * Usage:
 *   $v = new Validator($input, [
 *       'email'    => ['required', 'email'],
 *       'password' => ['required', 'min:6'],
 *   ]);
 *   if (!$v->passes()) { ...$v->errors()... }
 *   $clean = $v->validated();
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $validated = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->run();
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $value = $this->data[$field] ?? null;
            $isPresent = array_key_exists($field, $this->data) && $value !== null && $value !== '';

            foreach ($ruleSet as $rule) {
                [$name, $arg] = str_contains($rule, ':') ? explode(':', $rule, 2) : [$rule, null];

                if ($name === 'required') {
                    if (!$isPresent) {
                        $this->fail($field, $this->humanize($field) . ' is required');
                        continue 2; // no point checking further rules on a missing field
                    }
                    continue;
                }

                if (!$isPresent) {
                    // Optional field that's absent — skip remaining rules.
                    continue 2;
                }

                switch ($name) {
                    case 'string':
                        if (!is_string($value)) {
                            $this->fail($field, $this->humanize($field) . ' must be text');
                        }
                        break;

                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->fail($field, 'Enter a valid email address');
                        }
                        break;

                    case 'min':
                        if (is_string($value) && self::strLen($value) < (int) $arg) {
                            $this->fail($field, $this->humanize($field) . " must be at least {$arg} characters");
                        } elseif (is_numeric($value) && (float) $value < (float) $arg) {
                            $this->fail($field, $this->humanize($field) . " must be at least {$arg}");
                        }
                        break;

                    case 'max':
                        if (is_string($value) && self::strLen($value) > (int) $arg) {
                            $this->fail($field, $this->humanize($field) . " must be at most {$arg} characters");
                        } elseif (is_numeric($value) && (float) $value > (float) $arg) {
                            $this->fail($field, $this->humanize($field) . " must be at most {$arg}");
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', (string) $arg);
                        if (!in_array((string) $value, $allowed, true)) {
                            $this->fail($field, $this->humanize($field) . ' must be one of: ' . implode(', ', $allowed));
                        }
                        break;

                    case 'date':
                        if (!self::isValidDate((string) $value)) {
                            $this->fail($field, $this->humanize($field) . ' must be a valid date');
                        }
                        break;

                    case 'int':
                        if (!is_numeric($value) || (int) $value != $value) {
                            $this->fail($field, $this->humanize($field) . ' must be a whole number');
                        }
                        break;

                    case 'positive':
                        if (!is_numeric($value) || (float) $value <= 0) {
                            $this->fail($field, $this->humanize($field) . ' must be a positive number');
                        }
                        break;
                }
            }

            if ($isPresent) {
                $this->validated[$field] = $value;
            }
        }
    }

    private static function isValidDate(string $value): bool
    {
        $timestamp = strtotime($value);
        return $timestamp !== false;
    }

    /** Uses mb_strlen when available (multi-byte safe), falls back to strlen otherwise. */
    private static function strLen(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[] = ['field' => $field, 'message' => $message];
    }

    private function humanize(string $field): string
    {
        $spaced = preg_replace('/(?<!^)[A-Z]/', ' $0', $field);
        return ucfirst(strtolower($spaced ?? $field));
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Applies defaults for fields not present in the input, used for
     * pagination-style schemas (page/limit default values).
     */
    public function withDefaults(array $defaults): array
    {
        return array_merge($defaults, $this->validated);
    }
}
