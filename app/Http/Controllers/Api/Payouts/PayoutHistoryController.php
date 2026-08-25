<?php
namespace App\Http\Controllers\Api\Payouts;
use App\Http\Controllers\Controller; use App\Http\Requests\Api\Payouts\ListPayoutRequests; use App\Models\User; use App\Services\Payouts\PayoutQueryService; use Illuminate\Http\JsonResponse;
class PayoutHistoryController extends Controller { public function __invoke(ListPayoutRequests $request,PayoutQueryService $service):JsonResponse { $user=$request->user(); abort_unless($user instanceof User,401); return response()->json(['success'=>true,'data'=>$service->history($user,$request->validated())]); } }
