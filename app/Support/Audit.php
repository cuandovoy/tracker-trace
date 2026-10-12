<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function record(Model $model, string $action, array $data = []): void
    {
        AuditEvent::create(['user_id' => auth()->id(), 'subject_type' => $model->getTable(), 'subject_id' => $model->id, 'action' => $action, 'data' => $data, 'created_at' => now()]);
    }
}
