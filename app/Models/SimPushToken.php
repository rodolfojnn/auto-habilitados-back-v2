<?php

namespace App\Models;

class SimPushToken extends BaseModel
{

  const UPDATED_AT = null;

  protected $table = 'simPushToken';

  protected $fillable = ['fcm_token'];

}
