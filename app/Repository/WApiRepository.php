<?php

namespace App\Repository;

use App\Models\Emp;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WApiRepository
{
  private static $instanceID  = 'LITE-Q2FE3D-0FRVNQ';
  private static $token       = 'V8u7srsKIp6btw0B2i3Ezl2cNWx1eKpLh';
  private static $endpoint    = 'https://api.w-api.app/v1/message/';

  public static function setupTokens($defaultSession = false) {
    // Pega da empresa app.alleshub
    if ($defaultSession) return;
    // if ($defaultSession) {
    //   $groupConfig = require base_path('config/databases/app.database.php');
    //   Config::set('database.connections', $groupConfig['connections']);
    //   Config::set('database.default', 'erp_alleshub');
    //   $emp = Emp::find($emp_id);
    //   self::$instanceID = data_get($emp, 'store.wapi.instanceID');
    //   self::$token      = data_get($emp, 'store.wapi.token');
    //   return;
    // }
    // Pegar a sessão da empresa conectada
    // $emp = EmpRepository::getById($emp_id);
    // self::$instanceID = data_get($emp, 'store.wapi.instanceID');
    // self::$token      = data_get($emp, 'store.wapi.token');
  }

  public static function sendMessage($phone, $message, $defaultSession = false) {
    Log::channel('wapi')->info(__METHOD__ . ' ' . $phone . ' ' . $message);

    // Validação
    self::setupTokens($defaultSession);
    if (!self::$instanceID || !self::$token) return Log::channel('wapi')->info(__METHOD__ . ' ' . 'Sessão inexistente');

    try {
      $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . self::$token,
        'Content-Type' => 'application/json',
      ])
      ->withoutVerifying()
      ->post(self::$endpoint . 'send-text?instanceId=' . self::$instanceID, [
        'phone' => '55' . $phone,
        'message' => $message,
      ]);
      $response->throw();
    } catch (\Throwable $th) {
      Log::channel('wapi')->error(__METHOD__ . ' ' . $th->getMessage());
      return false;
    }
    $data = $response->json();
    return $data;
  }

  // https://api.w-api.app/v1/quere/quere?instanceId={{INSTANCE_ID}}&perPage=10&page=1
  public static function verFila() {
    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . self::$token,
      'Content-Type' => 'application/json',
    ])
    ->withoutVerifying()
    ->get("https://api.w-api.app/v1/quere/quere?instanceId=" . self::$instanceID . "&perPage=200&page=1");
    $response->throw();
    return $response->json();
  }

  public static function delFila() {
    $response = Http::withHeaders([
      'Authorization' => 'Bearer ' . self::$token,
      'Content-Type' => 'application/json',
    ])
    ->withoutVerifying()
    ->delete("https://api.w-api.app/v1/quere/delete-quere?instanceId=" . self::$instanceID);
    $response->throw();
    return $response->json();
  }

}
