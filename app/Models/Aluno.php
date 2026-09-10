<?php

namespace App\Models;

class Aluno extends BaseModel
{

  const UPDATED_AT = null;

  protected $table = 'aluno';

  protected $hidden = ['password', 'token'];

  protected $fillable = ['email', 'fone1'];

  protected $casts = [
    "lat"             => "double",
    "lng"             => "double",
    "termos"          => "object",
    "jornada"         => "object",
    "onboard"         => "object",
  ];

}
