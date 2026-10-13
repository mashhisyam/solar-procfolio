<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogger
{
    public function log(?int $procurementId, string $entity, string|int|null $entityId, string $action, ?string $field = null, mixed $old = null, mixed $new = null, ?string $reason = null): void
    {
        AuditLog::query()->create([
            'procurement_id' => $procurementId,
            'user_id' => auth()->id(),
            'entity' => $entity,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'action' => $action,
            'field' => $field,
            'old_value' => $this->str($old),
            'new_value' => $this->str($new),
            'reason' => $reason,
        ]);
    }

    private function str(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    }
}
