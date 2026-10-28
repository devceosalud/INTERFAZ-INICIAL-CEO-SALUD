<?php
namespace App\Http\Middleware;

use App\Services\WaitingRoom\InstallationConfiguration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

final class WaitingRoomServiceAuth
{
    public function handle(Request $request, Closure $next)
    {
        $authStart=microtime(true);
        if (!config('waiting_room.enabled')) return response()->json(['error'=>'not_found'], 404);
        $local = config('waiting_room.allow_loopback_http') && app()->environment(['testing','integration'])
            && in_array($request->ip(), ['127.0.0.1','::1'], true) && in_array($request->getHost(), ['127.0.0.1','localhost','::1'], true);
        if ((!$request->isSecure() && !$local) || $request->headers->has('Origin')) return $this->deny();
        $ipKey = 'wr-ip:'.hash('sha256', (string)$request->ip());
        if (RateLimiter::tooManyAttempts($ipKey, 180)) return $this->deny(429);
        RateLimiter::hit($ipKey, 60);
        try { $configuration = app(InstallationConfiguration::class)->read(); }
        catch (\Throwable $e) { return response()->json(['error'=>'integration_unavailable'],503); }
        $id = (string)$request->header('X-WR-Key');
        $installation = $configuration['installations'][$id] ?? null;
        $timestamp = (string)$request->header('X-WR-Timestamp');
        $nonce = (string)$request->header('X-WR-Nonce');
        $signature = (string)$request->header('X-WR-Signature');
        if (!is_array($installation) || ($installation['active'] ?? false) !== true
            || !is_string($installation['secret'] ?? null) || strlen($installation['secret']) < 32
            || !preg_match('/^[0-9]{10}$/D', $timestamp) || abs(time()-(int)$timestamp) > config('waiting_room.clock_skew_seconds')
            || !preg_match('/^[a-f0-9]{32,64}$/D', $nonce) || !preg_match('/^[a-f0-9]{64}$/D', $signature)) return $this->deny();
        $canonical = $request->method()."\n".$request->getRequestUri()."\n".$timestamp."\n".$nonce."\n".hash('sha256',$request->getContent());
        if($request->headers->has('If-None-Match')){
            $tag=$request->header('If-None-Match');if(!preg_match('/^"[a-f0-9]{64}"$/D',$tag))return $this->deny();$canonical.="\n".$tag;
        }
        $hmacStart=microtime(true);$expected=hash_hmac('sha256',$canonical,$installation['secret']);$request->attributes->set('wr_hmac_us',(microtime(true)-$hmacStart)*1000000);
        if(!hash_equals($expected,$signature))return $this->deny();
        if (!app(\App\Services\WaitingRoom\ReplayNonces::class)->claim($id,$nonce)) return $this->deny();
        $limitKey = 'wr-installation:'.hash('sha256',$id);
        if (RateLimiter::tooManyAttempts($limitKey, config('waiting_room.requests_per_minute'))) return $this->deny(429);
        RateLimiter::hit($limitKey,60);
        if ($request->query() !== []) return response()->json(['error'=>'invalid_request'],422);
        $request->attributes->set('waiting_room_installation', array_merge($installation,['key_id'=>$id]));
        $request->attributes->set('waiting_room_configuration', $configuration);
        $request->attributes->set('wr_auth_ms',(microtime(true)-$authStart)*1000);
        return $next($request);
    }
    private function deny(int $status=401) { return response()->json(['error'=>'unauthorized'], $status)->header('Cache-Control','no-store'); }
}
