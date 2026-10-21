<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Writes "<model>.created / updated / deleted" audit entries, with the
 * changed fields before and after, whenever staff change the record from
 * the admin area (forms and Excel import alike). Seeders, tests outside
 * admin routes and storefront actions (a customer's order taking stock)
 * are not logged.
 */
trait RecordsAuditTrail
{
    /**
     * Bookkeeping columns left out of the logged values.
     *
     * @var list<string>
     */
    private static array $auditIgnored = ['created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by'];

    public static function bootRecordsAuditTrail(): void
    {
        static::created(fn (self $model) => $model->writeAuditEntry('created', null, $model->auditValues($model->attributesToArray())));

        static::updated(function (self $model): void {
            $changed = array_keys($model->auditValues($model->getChanges()));
            if ($changed !== []) {
                // Both sides cast the same way (booleans, decimals, dates).
                $model->writeAuditEntry('updated', Arr::only($model->getOriginal(), $changed), Arr::only($model->attributesToArray(), $changed));
            }
        });

        static::deleted(fn (self $model) => $model->writeAuditEntry('deleted', $model->auditValues($model->attributesToArray()), null));
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function writeAuditEntry(string $event, ?array $old, ?array $new): void
    {
        if (AuditLogger::isQuiet() || ! request()->routeIs('admin.*') || auth()->guest()) {
            return;
        }

        app(AuditLogger::class)->record(Str::snake(class_basename($this)).'.'.$event, $this, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function auditValues(array $values): array
    {
        return array_diff_key($values, array_flip(self::$auditIgnored));
    }
}
