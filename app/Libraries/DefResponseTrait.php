<?php

namespace App\Libraries;

trait DefResponseTrait {

  private static $additionalFields = [];

  /**
   * @param mixed $data
   * @param bool $status
   * @return \Illuminate\Http\JsonResponse
   */
  public static function response($data=null, $status=true) {
    // Resposta HTTP padrão
    if (is_numeric($status)) {
      return response()->json($data, $status);
    }
    if (is_bool($status)) {
      $ret = array_merge(['status' => $status, 'data' => $data], self::$additionalFields);
      return response()->json($ret);
    }
  }

  // responseAdditionalFields(['warning' => 'Atenção! Pedido lançado mas vendedor...']);
  public static function responseAdditionalFields($data) {
    self::$additionalFields = array_merge(self::$additionalFields, $data);
  }

}