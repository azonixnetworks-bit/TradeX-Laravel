<?php
namespace App\Http\Controllers\Api\Payouts;
use App\Http\Controllers\Controller; use App\Models\User; use App\Services\Payouts\PayoutApiPresenter; use App\Services\Payouts\PayoutQueryService; use Illuminate\Http\JsonResponse; use Illuminate\Http\Request;
class PayoutShowController extends Controller { public function __invoke(Request $request,string $payoutRequest,PayoutQueryService $service,PayoutApiPresenter $presenter):JsonResponse { $user=$request->user(); abort_unless($user instanceof User,401); return response()->json(['success'=>true,'data'=>['payout'=>$presenter->payout($service->owned($user,$payoutRequest))]]); } }
