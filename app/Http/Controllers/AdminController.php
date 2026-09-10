<?php

namespace App\Http\Controllers;

use App\Libraries\Helpers;
use App\Models\Aluno;
use App\Models\Instrutor;
use App\Models\InstrutorReview;
use App\Repository\FirebaseRepository;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;

class AdminController extends Controller
{

  public function login(Request $req) {

    $email = strtolower(trim($req->email));
    if (!RateLimiter::attempt('IP_ADDRESS_' . $email, $perMinute = 5, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $admins = [
      (object) ['id' => 1, 'email' => 'rodolfojnnogueira@gmail.com', 'password' => 'fakezir1617', 'nome' => 'Rodolfo Nogueira'],
      (object) ['id' => 2, 'email' => 'comercial@dirigiragora.com.br', 'password' => 'Programadeira06@', 'nome' => 'Jenifer Raffo'],
    ];
    $admin = collect($admins)->where('email', $email)->where('password', $req->password)->first();
    if (!$admin) {
      Log::info(__METHOD__ . ' - Dados ADMIN não encontrados - ' . $email . ' - ' . $req->password);
      return $this->response('Cadastro não encontrado.', false);
    }

    // JWT
    $secret = config('app.JWT_SECRET_ADMIN');
    $jwt = JWT::encode(
      [
        'admin_id'    => $admin->id,
        'exp'         => Carbon::now()->addDays(5)->getTimestamp(),
        'iss'         => 'admin',                                // issuer
        'iat'         => Carbon::now()->getTimestamp(),          // issued at
      ],
      $secret,
      'HS256'
    );

    unset($admin->password);
    return $this->response(['jwt' => $jwt, 'admin' => $admin]);
  }

  // {"title":"asdfasdf","body":"asdfasdf","channel":"Dirigir Agora","pushPage":"iframe-container","ids":["asdfasdf","asdfasd","fasdsadf"]}
  public function envioPushPost(Request $req) {
    set_time_limit(0);

    $report = [];

    // Define o payload: se vier url, envia apenas url; senão, envia pagina
    $data = $req->url ? ['url' => $req->url] : ['pagina' => $req->pushPage];

    // Dirigir
    if ($req->channel == 'Dirigir Agora') {
      $report = FirebaseRepository::sendNotificationTokens(
        $req->ids,
        $req->title,
        $req->body,
        $data
      );
    } else

    if ($req->channel == 'Simulado CNH do Brasil') {
      $controller = app(\App\Http\Controllers\SimuladoController::class);
      $report = $controller->sendNotificationTokens(
        $req->ids,
        $req->title,
        $req->body,
        $data
      );
    }

    return $this->response($report);
  }

  // {"title":"T\u00eddulo do emamil","html":"<h1>Ol\u00e1<\/h1>","emails":["rodolfojnnogueira@gmail.com","rodolfojnn@gmail.com"]}
  // ['success' => $report->successes()->count(), 'failure' => $report->failures()->count()]
  public function envioEmailPost(Request $req) {

    $emails = $req->emails ?? [];
    $html = $req->html ?? '';
    $title = $req->title;
    $from = $req->from ?? 'Dirigir Agora';

    $success = 0;
    $failure = 0;

    foreach ($emails as $key => $email) {
      try {
        $email = strtolower(trim($email));
        Mail::mailer('zeptomail')->send([], [], function ($m) use ($email, $title, $html, $from) {
          $m->from('comercial@dirigiragora.com.br', $from);
          $m->to($email)->subject($title)->html($html);
        });

        Log::channel('email')->info('Email Send: ' . $email . ' - ' . $key);
        $success++;
      } catch (\Throwable $th) {
        Log::channel('email')->error($th->getMessage());
        $failure++;
      }
    }

    return $this->response(['success' => $success, 'failure' => $failure]);
  }

  public function alunoStatusUpdate(Request $req) {
    $aluno = Aluno::find($req->aluno_id);
    $aluno->statusD = $req->statusD;
    $aluno->save();
    return $this->response(true);
  }

  public function alunoDeletarConta(Request $req) {
    $aluno = Aluno::find($req->aluno_id);
    $aluno->status = 'X';
    $aluno->save();
    return $this->response(true);
  }

  public function alunoGetKanban(Request $req) {
    $statusDList = ['Biometria', 'Exame M', 'Exame P', 'Teórico', 'Aulas', 'Exame'];

    $query = Aluno::select('id', 'status', 'statusD', 'nome', 'created_at', 'fone1', 'cep', 'bairro', 'municipio', 'uf', 'solicitacao')
      ->whereIn('statusD', $statusDList);

    $alunos = $query->get();

    return $this->response($alunos);
  }

  // {"nome":"","fone1":"9021","municipio":"","uf":""}
  public function alunoBuscar(Request $req) {
    $query = Aluno::select('id', 'status', 'statusD', 'nome', 'created_at', 'fone1', 'email', 'cep', 'bairro', 'municipio', 'uf', 'solicitacao');

    $filtered = false;
    if (!empty($req->id)) { $query->where('id', '=', $req->id); $filtered = true; }
    if (!empty($req->nome)) { $query->where('nome', 'like', "%{$req->nome}%"); $filtered = true; }
    if (!empty($req->email)) { $query->where('email', 'like', "%{$req->email}%"); $filtered = true; }
    if (!empty($req->fone1)) { $query->where('fone1', 'like', "%{$req->fone1}%"); $filtered = true; }
    if (!empty($req->bairro)) { $query->where('bairro', 'like', "%{$req->bairro}%"); $filtered = true; }
    if (!empty($req->municipio)) { $query->where('municipio', 'like', "%{$req->municipio}%"); $filtered = true; }
    if (!empty($req->uf)) { $query->where('uf', 'like', "%{$req->uf}%"); $filtered = true; }

    // Se todos os filtros estiverem vazios, busca apenas os alunos criados no dia atual
    if (!$filtered) {
      $query->whereDate('created_at', Carbon::today())->orderBy('created_at', 'desc');
    }

    $alunos = $query->limit(150)->get();

    return $this->response($alunos);
  }

  // payload {"categoria":"carOwn","cep":"01001000"} ou {"categoria":"carOwn","municipio":"São Paulo","uf":"SP"}
  public function instrutorRadar(Request $req) {
    // Raio linear
    $raio = 18;
    if (!empty($req->cep)) {
      // Busca CEP e obtém coordenadas
      $cep      = Helpers::onlyN($req->cep);
      $cepData  = GMapsRepository::getCepV3($cep);
      $location = GMapsRepository::geocode($cepData['logradouro'], $cepData['bairro'], $cepData['localidade'], $cepData['uf']);
      $lat      = $location['lat'];
      $lng      = $location['lng'];

      if (!$lat || !$lng) {
        return $this->response('CEP não encontrado ou inválido.', false);
      }

      // Query com cálculo de distância linear (Haversine)
      $query = Instrutor::select(
          DB::raw("
            id, nome, fone1, cep, email, municipio, uf, bairro,
            vDesc5, vDesc10, vDesc15, vDesc20,
            carOwn, vCarOwn, vCarOwnKm, vCarRent,
            carAluno, vCarAluno, vCarAlunoKm,
            bikeOwn, vBikeOwn, vBikeOwnKm, vBikeRent,
            bikeAluno, vBikeAluno, vBikeAlunoKm,
            lat, lng, vTaxaApp,
            vCI2, vCI4, vCI6, vCI8, vCI10, vCA2, vCA4, vCA6, vCA8, vCA10, vMI2, vMI4, vMI6, vMI8, vMI10, vMA2, vMA4, vMA6, vMA8, vMA10,
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
        ->whereIn('status', ['A', 'AA'])
        ->where('ativo', '>=', 1);

      // Raio de 25km igual ao InstrutorController::proximos
      $query->having('km_linear', '<', $raio)
            ->orderBy('km_linear');

      $instrutores = $query->get();

      // Calcula km_real = km_linear * 1.4 (fator de correção para distância real de direção)
      foreach ($instrutores as $instrutor) {
        $instrutor->km_real = Helpers::N2($instrutor->km_linear * 1.4);
        unset($instrutor->km_linear);
        unset($instrutor->lat);
        unset($instrutor->lng);
      }

      return $this->response($instrutores);
    }

    // Modo sem CEP: filtra por município/UF
    $query = Instrutor::select(
        'id', 'nome', 'fone1', 'cep', 'email', 'municipio', 'uf', 'bairro',
        'vDesc5', 'vDesc10', 'vDesc15', 'vDesc20',
        'carOwn', 'vCarOwn', 'vCarOwnKm', 'vCarRent',
        'carAluno', 'vCarAluno', 'vCarAlunoKm',
        'bikeOwn', 'vBikeOwn', 'vBikeOwnKm', 'vBikeRent',
        'bikeAluno', 'vBikeAluno', 'vBikeAlunoKm', 'vTaxaApp',
        'vCI2', 'vCI4', 'vCI6', 'vCI8', 'vCI10', 'vCA2', 'vCA4', 'vCA6', 'vCA8', 'vCA10', 'vMI2',
        'vMI4', 'vMI6', 'vMI8', 'vMI10', 'vMA2', 'vMA4', 'vMA6', 'vMA8', 'vMA10'
      )
      ->whereIn('status', ['A', 'AA'])
      ->where('ativo', '>=', 1);

    if (!empty($req->municipio)) $query->where('municipio', 'like', "%{$req->municipio}%");
    if (!empty($req->uf)) $query->where('uf', $req->uf);

    $instrutores = $query->get();

    // Sem CEP de referência, km_real é 0
    foreach ($instrutores as $instrutor) {
      $instrutor->km_real = 0;
    }

    return $this->response($instrutores);
  }

  public function configLimparCache(Request $req) {
    try {
      \Illuminate\Support\Facades\Artisan::call('cache:clear');

      return $this->response(true);
    } catch (\Throwable $th) {
      Log::error('Erro ao limpar cache: ' . $th->getMessage());
      return $this->response('Erro ao limpar cache: ' . $th->getMessage(), false);
    }
  }

  public function instrutorCnhBuscar(Request $req) {
    $listaRaw = json_decode(file_get_contents(storage_path('data/cnhBrasilNova.json')), true);

    $pesquisa = strtoupper($req->nome);

    $find = collect($listaRaw)->filter(function ($item) use ($pesquisa) {
      $nome                   = strtoupper($item['nome'] ?? '');
      $telefone1              = strtoupper($item['telefone1'] ?? '');
      $telefone2              = strtoupper($item['telefone2'] ?? '');
      $enderecoMunicipioNome  = strtoupper($item['enderecoMunicipioNome'] ?? '');
      $enderecoBairro         = strtoupper($item['enderecoBairro'] ?? '');
      $email                  = strtoupper($item['email'] ?? '');

      return str_contains($nome, $pesquisa)
          || str_contains($telefone1, $pesquisa)
          || str_contains($telefone2, $pesquisa)
          || str_contains($enderecoMunicipioNome, $pesquisa)
          || str_contains($enderecoBairro, $pesquisa)
          || str_contains($email, $pesquisa);
    })->take(500)
    ->sortBy('nome')
    ->values();

    return $this->response($find);
  }

    public function instrutorBuscar(Request $req) {
      $query = Instrutor::select('id', 'status', 'ativo', 'selo', 'edit', 'destaque', 'nome', 'updated_at', 'fone1', 'email', 'cep', 'bairro', 'municipio', 'uf');

      if (!empty($req->nome)) $query->where('nome', 'like', "%{$req->nome}%");
      if (!empty($req->fone1)) $query->where('fone1', 'like', "%{$req->fone1}%");
      if (!empty($req->bairro)) $query->where('bairro', 'like', "%{$req->bairro}%");
      if (!empty($req->municipio)) $query->where('municipio', 'like', "%{$req->municipio}%");
      if (!empty($req->uf)) $query->where('uf', 'like', "%{$req->uf}%");
      if (!empty($req->email)) $query->where('email', 'like', "%{$req->email}%");
      if (!empty($req->status)) $query->where('status', '=', $req->status);
      if (!empty($req->ativo)) $query->where('ativo', '=', $req->ativo);
      if (!empty($req->selo)) $query->where('selo', '=', $req->selo);
      if (!empty($req->edit)) $query->where('edit', '=', $req->edit);
      if (!empty($req->destaque)) $query->where('destaque', '=', $req->destaque);
      if (!empty($req->id)) $query->where('id', '=', $req->id);

      // Filtro por push notification na tabela pushToken
      if (isset($req->temPush)) {
        if ($req->temPush == 1) {
          // Somente instrutores que possuem pelo menos um registro na tabela pushToken
          $query->whereExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('pushToken')
              ->where('owner_type', '=', 'instrutor')
              ->whereColumn('pushToken.owner_id', 'instrutor.id');
          });
        } elseif ($req->temPush == 0) {
          // Somente instrutores que NÃO possuem registro na tabela pushToken
          $query->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
              ->from('pushToken')
              ->where('owner_type', '=', 'instrutor')
              ->whereColumn('pushToken.owner_id', 'instrutor.id');
          });
        }
      }

      $alunos = $query
        ->orderBy('updated_at', 'desc')
        ->limit(300)
        ->get();

      return $this->response($alunos);
    }

    public function instrutorUpdate(Request $req) {
      $instrutor = Instrutor::find($req->id);
      $instrutor->status      = $req->status;
      $instrutor->ativo       = $req->ativo;
      $instrutor->selo        = $req->selo;
      $instrutor->edit        = $req->edit;
      $instrutor->destaque    = $req->destaque;
      $instrutor->save();
      return $this->response(true);
    }

    // {"instrutor_id":1103,"nome":"nome","title":"asldkfjlsd","body":"9iasjdflaskdjflasd"}
    public function instrutorAvaliacao(Request $req) {
      $instrutor = Instrutor::find($req->instrutor_id);

      // ----- instrutor
      $instrutor->notaQtd = $instrutor->notaQtd + 1;
      $instrutor->nota    = 5;
      $instrutor->save();

      // ----- instrutorReview
      $instrutorReview = new instrutorReview();
      $instrutorReview->aluno_id        = 1;
      $instrutorReview->instrutor_id    = $req->instrutor_id;
      $instrutorReview->nota            = 5;
      $instrutorReview->nome            = $req->nome;
      $instrutorReview->title           = $req->title;
      $instrutorReview->body            = $req->body;
      $instrutorReview->save();

      return $this->response(true);
    }


}