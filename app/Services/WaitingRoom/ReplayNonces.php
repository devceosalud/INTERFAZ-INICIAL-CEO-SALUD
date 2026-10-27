<?php
namespace App\Services\WaitingRoom;
use Illuminate\Support\Facades\Cache;
final class ReplayNonces {
 public function claim(string $installation,string $nonce):bool {
  $hash=hash('sha256',$installation.':'.$nonce);
  if(Cache::has('wr-nonce:'.$hash))return false; // Preserve the previous live nonce window during upgrade.
  $key='wr-nonces:'.hash('sha256',$installation);$lock=Cache::lock($key.':lock',3);if(!$lock->get())return false;
  try{$now=time();$map=array_filter(Cache::get($key,[]),fn($expires)=>$expires>$now);if(isset($map[$hash])||count($map)>180)return false;
   $map[$hash]=$now+90;Cache::put($key,$map,180);return true;
  }finally{$lock->release();}
 }
}
