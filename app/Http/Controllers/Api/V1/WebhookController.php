<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiKey;
use App\Models\WebhookSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WebhookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ApiKey $keyRecord */
        $keyRecord = $request->attributes->get('api_key_record');

        if (! in_array($keyRecord->plan, ['pro', 'ultra'])) {
            return response()->json([
                'success' => false,
                'error' => 'Webhooks require Pro or Ultra plan.',
            ], 403);
        }

        $subs = WebhookSubscription::where('api_key_id', $keyRecord->id)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'webhooks' => $subs,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var ApiKey $keyRecord */
        $keyRecord = $request->attributes->get('api_key_record');

        if (! in_array($keyRecord->plan, ['pro', 'ultra'])) {
            return response()->json([
                'success' => false,
                'error' => 'Webhooks require Pro or Ultra plan.',
            ], 403);
        }

        $count = WebhookSubscription::where('api_key_id', $keyRecord->id)
            ->where('is_active', true)
            ->count();

        $max = $keyRecord->plan === 'ultra' ? 50 : 10;
        if ($count >= $max) {
            return response()->json([
                'success' => false,
                'error' => "Maximum {$max} active webhooks allowed for your plan.",
            ], 429);
        }

        $validated = $request->validate([
            'url' => 'required|url|max:500',
            'event_type' => 'required|in:price_drop,deal_alert',
            'game_name' => 'nullable|string|max:255',
            'threshold_price' => 'nullable|numeric|min:0',
        ]);

        $sub = WebhookSubscription::create([
            'api_key_id' => $keyRecord->id,
            'url' => $validated['url'],
            'event_type' => $validated['event_type'],
            'game_name' => $validated['game_name'] ?? null,
            'threshold_price' => $validated['threshold_price'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'webhook' => $sub,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var ApiKey $keyRecord */
        $keyRecord = $request->attributes->get('api_key_record');

        $sub = WebhookSubscription::where('id', $id)
            ->where('api_key_id', $keyRecord->id)
            ->first();

        if (! $sub) {
            return response()->json([
                'success' => false,
                'error' => 'Webhook not found.',
            ], 404);
        }

        $sub->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook deactivated.',
        ]);
    }
}
