<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StaffOtpChallenge extends Model {protected $fillable=['staff_member_id','channel','code_hash','attempts','expires_at','sent_at','verified_at'];protected function casts():array{return ['expires_at'=>'datetime','sent_at'=>'datetime','verified_at'=>'datetime'];}public function staff():BelongsTo{return $this->belongsTo(StaffMember::class,'staff_member_id');}public function isUsable():bool{return $this->verified_at===null&&$this->attempts<5&&$this->expires_at->isFuture();}}
