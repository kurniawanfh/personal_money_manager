<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSettingController extends Controller
{
    /**
     * Get user settings and leak threshold configurations.
     */
    public function show(Request $request): JsonResponse
    {
        $settings = UserSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'drip_max_single_amount' => 25000.00,
                'drip_monthly_threshold' => 500000.00,
                'surge_percentage_threshold' => 150.00,
                'zombie_inactivity_days' => 60,
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $settings,
        ]);
    }

    /**
     * Update user settings and custom leak detector thresholds.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'drip_max_single_amount' => 'sometimes|numeric|min:1000|max:1000000',
            'drip_monthly_threshold' => 'sometimes|numeric|min:10000|max:50000000',
            'surge_percentage_threshold' => 'sometimes|numeric|min:100|max:1000',
            'zombie_inactivity_days' => 'sometimes|integer|min:7|max:365',
        ]);

        $settings = UserSetting::firstOrCreate(['user_id' => $request->user()->id]);
        $settings->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => $settings,
        ]);
    }
}
