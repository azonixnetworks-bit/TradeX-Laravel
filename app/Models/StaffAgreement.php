<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StaffAgreement extends Model { protected $fillable=['staff_member_id','agreement_type','version','content','accepted_at','accepted_ip','accepted_user_agent']; protected function casts():array{return ['accepted_at'=>'datetime'];} public function staff():BelongsTo{return $this->belongsTo(StaffMember::class,'staff_member_id');}}
