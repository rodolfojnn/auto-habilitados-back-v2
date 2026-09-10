<?php

namespace App\Models;

class PushToken extends BaseModel
{

  protected $table = 'pushToken';

  protected $fillable = ['fcm_token'];

}
