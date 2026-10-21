<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The one place that writes audit_logs rows: who did it, what, to which
 * record, the values before and after, and from which IP/browser.
 */
class AuditLogger
{
    private const MAX_USER_AGENT = 1000;

    private static int $quietDepth = 0;

    /**
     * @param  Model|class-string<Model>  $subject  a model, or its class when there is no record (e.g. a payment matching no order)
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  User|int|null  $actor  defaults to the signed-in user; null for system events
     */
    public function record(string $action, Model|string $subject, ?array $old = null, ?array $new = null, User|int|null $actor = null): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
        $model = is_string($subject) ? new $subject : $subject;

        return AuditLog::query()->create([
            'actor_id' => $actor instanceof User ? $actor->id : ($actor ?? Auth::id()),
            'action' => $action,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => is_string($subject) ? null : $subject->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? mb_substr($request->userAgent(), 0, self::MAX_USER_AGENT) : null,
        ]);
    }

    /**
     * Runs $callback without the automatic per-model entries (see
     * RecordsAuditTrail), for business actions that already write one
     * detailed entry themselves, e.g. confirming a goods receipt also saves
     * every variant's new stock.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function quietly(Closure $callback): mixed
    {
        self::$quietDepth++;

        try {
            return $callback();
        } finally {
            self::$quietDepth--;
        }
    }

    public static function isQuiet(): bool
    {
        return self::$quietDepth > 0;
    }
}
