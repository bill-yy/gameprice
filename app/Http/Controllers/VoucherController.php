<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    public function index(): JsonResponse
    {
        $vouchers = Voucher::with('store')
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->get();

        return response()->json($vouchers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'code' => 'required|string|max:50',
            'discount_value' => 'required|numeric|min:0',
            'discount_type' => 'required|in:fixed,percentage',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
        ]);

        $voucher = Voucher::create($validated);

        return response()->json($voucher->load('store'), 201);
    }

    public function show(Store $store): JsonResponse
    {
        $vouchers = Voucher::where('store_id', $store->id)
            ->where('is_active', true)
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->get();

        return response()->json($vouchers);
    }
}
