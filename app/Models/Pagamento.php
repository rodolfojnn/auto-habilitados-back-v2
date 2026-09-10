<?php

namespace App\Models;

class Pagamento extends BaseModel
{

  protected $table = 'pagamento';

  protected $hidden = [];

  protected $fillable = ['asaas_id'];

  public static $snakeAttributes = false;

  protected $casts = [
    'vLiquido'    => 'double',
    'vTaxado'     => 'double',
    'webhookJ'    => 'object',
    'valoresJ'    => 'object',
    'responseJ'   => 'object',
  ];

  public function pagamentoItem() {
    return $this->hasMany(PagamentoItem::class)->select('id', 'pagamento_id', 'created_at', 'estimated_at', 'parcela', 'parcelas', 'vDevido', 'vPago', 'vDevidoRent', 'vPagoRent');
  }

  public function aula() {
    return $this->hasOne(Aula::class)->select('id', 'pagamento_id', 'status', 'categoria', 'pacote', 'rent', 'kmDesloc');
  }

  public function aluno() {
    return $this->belongsTo(Aluno::class)->select(['id', 'nome']);
  }
}
