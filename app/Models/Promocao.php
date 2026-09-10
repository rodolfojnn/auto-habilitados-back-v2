<?php

namespace App\Models;

class Promocao extends BaseModel
{

  const UPDATED_AT = null;

  protected $table = 'promocao';

  protected $hidden = [];

  protected $fillable = [];

  protected $casts = [
    'price2'    => 'double',
    'price5'    => 'double',
    'price10'   => 'double',
    'vantagens' => 'object',
  ];

}
