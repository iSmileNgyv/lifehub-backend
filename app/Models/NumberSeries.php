<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NumberSeries extends Model
{
    protected $table = 'admin.number_series';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'prefix', 'padding', 'start_no', 'end_no', 'increment', 'last_no', 'in_use',
    ];

    protected function casts(): array
    {
        return [
            'padding' => 'integer',
            'start_no' => 'integer',
            'end_no' => 'integer',
            'increment' => 'integer',
            'last_no' => 'integer',
            'in_use' => 'boolean',
        ];
    }

    /**
     * Növbəti kodu atomik şəkildə generasiya edir (sətir kilidlənir → təkrar olmaz).
     */
    public static function generateNext(string $code): string
    {
        return DB::transaction(function () use ($code) {
            /** @var self $series */
            $series = self::where('code', $code)->lockForUpdate()->firstOrFail();

            $next = $series->last_no === null
                ? $series->start_no
                : $series->last_no + $series->increment;

            if ($series->end_no !== null && $next > $series->end_no) {
                throw new RuntimeException("Number series '{$code}' tükəndi.");
            }

            $series->last_no = $next;
            $series->in_use = true;
            $series->save();

            $number = $series->padding > 0
                ? str_pad((string) $next, $series->padding, '0', STR_PAD_LEFT)
                : (string) $next;

            return $series->prefix.$number;
        });
    }
}
