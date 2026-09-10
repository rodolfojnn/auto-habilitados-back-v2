<?php

namespace App\Repository;

use App\Models\Aluno;
use App\Models\Emp;
use App\Models\Instrutor;
use App\Models\InstrutorBlackList;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CNHBrasilRepository
{

  // http://localhost/jenifer/auto-habilitados-back/public/cron-alunos-email-cnh-brasil
  public static function cronNovosAlunosProcessEmail() {

    // alunos com created_at na última meia hora
    $alunos = Aluno::where('created_at', '>=', now()->subMinutes(60))->get();
    foreach ($alunos as $aluno) {
      $uf           = $aluno->uf;
      $municipio    = $aluno->municipio;

      // Blacklists
      $blackListFile = collect(json_decode(file_get_contents(storage_path('data/emailBlackList.json')), true));
      $blacklistInstrutores = Instrutor::where('uf', $uf)
        ->whereIn('status', ['A', 'AA'])
        // ->where('municipio', $municipio)
        ->pluck('email');
      $blacklistGeral = InstrutorBlackList::where('uf', $uf)
        ->where('municipio', $municipio)
        ->pluck('email');
      $blacklistFinal = $blacklistInstrutores
        ->concat($blacklistGeral)
        ->concat($blackListFile)
        ->unique()
        ->map(function ($email) {
            return Str::upper(Str::ascii($email));
        })
        ->toArray();

      $lista      = collect(json_decode(file_get_contents(storage_path('data/cnhBrasilNova.json')), true));
      $lista      = $lista
        ->where('enderecoUf', '=', Str::upper(Str::ascii($uf)))
        ->where('enderecoMunicipioNome', '=', Str::upper(Str::ascii($municipio)))
        ->whereNotIn('email', $blacklistFinal)
        ->whereNotNull('email')
        ->unique('email')
        ->filter(function ($item) {
          // Extrai o e-mail do item da lista
          $email = $item['email'] ?? null;

          if (empty($email)) return false;

          // Força minúsculo para os testes de texto ficarem mais seguros (case-insensitive)
          $emailMinusculo = strtolower(trim($email));

          if (!filter_var($emailMinusculo, FILTER_VALIDATE_EMAIL)) return false;

          // if (str_ends_with($emailMinusculo, '@hotmail.com')) return false;
          if (str_contains($emailMinusculo, 'autoescola') || str_contains($emailMinusculo, 'cfc')) return false;
          if (str_contains($emailMinusculo, 'aaaaa')) return false;
          if (str_contains($emailMinusculo, 'xxxxx')) return false;
          if (str_contains($emailMinusculo, '00000')) return false;

          // Validação do tamanho do usuário (antes do @)
          $usuario = explode('@', $emailMinusculo)[0];
          if (strlen($usuario) < 5) return false;

          return true;
        })
        ->slice(0, 300)
        ->toArray();

      // dd($lista);

      // Envia o email
      foreach ($lista as $key => $item) {

        $instrutorBlackList             = new InstrutorBlackList();
        $instrutorBlackList->email      = strtolower($item['email']);
        $instrutorBlackList->uf         = $uf;
        $instrutorBlackList->municipio  = $municipio;
        $instrutorBlackList->save();

        try {
          $enviado = Mail::mailer('zeptomail')->send('mail.instrutor-uf-municipio-v4', ['municipio' => $municipio, 'uf' => $uf],
            function ($m) use ($item) {
              $m->to(strtolower($item['email']))->subject('Você dá aula prática de primeira habilitação?');
            }
          );
          if ($enviado) Log::channel('email')->info('Email Send: instrutor-uf-municipio-v4 - ' . $item['email'] . ' - ' . $key);
        } catch (\Throwable $th) {
          Log::channel('email')->error($th->getMessage());
        }

      }
    }

  }

  public static function mergeData() {
    $main = file_get_contents(storage_path('data/instrutores_20251217.json'));
    $main = collect(json_decode($main, true))->unique();
    $main = $main->filter(function ($item) {
      return isset($item['uf'])
        && is_string($item['uf'])
        && $item['uf'] !== ''
        && !is_numeric($item['uf']);
    });

    // CPF
    $cpf = file_get_contents('C:\Users\rodol\Desktop\instrutores-CPF.json');
    $cpf = collect(json_decode($cpf, true))->unique();
    $cpfIndexado = $cpf->keyBy(function ($item) {
      return mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
    });
    $main = $main->map(function ($item) use ($cpfIndexado) {
      $key = mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
      $item['cpf'] = $cpfIndexado[$key]['cpf'] ?? null;
      return $item;
    });

    // Email
    $email = file_get_contents('C:\Users\rodol\Desktop\instrutores-EMAIL.json');
    $email = collect(json_decode($email, true))->unique();
    $emailIndexado = $email->keyBy(function ($item) {
      return mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
    });
    $main = $main->map(function ($item) use ($emailIndexado) {
      $key = mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
      $item['email'] = $emailIndexado[$key]['cpf'] ?? null;
      return $item;
    });

    // Fone1
    $fone1 = file_get_contents('C:\Users\rodol\Desktop\instrutores-TELEFONE1.json');
    $fone1 = collect(json_decode($fone1, true))->unique();
    $fone1Indexado = $fone1->keyBy(function ($item) {
      return mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
    });
    $main = $main->map(function ($item) use ($fone1Indexado) {
      $key = mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
      $item['fone1'] = $fone1Indexado[$key]['cpf'] ?? null;
      return $item;
    });

    // Fone2
    $fone2 = file_get_contents('C:\Users\rodol\Desktop\instrutores-TELEFONE2.json');
    $fone2 = collect(json_decode($fone2, true))->unique();
    $fone2Indexado = $fone2->keyBy(function ($item) {
      return mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
    });
    $main = $main->map(function ($item) use ($fone2Indexado) {
      $key = mb_strtolower(
        trim($item['nome']) . '|' .
        trim($item['uf']) . '|' .
        trim($item['municipio'])
      );
      $item['fone2'] = $fone2Indexado[$key]['cpf'] ?? null;
      if ($item['fone2'] === '0') $item['fone2'] = null;
      return $item;
    });

    file_put_contents('C:\Users\rodol\Desktop\instrutores-TOTAL.json', json_encode($main->toArray()));

  }

  public static function findInstrutor($nome, $uf)
  {
    try {
      // Tratamento nome
      $nome = Str::ascii($nome);
      $nome = trim(strtoupper($nome));
      $file   = file_get_contents(storage_path('data/instrutores_20251217.json'));
      $data   = json_decode($file, true);
      $collection = collect($data);

      $filter = $collection
        ->where('uf', $uf)
        ->filter(function ($item) use ($nome) {
          return Str::contains($item['nome'], $nome);
          // return Str::startsWith($item['nome'], 'RONY ');
          // return Str::startsWith($item['nome'], strtoupper('Fernanda Prodeliki'));
        });

      return $filter->count() > 0;
    } catch (\Throwable $th) {
      return false;
    }
  }

  public static function procRemoteData()
  {
    $token  = null;
    $estado = [];
    $todos  = [];

    do {
      $resp = self::getData($token, $estado);

      // Acumula os registros
      $todos = array_merge($todos, $resp['dados']);

      // Atualiza estado herdado (compactação)
      $estado = $resp['estado'];

      // Quantidade retornada nessa página
      $count = count($resp['dados']);

      $last = end($resp['dados']);
      if (!$last) break;

      // Próximo token (pode ser null na primeira)
      $token = [[
        "'{$last['nome']}'",
        "'{$last['municipio']}'",
        "'{$last['bairro']}'",
        "'{$last['uf']}'",
        "'{$last['cpf']}'",
        // "'{$last['email']}'",
        // "'{$last['telefone1']}'",
        // "'{$last['telefone2']}'",
      ]];

      Log::info(json_encode($token));

      //   dd($resp);
      // break;

    } while ($count === 20000);

    // dd(collect($todos)->reverse()->toArray());
    file_put_contents('C:\Users\rodol\Desktop\instrutores-EMAIL.json', json_encode($todos));
  }


  public static function getData($restartTokens = null, array &$estadoPersistente = [])
  {
    // 1️⃣ Normaliza RestartTokens
    if (empty($restartTokens)) {
      $restartTokens = null;
    }

    // 2️⃣ Estado persistente (para herança entre páginas)
    if (empty($estadoPersistente)) {
      $estadoPersistente = [
        'municipio'     => null,
        'nome'          => null,
        'bairro'        => null,
        'uf'            => null,
        'cpf'           => null,
        // 'email'         => null,
        // 'telefone1'     => null,
        // 'telefone2'     => null,
        // 'numero'        => null,
        // 'logradouro'    => null,
        // 'cep'           => null,
      ];
    }

    $response = Http::withHeaders([
      'accept' => 'application/json, text/plain, */*',
      'content-type' => 'application/json;charset=UTF-8',
      'x-powerbi-resourcekey' => 'a99cc6a6-d1a2-422f-981f-dc4ec861c584',
      'Referer' => 'https://app.powerbi.com/',
    ])->post(
      'https://wabi-brazil-south-api.analysis.windows.net/public/reports/querydata?synchronous=true',
      [
        'version' => '1.0.0',
        'queries' => [[
          'Query' => [
            'Commands' => [[
              'SemanticQueryDataShapeCommand' => [
                'Query' => [
                  'Version' => 2,
                  'From' => [
                    ['Name' => 't', 'Entity' => 'TOM_Serpro', 'Type' => 0],
                    ['Name' => 'i', 'Entity' => 'inf_contato_profissionais_Renach', 'Type' => 0],
                  ],
                  'Select' => [
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 't']], 'Property' => 'Município']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'nome']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_bairro']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'uf_detran_cadastramento']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'cpf']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'e_mail']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'telefone_1']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'telefone_2']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_numero']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_logradouro']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_cep']]
                  ],
                  'OrderBy' => [[
                    'Direction' => 1,
                    'Expression' => ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'nome']]
                  ]]
                ],
                'Binding' => [
                  'Primary' => [
                    'Groupings' => [[
                      'Projections' => [0, 1, 2, 3, 4],
                      'Subtotal' => 1,
                    ]]
                  ],
                  'DataReduction' => [
                    'DataVolume' => 3,
                    'Primary' => [
                      'Window' => [
                        'Count' => 20000,
                        'RestartTokens' => $restartTokens
                      ]
                    ]
                  ],
                  'Version' => 1
                ],
              ]
            ]]
          ],
          'QueryId' => ''
        ]],
        'modelId' => 10670397,
      ]
    );

    $json = $response->json();

    // if ($restartTokens) {
    //     dd($json);
    // }

    // 3️⃣ Caminhos corretos
    $dsr  = data_get($json, 'results.0.result.data.dsr', []);
    $rows = data_get($dsr, 'DS.0.PH.0.DM0', []);

    // 4️⃣ ValueDicts pode mudar de lugar
    $dicts =
      data_get($dsr, 'ValueDicts')
      ?? data_get($dsr, 'DS.0.ValueDicts')
      ?? [];

    // 5️⃣ Dicionários conforme SELECT
    $D0 = $dicts['D0'] ?? []; // Município
    $D1 = $dicts['D1'] ?? []; // Nome
    $D2 = $dicts['D2'] ?? []; // Bairro
    $D3 = $dicts['D3'] ?? []; // UF
    $D4 = $dicts['D4'] ?? []; // CPF
    // $D5 = $dicts['D5'] ?? []; // Telefone2
    // $D6 = $dicts['D6'] ?? []; // Telefone 1
    // $D7 = $dicts['D7'] ?? []; // Telefone 2
    // $D6 = $dicts['D6'] ?? []; // Logradouro
    // $D7 = $dicts['D7'] ?? []; // CEP

    $dados = [];

    foreach ($rows as $row) {
      if (!isset($row['C'])) continue;

      $c = $row['C'];

      // MUNICÍPIO
      if (array_key_exists(0, $c) && $c[0] !== null) {
        $estadoPersistente['municipio'] = self::resolveValue($c[0], $D0);
      }

      // NOME
      if (array_key_exists(1, $c) && $c[1] !== null) {
        $estadoPersistente['nome'] = self::resolveValue($c[1], $D1);
      }

      // BAIRRO
      if (array_key_exists(2, $c) && $c[2] !== null) {
        $estadoPersistente['bairro'] = self::resolveValue($c[2], $D2);
      }

      // UF
      if (array_key_exists(3, $c) && $c[3] !== null) {
        $estadoPersistente['uf'] = self::resolveValue($c[3], $D3);
      }

      // CPF
      if (array_key_exists(4, $c) && $c[4] !== null) {
        $estadoPersistente['cpf'] = self::resolveValue($c[4], $D4);
      }

      // // Email
      // if (array_key_exists(5, $c) && $c[5] !== null) {
      //     $estadoPersistente['e_mail'] = self::resolveValue($c[5], $D5);
      // }

      // // Telefone 1
      // if (array_key_exists(6, $c) && $c[6] !== null) {
      //     $estadoPersistente['telefone1'] = self::resolveValue($c[6], $D6);
      // }

      // // Telefone 2
      // if (array_key_exists(7, $c) && $c[7] !== null) {
      //     $estadoPersistente['telefone2'] = self::resolveValue($c[7], $D7);
      // }

      // // endereco_numero
      // if (array_key_exists(5, $c) && $c[5] !== null) {
      //     $estadoPersistente['numero'] = self::resolveValue($c[5], $D5);
      // }

      // // Logradouro
      // if (array_key_exists(6, $c) && $c[6] !== null) {
      //     $estadoPersistente['logradouro'] = self::resolveValue($c[6], $D6);
      // }

      // // CEP
      // if (array_key_exists(7, $c) && $c[7] !== null) {
      //     $estadoPersistente['cep'] = self::resolveValue($c[7], $D7);
      // }

      $dados[] = $estadoPersistente;
    }

    return [
      'dados' => $dados,
      'restartTokens' => data_get($dsr, 'RestartTokens'),
      'estado' => $estadoPersistente
    ];
  }

  public static function getDataV2($field)
  {
    Log::info($field);

    // 1️⃣ Normaliza RestartTokens
    if (empty($restartTokens)) {
      $restartTokens = null;
    }

    // 2️⃣ Estado persistente (para herança entre páginas)
    if (empty($estadoPersistente)) {
      $estadoPersistente = [
        'municipio'     => null,
        'nome'          => null,
        'bairro'        => null,
        'uf'            => null,
        'cpf'           => null,
        // 'numero'        => null,
        // 'logradouro'    => null,
        // 'cep'           => null,
      ];
    }

    $response = Http::withHeaders([
      'accept' => 'application/json, text/plain, */*',
      'content-type' => 'application/json;charset=UTF-8',
      'x-powerbi-resourcekey' => 'a99cc6a6-d1a2-422f-981f-dc4ec861c584',
      'Referer' => 'https://app.powerbi.com/',
    ])->post(
      'https://wabi-brazil-south-api.analysis.windows.net/public/reports/querydata?synchronous=true',
      [
        'version' => '1.0.0',
        'queries' => [[
          'Query' => [
            'Commands' => [[
              'SemanticQueryDataShapeCommand' => [
                'Query' => [
                  'Version' => 2,
                  'From' => [
                    ['Name' => 't', 'Entity' => 'TOM_Serpro', 'Type' => 0],
                    ['Name' => 'i', 'Entity' => 'inf_contato_profissionais_Renach', 'Type' => 0],
                  ],
                  'Select' => [
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 't']], 'Property' => 'Município']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'nome']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_bairro']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'uf_detran_cadastramento']],
                    ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => $field]],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_numero']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_logradouro']],
                    // ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'endereco_cep']]
                  ],
                  'OrderBy' => [[
                    'Direction' => 1,
                    'Expression' => ['Column' => ['Expression' => ['SourceRef' => ['Source' => 'i']], 'Property' => 'nome']]
                  ]]
                ],
                'Binding' => [
                  'Primary' => [
                    'Groupings' => [[
                      'Projections' => [0, 1, 2, 3, 4],
                      'Subtotal' => 1,
                    ]]
                  ],
                  'DataReduction' => [
                    'DataVolume' => 3,
                    'Primary' => [
                      'Window' => [
                        'Count' => 5,
                        'RestartTokens' => $restartTokens
                      ]
                    ]
                  ],
                  'Version' => 1
                ],
              ]
            ]]
          ],
          'QueryId' => ''
        ]],
        'modelId' => 10670397,
      ]
    );

    $json = $response->json();

    // if ($restartTokens) {
    //     dd($json);
    // }

    // 3️⃣ Caminhos corretos
    $dsr  = data_get($json, 'results.0.result.data.dsr', []);
    $rows = data_get($dsr, 'DS.0.PH.0.DM0', []);

    // 4️⃣ ValueDicts pode mudar de lugar
    $dicts =
      data_get($dsr, 'ValueDicts')
      ?? data_get($dsr, 'DS.0.ValueDicts')
      ?? [];

    // 5️⃣ Dicionários conforme SELECT
    $D0 = $dicts['D0'] ?? []; // Município
    $D1 = $dicts['D1'] ?? []; // Nome
    $D2 = $dicts['D2'] ?? []; // Bairro
    $D3 = $dicts['D3'] ?? []; // UF
    $D4 = $dicts['D4'] ?? []; // CPF
    // $D4 = $dicts['D5'] ?? []; // Endereço número
    // $D6 = $dicts['D6'] ?? []; // Logradouro
    // $D7 = $dicts['D7'] ?? []; // CEP

    $dados = [];

    foreach ($rows as $row) {
      if (!isset($row['C'])) continue;

      $c = $row['C'];

      // MUNICÍPIO
      if (array_key_exists(0, $c) && $c[0] !== null) {
        $estadoPersistente['municipio'] = self::resolveValue($c[0], $D0);
      }

      // NOME
      if (array_key_exists(1, $c) && $c[1] !== null) {
        $estadoPersistente['nome'] = self::resolveValue($c[1], $D1);
      }

      // BAIRRO
      if (array_key_exists(2, $c) && $c[2] !== null) {
        $estadoPersistente['bairro'] = self::resolveValue($c[2], $D2);
      }

      // UF
      if (array_key_exists(3, $c) && $c[3] !== null) {
        $estadoPersistente['uf'] = self::resolveValue($c[3], $D3);
      }

      // CPF
      if (array_key_exists(4, $c) && $c[4] !== null) {
        $estadoPersistente['cpf'] = self::resolveValue($c[4], $D4);
      }

      // // endereco_numero
      // if (array_key_exists(5, $c) && $c[5] !== null) {
      //     $estadoPersistente['numero'] = self::resolveValue($c[5], $D5);
      // }

      // // Logradouro
      // if (array_key_exists(6, $c) && $c[6] !== null) {
      //     $estadoPersistente['logradouro'] = self::resolveValue($c[6], $D6);
      // }

      // // CEP
      // if (array_key_exists(7, $c) && $c[7] !== null) {
      //     $estadoPersistente['cep'] = self::resolveValue($c[7], $D7);
      // }

      $dados[] = $estadoPersistente;
    }

    return [
      'dados' => $dados,
      'restartTokens' => data_get($dsr, 'RestartTokens'),
      'estado' => $estadoPersistente
    ];
  }

  private static function resolveValue($value, array $dict)
  {
    if (is_int($value)) {
      return $dict[$value] ?? null;
    }

    if (is_string($value)) {
      return $value;
    }

    return null;
  }
}
