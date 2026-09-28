<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StaffPayoutChangeRequest extends Model { protected $fillable=['staff_member_id','requested_details','reason','status','reviewed_by_admin_id','reviewed_at','review_note']; protected function casts():array{return ['requested_details'=>'array','reviewed_at'=>'datetime'];} public function staff():BelongsTo{return $this->belongsTo(StaffMember::class,'staff_member_id');}}
