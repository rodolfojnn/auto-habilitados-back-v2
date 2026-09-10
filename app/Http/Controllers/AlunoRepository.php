<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AlunoRepository {

  public static function getById($aluno_id) {
    // App\Http\Controllers\AlunoRepository::getById_1
    return Cache::remember(__METHOD__ . '_' . $aluno_id, 3600, function () use ($aluno_id) {
      return Aluno::find($aluno_id);
    });
  }

  public static function clearCache($aluno_id) {
    Cache::forget('App\Http\Controllers\AlunoRepository::getById_' . $aluno_id);
  }

  public static function proximosLinear($lat, $lng, $km = 50) {
    $alunos = Aluno::select(
      DB::raw("
        lat, lng, id, nome, fone1, uf, municipio, bairro, logradouro,
        ROUND(
            6371 * acos(
                cos(radians({$lat}))
                * cos(radians(lat))
                * cos(radians(lng) - radians({$lng}))
                + sin(radians({$lat})) * sin(radians(lat))
            ),
        2
        ) AS km_linear
      ")
    )
    ->where('ativo', true)
    ->having('km_linear', '<', $km)
    ->orderBy('km_linear')
    ->get();

    return $alunos;
  }

  public static function updateSolicitacaoByOnboard(int $aluno_id) {
    $aluno = Aluno::find($aluno_id);
    if (!$aluno->onboard) return;

    $onboard = $aluno->onboard;
    $partes = [];

    foreach ($onboard->categoria as $cat) {
      $campo = 'quantasAulas' . ucfirst($cat);
      $aulas = $onboard->{$campo} ?? null;

      if (is_numeric($aulas)) {
        $partes[] = ucfirst($cat) . ': ' . $aulas . ' aulas';
      } else {
        $partes[] = ucfirst($cat) . ': a definir';
      }
    }

    $frase = implode('; ', $partes);
    $aluno->solicitacao = $frase;
    $aluno->save();

    self::clearCache($aluno_id);
  }

}