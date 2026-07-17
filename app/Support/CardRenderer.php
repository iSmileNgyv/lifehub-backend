<?php

namespace App\Support;

use App\Models\Card;
use App\Models\CardTemplate;

/**
 * Kartı SƏTİRLƏRƏ çevirir — hər sətir bir və ya bir neçə sahə (yan-yana). Bot/extension ortaq.
 * Kanal display konfiqurasiyası varsa (dizayner): front/back = sətirlər (string[][] açar), sıra + yan-yana.
 * Yoxdursa: field.side + layout sırası (hər sahə öz sətri). Sadə kart → front/back sütunları.
 */
class CardRenderer
{
    /**
     * @param  string  $channel  'telegram' | 'extension' | 'default'
     * @return array{front:string, back:string, front_rows:array<int,array<int,string>>, back_rows:array<int,array<int,string>>, front_image:?string, back_image:?string}
     */
    public function render(Card $card, ?CardTemplate $tpl, string $channel = 'default'): array
    {
        if ($card->fields && $tpl) {
            $display = $tpl->display[$channel] ?? null;
            if (is_array($display) && (! empty($display['front']) || ! empty($display['back']))) {
                [$fr, $frImg] = $this->rowsFromConfig($card, $tpl, $display['front'] ?? [], $card->front_image);
                [$br, $brImg] = $this->rowsFromConfig($card, $tpl, $display['back'] ?? [], $card->back_image);
            } else {
                [$fr, $frImg] = $this->rowsFromSide($card, $tpl, 'front');
                [$br, $brImg] = $this->rowsFromSide($card, $tpl, 'back');
            }

            return $this->pack($fr, $br, $frImg, $brImg);
        }

        $fr = ($card->front !== null && trim((string) $card->front) !== '') ? [[(string) $card->front]] : [];
        $br = ($card->back !== null && trim((string) $card->back) !== '') ? [[(string) $card->back]] : [];

        return $this->pack($fr, $br, $card->front_image, $card->back_image);
    }

    /**
     * Kanal konfiqurasiyasından sətirlər (hər sətir = açar massivi → dəyərlər yan-yana).
     *
     * @param  array<int, mixed>  $config
     * @return array{0:array<int,array<int,string>>, 1:?string}
     */
    private function rowsFromConfig(Card $card, CardTemplate $tpl, array $config, ?string $img): array
    {
        $byKey = [];
        foreach ($tpl->fields as $f) {
            $byKey[$f['key'] ?? ''] = $f;
        }
        $rows = [];
        foreach ($config as $row) {
            $keys = is_array($row) ? $row : [$row]; // flat fallback
            $vals = [];
            foreach ($keys as $key) {
                $f = $byKey[$key] ?? null;
                if (! $f) {
                    continue;
                }
                $type = $f['type'] ?? 'text';
                if ($type === 'heading') {
                    $l = trim((string) ($f['label'] ?? ''));
                    if ($l !== '') {
                        $vals[] = $l;
                    }

                    continue;
                }
                $v = $card->fields[$key] ?? null;
                if ($v === null || trim((string) $v) === '') {
                    continue;
                }
                if ($type === 'image') {
                    if (! $img) {
                        $img = (string) $v;
                    }

                    continue;
                }
                $vals[] = $type === 'rich' ? $this->stripHtml((string) $v) : (string) $v;
            }
            if ($vals) {
                $rows[] = $vals;
            }
        }

        return [$rows, $img];
    }

    /**
     * @return array{0:array<int,array<int,string>>, 1:?string}
     */
    private function rowsFromSide(Card $card, CardTemplate $tpl, string $side): array
    {
        $img = $side === 'front' ? $card->front_image : $card->back_image;
        $rows = [];
        foreach ($this->ordered($tpl->fields) as $f) {
            $fSide = ($f['side'] ?? 'front') === 'back' ? 'back' : 'front';
            if ($fSide !== $side) {
                continue;
            }
            $type = $f['type'] ?? 'text';
            if ($type === 'heading') {
                $l = trim((string) ($f['label'] ?? ''));
                if ($l !== '') {
                    $rows[] = [$l];
                }

                continue;
            }
            $v = $card->fields[$f['key'] ?? ''] ?? null;
            if ($v === null || trim((string) $v) === '') {
                continue;
            }
            if ($type === 'image') {
                if (! $img) {
                    $img = (string) $v;
                }

                continue;
            }
            $rows[] = [$type === 'rich' ? $this->stripHtml((string) $v) : (string) $v];
        }

        return [$rows, $img];
    }

    /**
     * @param  array<int,array<int,string>>  $fr
     * @param  array<int,array<int,string>>  $br
     * @return array{front:string, back:string, front_rows:array<int,array<int,string>>, back_rows:array<int,array<int,string>>, front_image:?string, back_image:?string}
     */
    private function pack(array $fr, array $br, ?string $frImg, ?string $brImg): array
    {
        $flat = fn (array $rows) => implode("\n", array_map(fn ($r) => implode(' · ', $r), $rows));

        return [
            'front' => $flat($fr),
            'back' => $flat($br),
            'front_rows' => $fr,
            'back_rows' => $br,
            'front_image' => $frImg,
            'back_image' => $brImg,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function ordered(array $fields): array
    {
        $hasLayout = false;
        foreach ($fields as $f) {
            if (isset($f['x'])) {
                $hasLayout = true;
                break;
            }
        }
        if ($hasLayout) {
            usort($fields, fn ($a, $b) => (($a['y'] ?? 0) <=> ($b['y'] ?? 0)) ?: (($a['x'] ?? 0) <=> ($b['x'] ?? 0)));
        }

        return $fields;
    }

    private function stripHtml(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($s)) ?? '');
    }
}
