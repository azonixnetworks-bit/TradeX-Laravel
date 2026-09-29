<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StaffLeaveRequest extends Model { protected $fillable=['staff_member_id','starts_on','ends_on','leave_type','reason','status','reviewed_by_admin_id','reviewed_at','review_note']; protected function casts(): array{return ['starts_on'=>'date','ends_on'=>'date','reviewed_at'=>'datetime'];} public function staff():BelongsTo{return $this->belongsTo(StaffMember::class,'staff_member_id');} public function reviewer():BelongsTo{return $this->belongsTo(AdminUser::class,'reviewed_by_admin_id');}}
