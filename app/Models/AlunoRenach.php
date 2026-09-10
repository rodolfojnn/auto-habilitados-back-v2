<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class AlunoRenach extends BaseModel
{

  use SoftDeletes;

  protected $table = 'alunoRenach';

  protected $fillable = ['renach'];

  protected $casts = [
  ];

}
