<?php

namespace App\Models;

class ChatList extends BaseModel
{

  protected $table = 'chatList';

  protected $hidden = ['threadId'];

  protected $fillable = ['aluno_id', 'instrutor_id'];

  protected $casts = [
    'proposta' => 'object',
  ];

  public function instrutor() {
    return $this->belongsTo(Instrutor::class)->selectRaw("id, SUBSTRING_INDEX(nome, ' ', 1) as nome");
  }

  public function aluno() {
    return $this->belongsTo(Aluno::class)->selectRaw("id, SUBSTRING_INDEX(nome, ' ', 1) as nome");
  }

}
