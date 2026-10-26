<?php
namespace App\Http\Controllers\Integration;
use Illuminate\Http\Request;
use App\Services\WaitingRoom\WaitingRoomSnapshot;
final class WaitingRoomSnapshotController
{
    public function __invoke(Request $request, WaitingRoomSnapshot $snapshot)
    {
        try { $payload=$snapshot->make($request->attributes->get('waiting_room_installation'),$request->attributes->get('waiting_room_configuration')); }
        catch (\Throwable $e) { return response()->json(['error'=>'integration_unavailable'],503)->header('Cache-Control','no-store'); }
        return response()->json($payload)->header('Cache-Control','private, no-store')->header('X-Content-Type-Options','nosniff');
    }
}
