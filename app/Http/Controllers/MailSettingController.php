<?php
// This file is part of the app logic and has a short comment so it is easier to read.


namespace App\Http\Controllers;

use App\Models\MailSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailSettingController extends Controller
{
    /**
     * Store or update mail settings for a named form/modal.
     * English comment: This endpoint accepts `name`, `batch_size` and `delay` and upserts a record.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'batch_size' => 'nullable|integer|min:1',
            'delay' => 'nullable|integer|min:0',
            'activity_ids' => 'sometimes|array',
            'activity_ids.*' => 'integer|exists:activities,id',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        try {
            $settings = [
                'batch_size' => $data['batch_size'] ?? null,
                'delay' => $data['delay'] ?? null,
            ];

            foreach (['activity_ids', 'product_ids'] as $selection) {
                if (array_key_exists($selection, $data)) {
                    $settings[$selection] = array_values(array_unique(array_map('intval', $data[$selection])));
                }
            }

            MailSetting::updateOrCreate(
                ['name' => $data['name']],
                $settings
            );

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('[MailSettingController] store failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }
    }
}
