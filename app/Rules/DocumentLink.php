<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Tautan dokumen harus https dan berada di domain yang diizinkan (default sharepoint.com). */
class DocumentLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $host = parse_url((string) $value, PHP_URL_HOST);
        if (! str_starts_with((string) $value, 'https://') || ! $host) {
            $fail('Tautan harus diawali https://.');

            return;
        }
        $domains = config('procfolio.link_domains', []);
        if ($domains && ! collect($domains)->contains(fn ($d) => $host === $d || str_ends_with($host, '.'.$d))) {
            $fail('Tautan harus berasal dari '.implode(', ', $domains).'.');
        }
    }
}
