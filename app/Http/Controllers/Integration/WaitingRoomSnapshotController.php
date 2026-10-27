<?php
namespace App\Http\Controllers\Integration;
use Illuminate\Http\Request;
use App\Services\WaitingRoom\{WaitingRoomSnapshot,IntegrationFailure};
final class WaitingRoomSnapshotController {
 public function __invoke(Request $request,WaitingRoomSnapshot $snapshot){
  try{$payload=$snapshot->make($request->attributes->get('waiting_room_installation'),$request->attributes->get('waiting_room_configuration'));}
  catch(IntegrationFailure $e){$request->attributes->set('wr_reason',$e->reason);return response()->json(['error'=>'integration_unavailable','reason'=>$e->reason],$e->httpStatus)->header('Cache-Control','no-store')->header('X-WR-Reason',$e->reason);}
  catch(\Throwable $e){$request->attributes->set('wr_reason','projection_unavailable');return response()->json(['error'=>'integration_unavailable','reason'=>'projection_unavailable'],503)->header('Cache-Control','no-store')->header('X-WR-Reason','projection_unavailable');}
  $tag='"'.$payload['revision'].'"';$response=$request->header('If-None-Match')===$tag?response('',304):response()->json($payload);
  foreach(['ETag'=>$tag,'X-WR-Generated-At'=>$payload['generated_at'],'X-WR-Valid-Until'=>$payload['valid_until'],'X-WR-Sequence'=>(string)$payload['sequence'],'X-WR-Stream'=>$payload['stream_ref'],'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff'] as $k=>$v)$response->headers->set($k,$v);
  return $response;
 }
}
