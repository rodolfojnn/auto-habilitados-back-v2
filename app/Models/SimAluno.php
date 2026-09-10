<?php

namespace App\Models;

class SimAluno extends BaseModel
{

  const UPDATED_AT = null;

  protected $table = 'simAluno';

  protected $fillable = ['fone1', 'email', 'cep', 'nome'];

  protected $casts = [
    'extra' => 'object'
  ];

}
