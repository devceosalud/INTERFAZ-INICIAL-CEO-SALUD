<?php

namespace App\Http\Controllers\Api\channel;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    //

    public function search(Request $request)
    {
        $channel = Channel::find($request->channel_id);

        if (!$channel) {
            return response()->json(['message' => 'no encontrado'], 404);
        } else {
            return response()->json([
                'message' => 'encontrado',
                'channel' => $channel
            ], 200);
        }
    }
}
