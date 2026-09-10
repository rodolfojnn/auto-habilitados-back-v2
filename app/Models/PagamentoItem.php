<?php

namespace App\Models;

class PagamentoItem extends BaseModel
{

  protected $table = 'pagamentoItem';

  protected $hidden = [];

  protected $fillable = ['asaas_id'];

  protected $casts = [
    'vDevido'     => 'double',
    'vPago'       => 'double',
    'vDevidoRent' => 'double',
    'vPagoRent'   => 'double',
  ];

}
