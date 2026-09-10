<?php

namespace App\Repository;

use App\Http\Controllers\SimuladoController;

/**
 * Abstrai o envio de push notifications, centralizando as 4 combinações
 * possíveis de canal e plataforma:
 *
 *  - Dirigir   + Android -> FCM (FirebaseRepository)
 *  - Dirigir   + iOS     -> APNs (ApnRepository, bundle br.com.dirigiragora)
 *  - Simulado  + Android -> FCM (SimuladoController, service account do simulado)
 *  - Simulado  + iOS     -> APNs (ApnRepository, bundle br.com.simulado.cnh.brasil)
 */
class PushRepository
{
  const CANAL_DIRIGIR  = 'dirigir';
  const CANAL_SIMULADO = 'simulado';

  const PLATAFORMA_ANDROID = 'android';
  const PLATAFORMA_IOS     = 'ios';

  const BUNDLE_DIRIGIR  = 'br.com.dirigiragora';
  const BUNDLE_SIMULADO = 'br.com.simulado.cnh.brasil';

  /**
   * Envia push para todos os tokens, segmentando por plataforma internamente.
   *
   * Recebe a lista completa de tokens (android + ios) e, a partir da coluna
   * `platform` de cada registro, direciona para o backend correto de cada canal:
   *
   *  - Dirigir   + Android -> FCM (FirebaseRepository)
   *  - Dirigir   + iOS     -> APNs (ApnRepository, bundle br.com.dirigiragora)
   *  - Simulado  + Android -> FCM (SimuladoController, service account do simulado)
   *  - Simulado  + iOS     -> APNs (ApnRepository, bundle br.com.simulado.cnh.brasil)
   *
   * @param iterable $pushTokens Registros com as colunas `token` e `platform` (android|ios).
   * @param string   $titulo     Título da notificação.
   * @param string   $mensagem   Corpo da notificação.
   * @param array    $data       Dados extras (ex.: ['pagina' => ...] / ['url' => ...]).
   * @param string   $canal      Canal do app: CANAL_DIRIGIR ou CANAL_SIMULADO.
   */
  public static function sendNotificationTokens($pushTokens, string $titulo, string $mensagem, array $dataAndroid = [], array $dataIos = [], string $canal = self::CANAL_DIRIGIR)
  {
    $pushTokens = collect($pushTokens);

    $androidTokens = $pushTokens
      ->where('platform', self::PLATAFORMA_ANDROID)
      ->pluck('token')
      ->filter()
      ->values()
      ->toArray();

    $iosTokens = $pushTokens
      ->where('platform', self::PLATAFORMA_IOS)
      ->pluck('token')
      ->filter()
      ->values()
      ->toArray();

    // Android -> FCM (a service account muda conforme o canal)
    if (!empty($androidTokens)) {
      if ($canal === self::CANAL_SIMULADO) {
        SimuladoController::sendNotificationTokens($androidTokens, $titulo, $mensagem, $dataAndroid);
      } else {
        FirebaseRepository::sendNotificationTokens($androidTokens, $titulo, $mensagem, $dataAndroid);
      }
    }

    // iOS -> APNs (o bundleID muda conforme o canal)
    if (!empty($iosTokens)) {
      ApnRepository::$bundleID = $canal === self::CANAL_SIMULADO
        ? self::BUNDLE_SIMULADO
        : self::BUNDLE_DIRIGIR;

      ApnRepository::sendNotificationTokens($iosTokens, $titulo, $mensagem, $dataIos);
    }
  }
}
