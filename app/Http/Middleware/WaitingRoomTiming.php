<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
final class WaitingRoomTiming {
 public function handle(Request $r,Closure $next){
  if(!app()->environment('integration')||!config('waiting_room.metrics_file'))return $next($r);
  $start=microtime(true);$bootstrap=($start-(defined('LARAVEL_START')?LARAVEL_START:$start))*1000;$count=0;$sql=0;
  DB::listen(function($q)use(&$count,&$sql){$count++;$sql+=$q->time;});
  $response=$next($r);$total=(microtime(true)-(defined('LARAVEL_START')?LARAVEL_START:$start))*1000;
  $op=function_exists('opcache_get_status')?opcache_get_status(false):false;
  $metrics=['at'=>gmdate('c'),'opcache_on'=>(bool)($op['opcache_enabled']??false),'opcache_used_mib'=>round(($op['memory_usage']['used_memory']??0)/1048576,2),'opcache_hits'=>$op['opcache_statistics']['hits']??0,'route'=>$r->route()?->getName()?:$r->path(),'status'=>$response->getStatusCode(),'bootstrap_ms'=>round($bootstrap,3),'total_ms'=>round($total,3),'sql_count'=>$count,'sql_ms'=>round($sql,3),
   'auth_ms'=>round($r->attributes->get('wr_auth_ms',0),3),'hmac_us'=>round($r->attributes->get('wr_hmac_us',0),3),
   'upstream_ms'=>round($r->attributes->get('wr_upstream_ms',0),3),'upstream_status'=>$r->attributes->get('wr_upstream_status'),
   'projection_cache_hit'=>(bool)$r->attributes->get('wr_projection_hit',false),'reason'=>$r->attributes->get('wr_reason')];
  $response->headers->set('Server-Timing',sprintf('bootstrap;dur=%.3f, app;dur=%.3f, sql;dur=%.3f, auth;dur=%.3f, upstream;dur=%.3f',$bootstrap,$total-$bootstrap,$sql,$metrics['auth_ms'],$metrics['upstream_ms']));
  $response->headers->set('X-WR-Sql-Count',(string)$count);
  file_put_contents(config('waiting_room.metrics_file'),json_encode($metrics).PHP_EOL,FILE_APPEND|LOCK_EX);
  return $response;
 }
}
