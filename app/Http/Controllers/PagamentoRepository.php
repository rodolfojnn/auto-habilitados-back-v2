<?php

namespace App\Http\Controllers;

use App\Models\Instrutor;
use Illuminate\Support\Facades\Log;

class PagamentoRepository {

  public static $mapCategVal = [
    'carOwn'    => 'vCarOwn',
    'carAluno'  => 'vCarAluno',
    'bikeOwn'   => 'vBikeOwn',
    'bikeAluno' => 'vBikeAluno'
  ];

  // Prefixo dos campos de pacote (totais já com desconto embutido).
  // O sufixo é o número de aulas do pacote (2, 4, 6, 8, 10).
  public static $mapCategV2 = [
    'carOwn'    => 'vCI',
    'carAluno'  => 'vCA',
    'bikeOwn'   => 'vMI',
    'bikeAluno' => 'vMA'
  ];

  public static function getTotalPagamento($instrutor_id, $forma, $categoria, $pacote, $parcela, $km_real = 0, $aluguel = false, $kmDesloc = false) {
    $instrutor = Instrutor::find($instrutor_id);
    if ($km_real < 0) $km_real = 0;

    // Dados
    $vRent              = 0;
    $vKm                = 0;
    $aDescInstrutor     = data_get($instrutor, 'vDesc' . $pacote, 0);
    $vJurosAsaas        = 0;

    // ----- Aluguel
    if ($aluguel) {
      if ($categoria === 'carOwn' || $categoria === 'carAluno') {
        $vRent = $instrutor->vCarRent;
      } else {
        $vRent = $instrutor->vBikeRent;
      }
    }

    // ----- kmDesl
    if ($kmDesloc) {
      $vKm = round($km_real * data_get($instrutor, self::$mapCategVal[$categoria] . 'Km'), 3);
    }

    $vInstrAula           = data_get($instrutor, self::$mapCategVal[$categoria]);
    $vInstrKm             = $vKm;
    $vInstrAulaPac        = round(($vInstrAula + $vInstrKm) * $pacote, 3);
    $vInstrDesc           = round($vInstrAulaPac * ($aDescInstrutor / 100), 3);
    $vInstrFinal          = round($vInstrAulaPac - $vInstrDesc, 3);
    $vDirigirSubTotal     = round($vInstrFinal + ($vInstrFinal * ($instrutor->vTaxaApp / 100)), 3);
    $vDirigirRent         = round($vRent + ($vRent * ($instrutor->vTaxaApp / 100)), 2);
    $vDirigirTotal        = round($vDirigirSubTotal + $vDirigirRent, 2);

    if ($forma === 'Cartão de Crédito') {
      $calcParcelas   = self::calcParcelas($vDirigirSubTotal);
      $vJurosAsaas    = round($calcParcelas[$parcela - 1]['vTotal'] - $vDirigirSubTotal, 2);
      $vDirigirTotal  = $calcParcelas[$parcela - 1]['vTotal'];
    }

    $res = [
      'aDescInstrutor'    => $aDescInstrutor,
      'vInstrAula'        => $vInstrAula,
      'vInstrKm'          => $vInstrKm,
      'vInstrAulaPac'     => $vInstrAulaPac,
      'vInstrDesc'        => $vInstrDesc,
      'vInstrFinal'       => $vInstrFinal,
      'vInstrRent'        => $vRent,
      'vDirigirSubTotal'  => $vDirigirSubTotal,
      'vDirigirTotal'     => $vDirigirTotal,
      'vJurosAsaas'       => $vJurosAsaas,
      'vDirigirRent'      => $vDirigirRent
    ];
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($res));

