<?php

namespace App\Http\Controllers\Api\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\TradingFloorAccessCredential;
use App\Models\TradingFloorAccessDevice;
use App\Models\TradingFloorAccessLog;
use App\Services\Admin\TradingFloor\TradingFloorAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TradingFloorAccessScanController extends Controller
{
    public function __invoke(
        Request $request,
        TradingFloorAccessService $access
    ): JsonResponse {
        $device = $request->attributes->get('trading_floor_access_device');
        abort_unless($device instanceof TradingFloorAccessDevice, 401);

        $data = $request->validate([
            'event_id' => ['required', 'string', 'min:8', 'max:100'],
            'credential_type' => ['required', Rule::in([
                TradingFloorAccessCredential::TYPE_NFC,
                TradingFloorAccessCredential::TYPE_FINGERPRINT,
            ])],
            'credential_reference' => ['required', 'string', 'min:2', 'max:255'],
            'direction' => ['required', Rule::in([
                TradingFloorAccessLog::DIRECTION_ENTRY,
                TradingFloorAccessLog::DIRECTION_EXIT,
            ])],
        ]);

        $result = $access->process(
            $device,
            (string) $data['event_id'],
            (string) $data['credential_type'],
            (string) $data['credential_reference'],
            (string) $data['direction'],
            $request->ip()
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}
