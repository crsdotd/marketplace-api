<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LiveLocation;
use App\Models\LocationHistory;
use App\Models\MeetingPoint;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class LiveLocationController extends Controller
{
    /**
     * POST /api/v1/live-location/start
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:transactions,id',
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::findOrFail($request->transaction_id);
        $user        = $request->user();

        if (!$transaction->involves($user)) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($transaction->type !== 'cod') {
            return response()->json(['success' => false, 'message' => 'Fitur ini hanya untuk transaksi COD.'], 422);
        }

        // Cek sesi aktif yang sudah ada
        $existing = LiveLocation::where('transaction_id', $transaction->id)
            ->where('user_id', $user->id)
            ->where('is_sharing', true)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'success'    => true,
                'message'    => 'Sesi live location sudah aktif.',
                'data'       => $existing,
                'share_link' => url("/api/v1/live-location/track/{$existing->share_token}"),
            ]);
        }

        $liveLocation = LiveLocation::create([
            'transaction_id' => $transaction->id,
            'user_id'        => $user->id,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'is_sharing'     => true,
            'share_token'    => Str::random(40),
            'expires_at'     => Carbon::now()->addHours(6),
        ]);

        LocationHistory::create([
            'live_location_id' => $liveLocation->id,
            'latitude'         => $request->latitude,
            'longitude'        => $request->longitude,
            'recorded_at'      => now(),
        ]);

        return response()->json([
            'success'    => true,
            'message'    => 'Live location dimulai.',
            'data'       => $liveLocation,
            'share_link' => url("/api/v1/live-location/track/{$liveLocation->share_token}"),
        ], 201);
    }

    /**
     * PUT /api/v1/live-location/{id}/update
     */
    public function update(Request $request, LiveLocation $liveLocation): JsonResponse
    {
        if ($liveLocation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($liveLocation->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Sesi live location sudah berakhir.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'sometimes|numeric',
            'speed'     => 'sometimes|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $liveLocation->update([
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
        ]);

        LocationHistory::create([
            'live_location_id' => $liveLocation->id,
            'latitude'         => $request->latitude,
            'longitude'        => $request->longitude,
            'accuracy'         => $request->accuracy,
            'speed'            => $request->speed,
            'recorded_at'      => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Lokasi diperbarui.']);
    }

    /**
     * GET /api/v1/live-location/track/{token}
     */
    public function track(string $token): JsonResponse
    {
        $liveLocation = LiveLocation::with(['user', 'transaction'])
            ->where('share_token', $token)
            ->where('is_sharing', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$liveLocation) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak ditemukan atau sudah berakhir.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user'       => $liveLocation->user->only(['id', 'name', 'avatar']),
                'latitude'   => $liveLocation->latitude,
                'longitude'  => $liveLocation->longitude,
                'updated_at' => $liveLocation->updated_at,
                'expires_at' => $liveLocation->expires_at,
            ],
        ]);
    }

    /**
     * POST /api/v1/live-location/{id}/stop
     */
    public function stop(Request $request, LiveLocation $liveLocation): JsonResponse
    {
        if ($liveLocation->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $liveLocation->update(['is_sharing' => false]);
        return response()->json(['success' => true, 'message' => 'Live location dihentikan.']);
    }

    /**
     * POST /api/v1/live-location/meeting-point
     */
    public function proposeMeetingPoint(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:transactions,id',
            'name'           => 'required|string|max:255',
            'address'        => 'required|string',
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'scheduled_at'   => 'sometimes|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $transaction = Transaction::findOrFail($request->transaction_id);

        if (!$transaction->involves($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        $meetingPoint = MeetingPoint::create([
            'transaction_id' => $transaction->id,
            'name'           => $request->name,
            'address'        => $request->address,
            'latitude'       => $request->latitude,
            'longitude'      => $request->longitude,
            'proposed_by'    => $request->user()->id,
            'scheduled_at'   => $request->scheduled_at,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Titik temu berhasil diusulkan.',
            'data'    => $meetingPoint->load('proposer'),
        ], 201);
    }

    /**
     * PUT /api/v1/live-location/meeting-point/{id}/agree
     */
    public function agreeMeetingPoint(Request $request, MeetingPoint $meetingPoint): JsonResponse
    {
        $transaction = $meetingPoint->transaction;

        if (!$transaction->involves($request->user())) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($meetingPoint->proposed_by === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Tidak bisa menyetujui titik temu yang Anda usulkan sendiri.'], 422);
        }

        $meetingPoint->update(['status' => 'agreed']);

        return response()->json([
            'success' => true,
            'message' => 'Titik temu disetujui.',
            'data'    => $meetingPoint->fresh(),
        ]);
    }
}
