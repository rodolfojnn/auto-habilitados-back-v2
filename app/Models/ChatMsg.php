<?php

namespace App\Models;

class ChatMsg extends BaseModel
{

  const UPDATED_AT = null;

  protected $table = 'chatMsg';

  protected $hidden = ['threadId'];

  protected $casts = [
    'oculto' => 'boolean',
    'exclusivo' => 'boolean',
  ];

}
