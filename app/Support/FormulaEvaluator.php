<?php

namespace App\Support;

use RuntimeException;

/**
 * Təhlükəsiz formula hesablayıcı (recursive-descent).
 * Dəstək: rəqəmlər, `x` (dəyişən), + − × ÷, mötərizə, unar −/+. Başqa heç nə (kod icrası yox).
 * Pilləli formula: hər pillə {from, to, expr}; aralıq from<=x<to (from/to null = sonsuz).
 */
class FormulaEvaluator
{
    /** @var array<int, array{0:string,1:mixed}> */
    private array $tokens = [];

    private int $pos = 0;

    private float $x = 0.0;

    public static function evaluate(string $expr, float $x): float
    {
        $ev = new self();
        $ev->tokens = self::tokenize($expr);
        $ev->pos = 0;
        $ev->x = $x;

        if (! $ev->tokens) {
            throw new RuntimeException('İfadə boşdur.');
        }

        $val = $ev->parseExpr();
        if ($ev->pos < count($ev->tokens)) {
            throw new RuntimeException('İfadə səhvdir.');
        }

        return $val;
    }

    /**
     * Uyğun pilləni seç və hesabla.
     *
     * @param  array<int, array<string, mixed>>  $tiers
     * @return array{tier:int, result:float}
     */
    public static function apply(array $tiers, float $x): array
    {
        foreach ($tiers as $idx => $tier) {
            $from = $tier['from'] ?? null;
            $to = $tier['to'] ?? null;
            $okFrom = $from === null || $from === '' || $x >= (float) $from;
            $okTo = $to === null || $to === '' || $x < (float) $to;
            if ($okFrom && $okTo) {
                return ['tier' => $idx, 'result' => round(self::evaluate((string) ($tier['expr'] ?? ''), $x), 4)];
            }
        }

        throw new RuntimeException('Bu məbləğ üçün uyğun pillə yoxdur.');
    }

    /**
     * @return array<int, array{0:string,1:mixed}>
     */
    private static function tokenize(string $expr): array
    {
        $t = [];
        $i = 0;
        $n = strlen($expr);
        while ($i < $n) {
            $c = $expr[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if (ctype_digit($c) || $c === '.') {
                $num = '';
                while ($i < $n && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $num .= $expr[$i];
                    $i++;
                }
                if (! is_numeric($num)) {
                    throw new RuntimeException("Yanlış rəqəm: {$num}");
                }
                $t[] = ['num', (float) $num];
                continue;
            }
            if ($c === 'x' || $c === 'X') {
                $t[] = ['x', null];
                $i++;
                continue;
            }
            if (in_array($c, ['+', '-', '*', '/', '(', ')'], true)) {
                $t[] = ['op', $c];
                $i++;
                continue;
            }
            throw new RuntimeException("Yanlış simvol: {$c}");
        }

        return $t;
    }

    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function next(): ?array
    {
        return $this->tokens[$this->pos++] ?? null;
    }

    private function parseExpr(): float
    {
        $v = $this->parseTerm();
        while (($tok = $this->peek()) && $tok[0] === 'op' && ($tok[1] === '+' || $tok[1] === '-')) {
            $this->next();
            $r = $this->parseTerm();
            $v = $tok[1] === '+' ? $v + $r : $v - $r;
        }

        return $v;
    }

    private function parseTerm(): float
    {
        $v = $this->parseFactor();
        while (($tok = $this->peek()) && $tok[0] === 'op' && ($tok[1] === '*' || $tok[1] === '/')) {
            $this->next();
            $r = $this->parseFactor();
            if ($tok[1] === '/') {
                if ($r == 0.0) {
                    throw new RuntimeException('Sıfıra bölmə.');
                }
                $v /= $r;
            } else {
                $v *= $r;
            }
        }

        return $v;
    }

    private function parseFactor(): float
    {
        $tok = $this->peek();
        if ($tok && $tok[0] === 'op' && ($tok[1] === '-' || $tok[1] === '+')) {
            $this->next();
            $f = $this->parseFactor();

            return $tok[1] === '-' ? -$f : $f;
        }
        if ($tok && $tok[0] === 'num') {
            $this->next();

            return (float) $tok[1];
        }
        if ($tok && $tok[0] === 'x') {
            $this->next();

            return $this->x;
        }
        if ($tok && $tok[0] === 'op' && $tok[1] === '(') {
            $this->next();
            $v = $this->parseExpr();
            $close = $this->next();
            if (! $close || $close[1] !== ')') {
                throw new RuntimeException('Mötərizə bağlanmayıb.');
            }

            return $v;
        }

        throw new RuntimeException('İfadə gözlənilir.');
    }
}
