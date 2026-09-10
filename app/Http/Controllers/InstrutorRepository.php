<?php

namespace App\Http\Controllers;

use App\Libraries\Helpers;
use App\Models\Aluno;
use App\Models\Instrutor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InstrutorRepository {

  public static function getById($instrutor_id) {
    // App\Http\Controllers\InstrutorRepository::getById_1
    return Cache::remember(__METHOD__ . '_' . $instrutor_id, 3600, function () use ($instrutor_id) {
      return Instrutor::find($instrutor_id);
    });
  }

  public static function clearCache($instrutor_id) {
    Cache::forget('App\Http\Controllers\InstrutorRepository::getById_' . $instrutor_id);
  }

  // Distância "real" (em km) entre o aluno e o instrutor, calculada por seus IDs.
  // Usa a mesma convenção do resto da aplicação: km_real = Haversine * 1.4.
  // Retorna 0 quando um dos dois não possui coordenadas.
  public static function getDistanceAluno($aluno_id, $instrutor_id) {
    $aluno     = Aluno::find($aluno_id);
    $instrutor = Instrutor::find($instrutor_id);

    if (!$aluno?->lat || !$aluno?->lng || !$instrutor?->lat || !$instrutor?->lng) {
      return 0;
    }

    // km_linear = distância em linha reta (Haversine)
    $km_linear = round(
      6371 * acos(
        max(-1, min(1,
          cos(deg2rad($aluno->lat))
          * cos(deg2rad($instrutor->lat))
          * cos(deg2rad($instrutor->lng) - deg2rad($aluno->lng))
          + sin(deg2rad($aluno->lat)) * sin(deg2rad($instrutor->lat))
        ))
      ),
      2
    );

    // km_real = km_linear * 1.4 (fator de correção p/ distância real de direção)
    return Helpers::N2($km_linear * 1.4);
  }

  public static function proximosLinear($lat, $lng, $raio = 20) {
    $instrutores = Instrutor::select(
      DB::raw("
        lat, lng, id, municipio, status, nome, fone1, carOwn, carAluno, bikeOwn, bikeAluno,
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
    ->where('ativo', '>=', 1)
    ->having('km_linear', '<', $raio)
    ->orderBy('km_linear')
    ->get();

    return $instrutores;
  }

  public static function proximoAlunoId($instrutor_id, $aluno_id) {
    $aluno = Aluno::find($aluno_id);

    // Instrutor
    $select = "id,
      CONCAT(UCASE(LEFT(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 1)),
      LCASE(SUBSTRING(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 2))) as nome,
      uf, municipio, bairro, lat, lng, status, ativo, selo,
      nota, notaQtd, vDesc5, vDesc10, vDesc15, vDesc20, fotosCarN, fotosBkN,
      carOwn, vCarOwn, vCarOwnKm, vCarRent, carAluno, vCarAluno, vCarAlunoKm,
      bikeOwn, vBikeOwn, vBikeOwnKm, vBikeRent, bikeAluno, vBikeAluno, vBikeAlunoKm, kmMax,
      veMarca, veModelo, veAno, veTipo, veCambio,
      bkMarca, bkModelo, bkAno,
      descricao, vTaxaApp, destaque,
      vCI2, vCI4, vCI6, vCI8, vCI10, vCA2, vCA4, vCA6, vCA8, vCA10, vMI2, vMI4, vMI6, vMI8, vMI10, vMA2, vMA4, vMA6, vMA8, vMA10";
    $instrutor = Instrutor::select(
      DB::raw("
        $select,
        ROUND(
            6371 * acos(
                cos(radians({$aluno->lat}))
                * cos(radians(lat))
                * cos(radians(lng) - radians({$aluno->lng}))
                + sin(radians({$aluno->lat})) * sin(radians(lat))
            ),
        2
        ) AS km_linear
      ")
    )
    ->where('id', '=', $instrutor_id)
    ->first();
    if (!$instrutor) return null;

    // Directions
    // $instrutor->km_real = GMapsRepository::distanceKm($aluno->lat, $aluno->lng, $instrutor->lat, $instrutor->lng);
    $instrutor->km_real = Helpers::N2($instrutor->km_linear * 1.4);

    // Dados da base do valor real
    $instrutor->instrutor           = clone $instrutor;

    $instrutor->vCarOwn       = round($instrutor->vCarOwn + ($instrutor->vCarOwn * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCarAluno     = round($instrutor->vCarAluno + ($instrutor->vCarAluno * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vBikeOwn      = round($instrutor->vBikeOwn + ($instrutor->vBikeOwn * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vBikeAluno    = round($instrutor->vBikeAluno + ($instrutor->vBikeAluno * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCarOwnKm     = round($instrutor->vCarOwnKm + ($instrutor->vCarOwnKm * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vCarRent      = round($instrutor->vCarRent + ($instrutor->vCarRent * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vCarAlunoKm   = round($instrutor->vCarAlunoKm + ($instrutor->vCarAlunoKm * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vBikeOwnKm    = round($instrutor->vBikeOwnKm + ($instrutor->vBikeOwnKm * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vBikeRent     = round($instrutor->vBikeRent + ($instrutor->vBikeRent * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vBikeAlunoKm  = round($instrutor->vBikeAlunoKm + ($instrutor->vBikeAlunoKm * ($instrutor->vTaxaApp / 100)), 3);

    // Taxa nos novos campos também
    $instrutor->vCI2       = round($instrutor->vCI2 + ($instrutor->vCI2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI4       = round($instrutor->vCI4 + ($instrutor->vCI4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI6       = round($instrutor->vCI6 + ($instrutor->vCI6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI8       = round($instrutor->vCI8 + ($instrutor->vCI8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI10      = round($instrutor->vCI10 + ($instrutor->vCI10 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA2       = round($instrutor->vCA2 + ($instrutor->vCA2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA4       = round($instrutor->vCA4 + ($instrutor->vCA4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA6       = round($instrutor->vCA6 + ($instrutor->vCA6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA8       = round($instrutor->vCA8 + ($instrutor->vCA8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA10      = round($instrutor->vCA10 + ($instrutor->vCA10 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI2       = round($instrutor->vMI2 + ($instrutor->vMI2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI4       = round($instrutor->vMI4 + ($instrutor->vMI4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI6       = round($instrutor->vMI6 + ($instrutor->vMI6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI8       = round($instrutor->vMI8 + ($instrutor->vMI8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI10      = round($instrutor->vMI10 + ($instrutor->vMI10 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA2       = round($instrutor->vMA2 + ($instrutor->vMA2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA4       = round($instrutor->vMA4 + ($instrutor->vMA4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA6       = round($instrutor->vMA6 + ($instrutor->vMA6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA8       = round($instrutor->vMA8 + ($instrutor->vMA8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA10      = round($instrutor->vMA10 + ($instrutor->vMA10 * ($instrutor->vTaxaApp / 100)), 2);

    return $instrutor;
  }

  public static function hash($hash) {
    $select = "id,
      CONCAT(UCASE(LEFT(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 1)),
      LCASE(SUBSTRING(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 2))) as nome,
      uf, municipio, bairro, lat, lng, status, ativo, selo,
      nota, notaQtd, vDesc5, vDesc10, vDesc15, vDesc20, fotosCarN, fotosBkN,
      carOwn, vCarOwn, vCarOwnKm, vCarRent, carAluno, vCarAluno, vCarAlunoKm,
      bikeOwn, vBikeOwn, vBikeOwnKm, vBikeRent, bikeAluno, vBikeAluno, vBikeAlunoKm, kmMax,
      veMarca, veModelo, veAno, veTipo, veCambio,
      bkMarca, bkModelo, bkAno,
      descricao, vTaxaApp, destaque,
      vCI2, vCI4, vCI6, vCI8, vCI10, vCA2, vCA4, vCA6, vCA8, vCA10, vMI2, vMI4, vMI6, vMI8, vMI10, vMA2, vMA4, vMA6, vMA8, vMA10";
    $instrutor = Instrutor::selectRaw($select)
      ->where('id', $hash)
      ->where('ativo', '>=', 1)
      ->first();
    if (!$instrutor) return null;

    // Dados da base do valor real
    $instrutor->km_real       = 0;
    $instrutor->km_linear     = 0;
    $instrutor->vCarOwn       = round($instrutor->vCarOwn + ($instrutor->vCarOwn * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCarAluno     = round($instrutor->vCarAluno + ($instrutor->vCarAluno * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vBikeOwn      = round($instrutor->vBikeOwn + ($instrutor->vBikeOwn * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vBikeAluno    = round($instrutor->vBikeAluno + ($instrutor->vBikeAluno * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCarOwnKm     = round($instrutor->vCarOwnKm + ($instrutor->vCarOwnKm * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vCarRent      = round($instrutor->vCarRent + ($instrutor->vCarRent * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vCarAlunoKm   = round($instrutor->vCarAlunoKm + ($instrutor->vCarAlunoKm * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vBikeOwnKm    = round($instrutor->vBikeOwnKm + ($instrutor->vBikeOwnKm * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vBikeRent     = round($instrutor->vBikeRent + ($instrutor->vBikeRent * ($instrutor->vTaxaApp / 100)), 3);
    $instrutor->vBikeAlunoKm  = round($instrutor->vBikeAlunoKm + ($instrutor->vBikeAlunoKm * ($instrutor->vTaxaApp / 100)), 3);

    // Taxa nos novos campos também
    $instrutor->vCI2       = round($instrutor->vCI2 + ($instrutor->vCI2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI4       = round($instrutor->vCI4 + ($instrutor->vCI4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI6       = round($instrutor->vCI6 + ($instrutor->vCI6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI8       = round($instrutor->vCI8 + ($instrutor->vCI8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCI10      = round($instrutor->vCI10 + ($instrutor->vCI10 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA2       = round($instrutor->vCA2 + ($instrutor->vCA2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA4       = round($instrutor->vCA4 + ($instrutor->vCA4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA6       = round($instrutor->vCA6 + ($instrutor->vCA6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA8       = round($instrutor->vCA8 + ($instrutor->vCA8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vCA10      = round($instrutor->vCA10 + ($instrutor->vCA10 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI2       = round($instrutor->vMI2 + ($instrutor->vMI2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI4       = round($instrutor->vMI4 + ($instrutor->vMI4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI6       = round($instrutor->vMI6 + ($instrutor->vMI6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI8       = round($instrutor->vMI8 + ($instrutor->vMI8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMI10      = round($instrutor->vMI10 + ($instrutor->vMI10 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA2       = round($instrutor->vMA2 + ($instrutor->vMA2 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA4       = round($instrutor->vMA4 + ($instrutor->vMA4 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA6       = round($instrutor->vMA6 + ($instrutor->vMA6 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA8       = round($instrutor->vMA8 + ($instrutor->vMA8 * ($instrutor->vTaxaApp / 100)), 2);
    $instrutor->vMA10      = round($instrutor->vMA10 + ($instrutor->vMA10 * ($instrutor->vTaxaApp / 100)), 2);

    return $instrutor;
  }

}