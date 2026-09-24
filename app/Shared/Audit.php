<?php
namespace App\Shared;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\IdentityAccess\Models\User;
final class Audit
{
    public static function append(
        User $actor,
        string $action,
        string $subject,
        array $metadata = [],
    ): void {
        AuditEvent::create([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'action' => $action,
            'subject' => $subject,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