    return $res;
  }

  public static function getTotalPagamentoV2($instrutor_id, $forma, $categoria, $pacote, $parcela, $km_real = 0, $aluguel = false, $kmDesloc = false) {
    $instrutor = Instrutor::find($instrutor_id);
    if ($km_real < 0) $km_real = 0;

    // Dados
    $vRent              = 0;
    $vKm                = 0;
    $vJurosAsaas        = 0;

    // ----- Aluguel (mesma lógica)
    if ($aluguel) {
      if ($categoria === 'carOwn' || $categoria === 'carAluno') {
        $vRent = $instrutor->vCarRent;
      } else {
        $vRent = $instrutor->vBikeRent;
      }
    }

    // ----- kmDesl (mesma lógica)
    if ($kmDesloc) {
      $vKm = round($km_real * data_get($instrutor, self::$mapCategVal[$categoria] . 'Km'), 3);
    }

    // Valor do pacote já é o total (inclui desconto), ex.: vCI10, vCA6, vMI4, vMA2...
    $vInstrAula           = data_get($instrutor, self::$mapCategV2[$categoria] . $pacote, 0);
    $vInstrKm             = $vKm;
    $vInstrAulaPac        = $vInstrAula;                                 // já é o total do pacote
    $vInstrDesc           = 0;                                           // desconto já embutido no pacote
    $vInstrFinal          = round($vInstrAula + ($vInstrKm * $pacote), 3);
    $vDirigirSubTotal     = round($vInstrFinal + ($vInstrFinal * ($instrutor->vTaxaApp / 100)), 3);
    $vDirigirRent         = round($vRent + ($vRent * ($instrutor->vTaxaApp / 100)), 2);
    $vDirigirTotal        = round($vDirigirSubTotal + $vDirigirRent, 2);

    if ($forma === 'Cartão de Crédito') {
      $calcParcelas   = self::calcParcelas($vDirigirSubTotal);
      $vJurosAsaas    = round($calcParcelas[$parcela - 1]['vTotal'] - $vDirigirSubTotal, 2);
      $vDirigirTotal  = $calcParcelas[$parcela - 1]['vTotal'];
    }

    $res = [
      'aDescInstrutor'    => 0,
      'vInstrAula'        => $vInstrAula,
      'vInstrKm'          => $vInstrKm,
      'vInstrAulaPac'     => $vInstrAulaPac,
      'vInstrDesc'        => $vInstrDesc,
      'vInstrFinal'       => $vInstrFinal,
      'vInstrRent'        => $vRent,
      'vDirigirSubTotal'  => $vDirigirSubTotal,
      'vDirigirTotal'     => $vDirigirTotal,
      'vJurosAsaas'       => $vJurosAsaas,
      'vDirigirRent'      => $vDirigirRent
    ];
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($res));

    return $res;
  }

  public static function calcParcelas(float $valor): array {
    // Juros específico de cada parcela (0 = sem juros)
    // $jurosPorParcela = [2.99, 3.49, 3.49, 3.49, 3.49, 3.49, 3.99, 3.99, 3.99, 3.99];
    $jurosPorParcela = [2.99, 3.49, 3.49, 3.49, 3.49, 3.49];

    // Taxa fixa
    $taxaFixa = 0.49;

    $resultados = [];

    for ($n = 1; $n <= count($jurosPorParcela); $n++) {

        $juros = $jurosPorParcela[$n - 1];

        // Juros percentual aplicado sobre TOTAL (simples)
        $taxaPercentual = $juros / 100;

        // Cálculo igual ao JS
        $total = ($valor + $taxaFixa) / (1 - $taxaPercentual);

        $parcela = $total / $n;

        $resultados[] = [
            "parcela" => $n,
            "vParcela" => round($parcela, 2),
            "vTotal"   => round($total, 2),
            "juros"    => $juros
        ];
    }

    return $resultados;
  }

  /**
   * Retorna o valor total a pagar para um número específico de parcelas,
   * aplicando taxas e juros definidos em calcParcelas().
   *
   * @param float $valor   Valor base (subtotal) sobre o qual calcular.
   * @param int   $parcela Número de parcelas desejado (1-based).
   * @return float Valor total a cobrar para esas parcelas.
   */
  public static function calcTotalParcelas(float $valor, int $parcela): float {
    $calcParcelas = self::calcParcelas($valor);
    $index        = $parcela - 1;

    if (!isset($calcParcelas[$index])) {
      throw new \Exception('Número de parcelas inválido.');
    }

    return $calcParcelas[$index]['vTotal'];
  }

}