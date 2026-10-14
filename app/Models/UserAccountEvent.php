<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserAccountEvent extends Model
{
    protected $guarded=['id'];
    protected $casts=['metadata'=>'array'];
    protected $hidden=['payload_hash'];
}
