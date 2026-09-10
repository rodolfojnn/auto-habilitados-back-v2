<?php

namespace App\Models;

class Instrutor extends BaseModel
{

  protected $table = 'instrutor';

  protected $hidden = ['password', 'token'];

  protected $fillable = ['email', 'fone1'];

  protected $casts = [
    'nota'            => 'double',
    'notaQtd'         => 'integer',
    'vDesc5'          => 'double',
    'vDesc10'         => 'double',
    'vDesc15'         => 'double',
    'vDesc20'         => 'double',
    'lat'             => 'double',
    'lng'             => 'double',
    'veAno'           => 'integer',
    'bkAno'           => 'integer',
    'carOwn'          => 'boolean',
    'vCarOwn'         => 'double',
    'vCarOwnKm'       => 'double',
    'vCarRent'        => 'double',
    'carAluno'        => 'boolean',
    'vCarAluno'       => 'double',
    'vCarAlunoKm'     => 'double',
    'bikeOwn'         => 'boolean',
    'vBikeOwn'        => 'double',
    'vBikeOwnKm'      => 'double',
    'vBikeRent'       => 'double',
    'bikeAluno'       => 'boolean',
    'vBikeAluno'      => 'double',
    'vBikeAlunoKm'    => 'double',
    'kmMax'           => 'integer',
    'termos'          => 'object',
    "vCI2"            => "integer",
    "vCI4"            => "integer",
    "vCI6"            => "integer",
    "vCI8"            => "integer",
    "vCI10"           => "integer",
    "vCA2"            => "integer",
    "vCA4"            => "integer",
    "vCA6"            => "integer",
    "vCA8"            => "integer",
    "vCA10"           => "integer",
    "vMI2"            => "integer",
    "vMI4"            => "integer",
    "vMI6"            => "integer",
    "vMI8"            => "integer",
    "vMI10"           => "integer",
    "vMA2"            => "integer",
    "vMA4"            => "integer",
    "vMA6"            => "integer",
    "vMA8"            => "integer",
    "vMA10"           => "integer"
  ];

}
