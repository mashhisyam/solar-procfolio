<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tautan dokumen (SharePoint, sharefolder, atau alamat lain).
 * Tidak dibatasi domain maupun skema; hanya panjang maksimal.
 */
class DocumentLink implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && mb_strlen((string) $value) > 1000) {
            $fail('Tautan terlalu panjang (maksimal 1000 karakter).');
        }
    }
}
