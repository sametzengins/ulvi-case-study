<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerEntryResource;
use App\Models\LedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['balance' => (int) $request->user()->wallet()->value('balance')],
        ]);
    }

    public function ledger(Request $request): AnonymousResourceCollection
    {
        $entries = LedgerEntry::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return LedgerEntryResource::collection($entries);
    }
}
