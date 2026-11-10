<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ValidStlFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile || !$value->isValid() || strtolower($value->getClientOriginalExtension()) !== 'stl') {
            $fail('O arquivo deve ser um STL valido.');
            return;
        }

        $path = $value->getRealPath();
        $size = $path === false ? false : filesize($path);

        if ($size === false || $size < 15) {
            $fail('O arquivo STL esta vazio ou corrompido.');
            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $fail('Nao foi possivel ler o arquivo STL.');
            return;
        }

        $header = fread($handle, 84);

        if (preg_match('/^\s*solid\b/i', $header) === 1) {
            $hasFacet = false;
            $hasEnd = false;

            rewind($handle);
            while (!feof($handle) && (!$hasFacet || !$hasEnd)) {
                $chunk = fread($handle, 8192);
                $hasFacet = $hasFacet || stripos($chunk, 'facet normal') !== false;
                $hasEnd = $hasEnd || stripos($chunk, 'endsolid') !== false;
            }

            fclose($handle);

            if ($hasFacet && $hasEnd) {
                return;
            }

            $fail('O arquivo nao possui uma estrutura STL valida.');
            return;
        }

        fclose($handle);

        if (strlen($header) !== 84) {
            $fail('O arquivo nao possui uma estrutura STL valida.');
            return;
        }

        $triangles = unpack('Vcount', substr($header, 80, 4))['count'];
        $expectedSize = 84 + ($triangles * 50);

        if ($expectedSize !== $size) {
            $fail('O arquivo nao possui uma estrutura STL valida.');
        }
    }
}
