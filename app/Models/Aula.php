<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;

class Aula extends BaseModel
{

  const UPDATED_AT = null;

  protected $table = 'aula';

  protected $hidden = [];

  protected $fillable = ['pagamento_id'];

  protected $casts = [
    "aulasJ"          => "object",
  ];

  public function aluno() {
    return $this->belongsTo(Aluno::class)->select(['id', 'nome', 'fone1', 'email', 'municipio', 'uf', 'logradouro', 'bairro', 'numero']);
  }

  public function instrutor() {
    return $this->belongsTo(Instrutor::class)->select(['id', 'nome', 'fone1', 'email', 'municipio', 'uf', 'logradouro', 'bairro', 'numero']);
  }

  public function pagamento() {
    return $this->belongsTo(Pagamento::class)->select(['id', 'valoresJ', 'forma', 'parcelas']);
  }

}
