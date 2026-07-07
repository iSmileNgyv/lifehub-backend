<?php

namespace App\AI;

interface AiProvider
{
    /**
     * Verilən söz/prompt üçün sahələri doldur.
     *
     * @param  array<int, array{key: string, description: ?string}>  $fields
     * @param  string  $instruction  Şablonun ümumi AI təlimatı (formatlaşdırma və s.)
     * @return array<string, string>  key => dəyər
     */
    public function generateFields(array $fields, string $prompt, string $instruction = ''): array;
}
