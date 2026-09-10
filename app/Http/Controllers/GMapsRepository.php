<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GMapsRepository {

  // ESAL - AIzaSyDjPQmROd0S2vBsQG3IijFWUfnNO2lUoPk
  private static $key = 'AIzaSyACECtKFiaHwhgDbovrcxV-dJOyqIBjX5s';

  public static function getCepV3($cep) {
    try {
      return self::getCepViaCep($cep);
    } catch (\Throwable $th) {
      try {
        return self::getCepAwesomeApi($cep);
      } catch (\Throwable $th) {
        try {
          return self::getCepBrasilApi($cep);
        } catch (\Throwable $th) {
          return self::getCepOpenCep($cep);
        }
      }
    }
  }

  public static function getCepBrasilApi($cep) {
    $response = Http::get("https://brasilapi.com.br/api/cep/v1/{$cep}");
    if ($response->failed()) {
      Log::info(__METHOD__ . ' - ' . $cep . ' - ' . json_encode($response->json()));
      throw new \Exception('CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.');
    }
    $data = $response->json();
    if (isset($data['erro'])) throw new \Exception('CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.');
    return [
      "cep" => $data['cep'],
      "logradouro" => $data['street'],
      "bairro" => $data['neighborhood'],
      "localidade" => $data['city'],
      "uf" => $data['state']
    ];
  }

  public static function getCepAwesomeApi($cep) {
    $response = Http::get("https://cep.awesomeapi.com.br/json/{$cep}?token=20f92724db811fb4fe19691e8f0cabf4764c8a4991881b710de34ed3fb6b7a33");
    if ($response->failed()) {
      Log::info(__METHOD__ . ' - ' . $cep . ' - ' . json_encode($response->json()));
      throw new \Exception('CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.');
    }
    $data = $response->json();
    if (isset($data['erro'])) throw new \Exception('CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.');
    return [
      "cep" => $data['cep'],
      "logradouro" => $data['address'],
      "bairro" => $data['district'],
      "localidade" => $data['city'],
      "uf" => $data['state']
    ];
  }

  public static function getCepOpenCep($cep) {
    $response = Http::withoutVerifying()->get("https://opencep.com/v1/{$cep}.json");
    if ($response->failed()) {
      Log::info(__METHOD__ . ' - ' . $cep . ' - ' . json_encode($response->json()));
      throw new \Exception('CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.');
    }
    $data = $response->json();
    if (isset($data['erro'])) throw new \Exception('CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.');
    return $data;
  }

  public static function getCepViaCep($cep) {
    $response = Http::get("https://viacep.com.br/ws/{$cep}/json/");
    if ($response->failed()) {
      Log::info(__METHOD__ . ' - ' . $cep . ' - ' . json_encode($response->json()));
      throw new \Exception('Erro ao consultar o CEP.');
    }
    $data = $response->json();
    if (isset($data['erro'])) throw new \Exception('CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.');
    return $data;
  }

  public static function geocode($logradouro, $bairro, $municipio, $uf) {
    $response = Http::get("https://maps.googleapis.com/maps/api/geocode/json?address={$logradouro},+{$bairro},+{$municipio},+{$uf},+Brasil&key=" . self::$key);
    $lat = data_get($response->json(), 'results.0.geometry.location.lat', null);
    $lng = data_get($response->json(), 'results.0.geometry.location.lng', null);
    if (!$lat || !$lng) {
      Log::error('Erro ao consultar o endereço no Google Maps');
      Log::error($response->body());
    }

    return ['lat' => $lat, 'lng' => $lng];
  }

  public static function distanceLinear(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $earthRadius = 6371;

    $lat1Rad = deg2rad($lat1);
    $lng1Rad = deg2rad($lng1);
    $lat2Rad = deg2rad($lat2);
    $lng2Rad = deg2rad($lng2);

    $latDiff = $lat2Rad - $lat1Rad;
    $lngDiff = $lng2Rad - $lng1Rad;

    $a = sin($latDiff / 2) ** 2 + cos($lat1Rad) * cos($lat2Rad) * sin($lngDiff / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadius * $c;
  }

  // GMapsRepository::distanceKm(-25.4077, -49.2533, -25.4284, -49.2733)
  public static function distanceKm($lat1, $lng1, $lat2, $lng2) {
    $origem = "{$lat1},{$lng1}";
    $destino = "{$lat2},{$lng2}";

    $response = Http::get("https://maps.googleapis.com/maps/api/directions/json", [
        'origin' => $origem,
        'destination' => $destino,
        'mode' => 'driving', // Pode ser walking, bicycling, transit...
        'language' => 'pt-BR',
        'key' => self::$key
    ]);

    $data = $response->json();

    // Pega a distância em metros
    $distanciaMetros = data_get($data, 'routes.0.legs.0.distance.value', null);

    if (!$distanciaMetros) {
        Log::error('Erro ao calcular a distância no Google Maps', [
            'origem' => $origem,
            'destino' => $destino,
            'resposta' => $data
        ]);
        return 0;
    }

    // Converte para KM com 2 casas decimais
    return round($distanciaMetros / 1000, 2);
  }

  // $destinos = [
  //   ['lat' => -23.564, 'lng' => -46.653],
  //   ['lat' => -23.550, 'lng' => -46.633],
  //   ['lat' => -23.580, 'lng' => -46.620],
  // ];
  // $resultado = GMapsRepository::distanceKmMatrix(-25.4077, -49.2533, $destinos);
  public static function distanceKmMatrix($origLat, $origLng, array $destinos) {
    // Origem no formato "lat,lng"
    $origem = "{$origLat},{$origLng}";

    // Converte array de destinos para "lat,lng|lat,lng|lat,lng"
    $destinosStr = collect($destinos)
        ->map(fn($coord) => "{$coord['lat']},{$coord['lng']}")
        ->implode('|');

    $response = Http::get("https://maps.googleapis.com/maps/api/distancematrix/json", [
        'origins' => $origem,
        'destinations' => $destinosStr,
        'mode' => 'driving',
        'language' => 'pt-BR',
        'key' => self::$key
    ]);

    $data = $response->json();

    // Processa o resultado em KM
    $resultados = [];
    foreach (data_get($data, 'rows.0.elements', []) as $i => $element) {
        $distanciaMetros = data_get($element, 'distance.value', null);
        $resultados[] = [
            'destino' => $destinos[$i],
            'distancia_km' => $distanciaMetros ? round($distanciaMetros / 1000, 2) : null
        ];
    }

    return $resultados;
}

  // public static function getDistance($latlng, $cep) {
  //   try {
  //     $viacepUrl = 'https://viacep.com.br/ws/' . $cep . '/json';
  //     $dados = (new Client())->request('GET', $viacepUrl);
  //     $dados = json_decode((string) $dados->getBody());
  //     $destino = urlencode('Brasil ' . $dados->cep . ' ' . $dados->uf . ' ' . $dados->localidade . ' ' . $dados->logradouro . ' Bairro ' . $dados->bairro);
  //     // Busca GoogleMaps
  //     $googleUrl = 'https://maps.googleapis.com/maps/api/distancematrix/json?language=pt-BR&key=AIzaSyCS6gImzZ_JQ3d0KLWX11zvja43GtscOV0&mode=driving&origins=' . $latlng . '&destinations=' . $destino;
  //     $response = (new Client())->request('GET', $googleUrl);
  //     $json = objectToArray(json_decode((string)$response->getBody()));
  //     $distance = Arr::get($json, 'rows.0.elements.0.distance.value');
  //     if (!$distance) return -1;
  //     return round($distance / 1000, 1);
  //   } catch (\Throwable $e) {
  //     return -1;
  //   }
  // }

}