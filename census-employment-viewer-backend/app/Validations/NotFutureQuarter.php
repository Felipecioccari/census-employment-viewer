<?php

namespace App\Validations;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotFutureQuarter implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Expected format: YYYY-Q#
        if (!preg_match('/^(\d{4})-Q([1-4])$/', $value, $matches)) {
            $fail('The :attribute must be in the format YYYY-Q# (e.g., 2023-Q4).');
            return;
        }

        $year = (int) $matches[1];
        $quarter = (int) $matches[2];

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $currentQuarter = (int) ceil($currentMonth / 3);

        // Check if the quarter is in the future
        if ($year > $currentYear || ($year === $currentYear && $quarter > $currentQuarter)) {
            $fail('The :attribute cannot be in the future.');
        }
    }
}
