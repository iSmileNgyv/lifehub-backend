<?php

namespace App\AI;

interface AiProvider
{
    /**
     * Verilən söz/prompt üçün sahələri doldur.
     *
     * @param  array<int, array{key: string, label?: string, description: ?string}>  $fields
     * @param  string  $instruction  Şablonun ümumi AI təlimatı (formatlaşdırma və s.)
     * @return array<string, string>  key => dəyər
     */
    public function generateFields(array $fields, string $prompt, string $instruction = ''): array;

    /**
     * Bir sorğuda çox söz üçün sahələri doldur (xərci azaltmaq üçün).
     *
     * @param  array<int, array{key: string, label?: string, description: ?string}>  $fields
     * @param  array<int, string>  $prompts  sözlər
     * @return array<string, array<string, string>>  söz => (key => dəyər)
     */
    public function generateFieldsBatch(array $fields, array $prompts, string $instruction = ''): array;
}
