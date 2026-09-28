<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StaffSalaryPayment extends Model { protected $fillable=['staff_member_id','period','gross_amount','deduction_amount','net_amount','currency','status','paid_at','reference','recorded_by_admin_id']; protected function casts():array{return ['gross_amount'=>'decimal:2','deduction_amount'=>'decimal:2','net_amount'=>'decimal:2','paid_at'=>'datetime'];} public function staff():BelongsTo{return $this->belongsTo(StaffMember::class,'staff_member_id');}}
