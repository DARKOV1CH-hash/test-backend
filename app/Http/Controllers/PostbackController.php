<?php

namespace App\Http\Controllers;

use App\Models\Postback;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PostbackController extends Controller
{
    public function __construct(private readonly PartnerService $partner) {}

    public function receive(Request $request): JsonResponse
    {
        $clickId = trim((string) $request->query('click_id', ''));
        $event   = trim((string) $request->query('event', ''));
        $amount  = $request->query('amount');

        if ($clickId === '' || $event === '' || !$this->isValidAmount($amount)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid parameters'], 422);
        }

        $amount = (float) $amount;

        $dedupeKey = $this->dedupeKey($clickId, $event, $amount);

        // Atomic SET NX — prevents race condition on concurrent identical requests
        if (!Redis::set($dedupeKey, 1, 'EX', 86400, 'NX')) {
            return response()->json(['status' => 'duplicate', 'message' => 'Event already received']);
        }

        $postback = Postback::create([
            'click_id' => $clickId,
            'event'    => $event,
            'amount'   => $amount,
            'status'   => 'pending',
        ]);

        $this->partner->send($postback);

        return response()->json(['status' => 'accepted', 'message' => 'Event received']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Postback::orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $postbacks = $query->paginate(50);

        return response()->json($postbacks);
    }

    private function isValidAmount(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (!is_numeric($value)) {
            return false;
        }
        return (float) $value >= 0;
    }

    private function dedupeKey(string $clickId, string $event, float $amount): string
    {
        return sprintf('postback:dedupe:%s', hash('sha256', $clickId . '|' . $event . '|' . number_format($amount, 4, '.', '')));
    }
}
