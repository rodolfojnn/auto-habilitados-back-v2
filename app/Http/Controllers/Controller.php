<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Response;

class Controller extends BaseController
{
  // use AuthorizesRequests, ValidatesRequests;

  private $additionalFields = [];

  /**
   * @param mixed $data
   * @param bool $status
   * @return \Illuminate\Http\JsonResponse
   */
  public function response($data = null, $status = true)
  {
    // Resposta HTTP padrão
    if (is_string($data)) {
      $data = $this->unicodeString($data);
    }
    // Caso seja error, traduz caracteres unicode
    $error = data_get($data, 'error');
    if ($error) {
      data_set($data, 'error', $this->unicodeString($error));
    }

    if (is_numeric($status)) {
      return response()->json($data, $status);
    }
    if (is_bool($status)) {
      $ret = array_merge(['status' => $status, 'data' => $data], $this->additionalFields);
      return response()->json($ret);
    }
  }

  public function unicodeString($str, $encoding='UTF-8') {
    if (is_null($encoding)) $encoding = ini_get('mbstring.internal_encoding');
    return preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/u', function($match) use ($encoding) {
      return mb_convert_encoding(pack('H*', $match[1]), $encoding, 'UTF-16BE');
    }, $str);
  }

  public function checkBasicAuth() {
    if (!request()->hasHeader('Authorization')) return false;
    list($username, $password) = explode(':', base64_decode(substr(request()->header('Authorization'), 6)) ?: '', 2) ?? [];
    if ($username !== 'admin' || $password !== '2017-es@L#') return false;
    return true;
  }
}
