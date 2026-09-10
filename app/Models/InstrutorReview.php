<?php

namespace App\Models;

class InstrutorReview extends BaseModel
{

  const UPDATED_AT = null;

  protected $table = 'instrutorReview';

  protected $hidden = [];

  protected $casts = [
  ];

  public function aluno() {
    return $this->belongsTo(Aluno::class)->select('id', 'nome');
  }

}
