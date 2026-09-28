<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffKycDocument extends Model
{
    protected $fillable = ['staff_member_id', 'document_type', 'storage_disk', 'document_path', 'status', 'review_note', 'reviewed_by_admin_id', 'reviewed_at'];
    protected function casts(): array { return ['reviewed_at' => 'datetime']; }
    public function staff(): BelongsTo { return $this->belongsTo(StaffMember::class, 'staff_member_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_id'); }
}
