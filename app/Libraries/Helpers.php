<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Helpers {

  public static function csvToJson($file, $delimiter = ',') {
    $csv = explode("\n", $file);
    $headers = str_getcsv($csv[0], $delimiter);
    array_shift($csv);
    $result = [];
    foreach ($csv as $row) {
      $combined = collect($headers)->combine(str_getcsv($row, $delimiter));
      $result[] = $combined;
    }
    return json_decode(json_encode($result));
  }

  public static function csvToJsonV2($file, $delimiter = ',') {
    $lines = array_filter(explode("\n", trim($file)));
    $headers = str_getcsv(array_shift($lines), $delimiter);

    $result = [];

    foreach ($lines as $line) {
        $values = str_getcsv($line, $delimiter);

        // Ajusta o tamanho do array
        $values = array_pad($values, count($headers), null);
        $values = array_slice($values, 0, count($headers));

        $result[] = array_combine($headers, $values);
    }

    return json_decode(json_encode($result));
  }

  public static function csvToJsonV3($file, $delimiter = ';')
{
    $lines = array_filter(explode("\n", trim($file)));

    // Cabeçalho
    $rawHeaders = str_getcsv(array_shift($lines), $delimiter);

    // Corrige headers duplicados
    $headers = [];
    $count = [];

    foreach ($rawHeaders as $header) {
        if (!isset($count[$header])) {
            $count[$header] = 1;
            $headers[] = $header;
        } else {
            $count[$header]++;
            $headers[] = $header . '_' . $count[$header];
        }
    }

    $result = [];

    foreach ($lines as $line) {
        $values = str_getcsv($line, $delimiter);

        // Ajusta tamanho
        $values = array_pad($values, count($headers), null);
        $values = array_slice($values, 0, count($headers));

        $row = [];
        foreach ($headers as $i => $header) {
            $row[$header] = $values[$i] ?? null;
        }

        $result[] = $row;
    }

    return json_decode(json_encode($result));
  }

  // "  10,028" = 10.03
  // "10,028"   = 10.03
  // 10.028     = 10.03
  // ""         = 0.0
  public static function N2($value, $dec = 2) {
    $valor = str_replace(',', '.', trim($value));
    return round(floatval($valor), $dec);
  }

  public static function dateTz($timestamp) {
    return \Carbon\Carbon::createFromTimestamp($timestamp / 1000, 'America/Bahia');
  }

    public static function onlyN($input) {
      return preg_replace('/[^0-9]/', '', $input);
  }

  public static function validaCPF($cpf) {
    // Extrai somente os números
    $cpf = preg_replace('/[^0-9]/is', '', $cpf);
    // Verifica se foi informado todos os digitos corretamente
    if (strlen($cpf) != 11) {
      return false;
    }
    // Verifica se foi informada uma sequência de digitos repetidos. Ex: 111.111.111-11
    if (preg_match('/(\d)\1{10}/', $cpf)) {
      return false;
    }
    // Faz o calculo para validar o CPF
    for ($t = 9; $t < 11; $t++) {
      for ($d = 0, $c = 0; $c < $t; $c++) {
        $d += $cpf[$c] * (($t + 1) - $c);
      }
      $d = ((10 * $d) % 11) % 10;
      if ($cpf[$c] != $d) {
        return false;
      }
    }
    return true;
  }

  public static function lastQuery() {
    \Illuminate\Support\Facades\DB::enableQueryLog();
      return \Illuminate\Support\Facades\DB::getQueryLog();
  }

  // https://github.com/rodolfojnn/blc-frontend/issues/118
  public static function sign($data) {
    // Carregar a chave privada
    $privateKeyPem = file_get_contents(base_path() . '/utils/keys/' . 'private_key_ECDSA.pem');
    $privateKey = openssl_pkey_get_private($privateKeyPem, '2017-es@L#');

    // Assinar dados
    $signature = '';
    if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) die('Erro ao assinar dados.');

    return base64_encode($signature);
  }

  // https://github.com/rodolfojnn/blc-frontend/issues/118
  public static function verifySign($data, $signature) {
    // Carregar a chave pública
    $publicKeyPem = file_get_contents(base_path() . '/utils/keys/' . 'public_key_ECDSA.pem');
    $publicKey = openssl_pkey_get_public($publicKeyPem);
    // Verificar assinatura
    $verified = openssl_verify($data, base64_decode($signature), $publicKey, OPENSSL_ALGO_SHA256);
    if ($verified === 1) return true;
    return false;
  }

  /**
   * Extrai o nome do campo que causou a duplicidade de uma mensagem de erro SQL.
   *
   * @param string $errorMessage A mensagem de erro completa da QueryException.
   * @return string O nome do campo ou uma string genérica se não for encontrado.
   */
  public static function getDuplicateFieldName(string $errorMessage): string
  {
    $fieldName = '';

    if (Str::contains($errorMessage, "for key '")) {
      preg_match("/for key '([^']+)'/", $errorMessage, $matches);
      if (isset($matches[1])) {
        $keyName = $matches[1];

        // Tenta limpar o nome da chave para obter o nome do campo
        if (Str::contains($keyName, '.')) {
          $parts = explode('.', $keyName);
          $fieldName = end($parts);
        } else {
          $fieldName = $keyName;
        }

        // Remove sufixos comuns de chaves únicas, como '_unique' ou '_index'
        $fieldName = Str::replaceLast('_unique', '', $fieldName);
        $fieldName = Str::replaceLast('_index', '', $fieldName);
      }
    }

    return strtoupper($fieldName);
  }

  public static function folderMaker($folder) {
    if (!is_dir($folder)) { mkdir($folder, 0777, true); }
    return $folder;
  }

  public static function FileB64xBin($base) {
    $data = base64_decode(substr($base, strpos($base, 'base64,') + 7));
    return $data;
  }

  public static function tratamentoImg($path, $filename) {
    try {
      $info = getimagesize($path . $filename);
      if ($info === false) return;
      $mime = $info['mime'];
      if ($mime === 'image/webp') return;

      // Mime
      switch ($mime) {
        case 'image/jpeg':
          $image = imagecreatefromjpeg($path . $filename);
          break;
        case 'image/png':
          $image = imagecreatefrompng($path . $filename);
          break;
        default:
          return;
      }

      // Redimensionar caso precise
      $maxSize = 1000;
      $width  = imagesx($image);
      $height = imagesy($image);
      $scale = min($maxSize / $width, $maxSize / $height, 1);
      $newWidth  = (int)($width * $scale);
      $newHeight = (int)($height * $scale);
      $resized = imagecreatetruecolor($newWidth, $newHeight);
      imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
      imagewebp($resized, $path . $filename, 90);
      imagedestroy($image);
      imagedestroy($resized);
    } catch (\Throwable $th) {
      Log::error(__METHOD__ . ' - ' . $th->getMessage());
    }
  }

}