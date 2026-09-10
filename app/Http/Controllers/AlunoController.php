<?php

namespace App\Http\Controllers;

use App\Libraries\Helpers;
use App\Models\Aluno;
use App\Models\ChatList;
use App\Models\ChatMsg;
use App\Models\Promocao;
use App\Repository\MailRepository;
use App\Repository\NotificationRepository;
use App\Repository\TelegramRepository;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;

class AlunoController extends Controller
{

  public function register(Request $req) {

    if (!RateLimiter::attempt('IP_ADDRESS_' . strtolower($req->email), $perMinute = 6, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $find = Aluno::where('email', '=', strtolower($req->email))->first();
    if ($find) return $this->response('Seu cadastro já foi iniciado, clique abaixo em já tenho um cadastro.', false);

    Log::info(__METHOD__ . ' - ' . json_encode($req->all()));

    // Valida se o CEP existe
    $cep      = Helpers::onlyN($req->cep);
    $cepData  = GMapsRepository::getCepV3($cep);
    $location = GMapsRepository::geocode($cepData['logradouro'], $cepData['bairro'], $cepData['localidade'], $cepData['uf']);
    if (!$location['lat'] || !$location['lng']) throw new \Exception('Erro ao encontrar as coordenadas do endereço. Verifique o CEP e tente novamente.');

    $aluno               = new Aluno();
    $aluno->status       = 'A';
    $aluno->statusD      = 'Lead';
    $aluno->ativo        = true;
    $aluno->nome         = $req->nome;
    $aluno->email        = strtolower($req->email);
    $aluno->fone1        = $req->fone1;
    $aluno->uf           = $cepData['uf'];
    $aluno->municipio    = $cepData['localidade'];
    $aluno->bairro       = $cepData['bairro'];
    $aluno->logradouro   = $cepData['logradouro'];
    $aluno->cep          = $cep;
    $aluno->password     = Hash::make($req->fone1);
    $aluno->lat          = $location['lat'];
    $aluno->lng          = $location['lng'];
    $aluno->indicacao    = $req->indicacao;
    $aluno->solicitacao  = null;
    $aluno->token        = random_int(1000, 9999);
    $aluno->jornada      = null;

    // Termos
    $aluno->termos       = [
      'ip'               => request()->ip(),
      'ua'               => $_SERVER['HTTP_USER_AGENT'],
      'date'             => Carbon::now()->toDateTimeString(),
      'versao'           => 1
    ];

    $aluno->save();

    // JWT
    $secret = config('app.JWT_SECRET_ALUNO');
    $jwt = JWT::encode(
      [
        'aluno_id'    => $aluno->id,
        'iss'         => 'dirigiragora',                                // issuer
        'iat'         => Carbon::now()->getTimestamp(),               // issued at
      ],
      $secret,
      'HS256'
    );

    // E-mail token
    Log::info(__METHOD__ . ' - ' . $aluno->email);
    // MailRepository::sendToken($aluno->email, $aluno->token);                                         // WApiRepository::sendMessage('4188623051', "*Dados do Aluno*\nNome: $aluno->nome\nTelefone: $aluno->fone1\nE-mail: $aluno->email\nCEP: $aluno->cep");

    // $instrutoresCount = InstrutorRepository::proximosLinear($aluno->lat, $aluno->lng)->count();
    // WApiRepository::sendMessage('41997629021', "*Dados do Aluno*\nNome: $aluno->nome\nTelefone: $aluno->fone1\nE-mail: $aluno->email\nCEP: $aluno->cep\nMunicipio: $aluno->municipio\nUF: $aluno->uf\nHabilitado: $habilitado\nInstrutores Próximos: $instrutoresCount");

    // Boas vindas chat do aluno
    ChatRepository::sendMessage('Instrutor', 348, $aluno->id, "🚗💚 Que bom ter você com a gente! 🎉

Aqui funciona assim: após comprar as aulas, nós cuidamos de todo o resto para você (com suporte incluso e sem custos extras!). Você não precisa perder tempo correndo atrás de burocracia no Detran sozinho(a).

Para darmos o primeiro passo e você garantir esse acompanhamento completo, me conta: em qual etapa do processo você está?
 * Já deu entrada no Detran ou fez os exames?
 * Ou ainda está começando do zero?");

    return $this->response(['jwt' => $jwt, 'aluno' => $aluno]);
  }

  public function login(Request $req) {

    // return $this->response('Servidor em manutenção, por favor tente novamente mais tarde', false);

    $email = strtolower(trim($req->email));
    $fone1 = Helpers::onlyN(trim($req->fone1));
    if (!RateLimiter::attempt('IP_ADDRESS_' . $email, $perMinute = 5, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $aluno = Aluno::where('email', $email)->where('fone1', $fone1)->first();
    if (!$aluno || !Hash::check($fone1, $aluno->password)) {
      Log::info(__METHOD__ . ' - Dados ALUNO não encontrados - ' . $email . ' - ' . $fone1);
      return $this->response('Cadastro não encontrado. Revise os dados ou cadastre-se novamente.', false);
    }

    if ($aluno->status === 'X') {
      return $this->response('Este perfil foi excluído. Em caso de dúvidas, entre em contato com o suporte.', false);
    }

    // JWT
    $secret = config('app.JWT_SECRET_ALUNO');
    $jwt = JWT::encode(
      [
        'aluno_id'    => $aluno->id,
        'iss'         => 'dirigiragora',                                // issuer
        'iat'         => Carbon::now()->getTimestamp(),               // issued at
      ],
      $secret,
      'HS256'
    );

    // E-mail token
    if ($aluno->status === 'I') {
      $token = $aluno->token;
      if (!$token) $aluno->token = random_int(1000, 9999);
      $aluno->save();
      MailRepository::sendToken($aluno->email, $aluno->token);
    }

    return $this->response(['jwt' => $jwt, 'aluno' => $aluno]);
  }

  public function lista(Request $req) {

    // throw new Exception('Acesso negado');

    if (!$this->checkBasicAuth()) {
      return response('Unauthorized', 401, [
        'WWW-Authenticate' => 'Basic realm="Área Restrita"'
      ]);
    }

    $instrutores = Aluno::orderBy('id', 'desc')->get();

    if ($instrutores->isEmpty()) {
      return '<p>Nenhum registro encontrado.</p>';
    }

    // Pega dinamicamente os nomes das colunas a partir do primeiro registro
    $colunas = array_keys($instrutores->first()->getAttributes());

    // Remove o campo password
    $colunas = array_filter($colunas, function ($coluna) {
      return $coluna !== 'password' && $coluna !== 'termos';
    });

    $html = '<table border="1" cellpadding="5">';
    $html .= '<tr>';
    foreach ($colunas as $coluna) {
      $html .= '<th>' . ucfirst($coluna) . '</th>';
    }
    $html .= '</tr>';

    foreach ($instrutores as $i) {
      $html .= '<tr>';
      foreach ($colunas as $coluna) {
        $html .= '<td>' . $i->$coluna . '</td>';
      }
      $html .= '</tr>';
    }

    $html .= '</table>';

    return $html;
  }

  public function validarToken(Request $req) {
    // Ratelimit
    if (!RateLimiter::attempt(__METHOD__ . '_' . strtolower($req->aluno->email), $perMinute = 5, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $aluno = Aluno::find($req->aluno->id);
    if ($req->token !== $aluno->token) return $this->response('Token inválido', false);

    $aluno->token = null;
    $aluno->status = 'A';
    $aluno->save();

    return $this->response(true);
  }

  public function promocao(Request $req) {
    $promocao = Promocao::latest('id')->first();
    return $this->response($promocao);
  }

  public function update(Request $req) {

    $aluno = Aluno::find($req->aluno->id);

    // Caso seja mudança de CEP
    if ($req->cep && $req->cep != $aluno->cep) {
      $cep            = Helpers::onlyN($req->cep);
      $cepData        = GMapsRepository::getCepV3($cep);
      $location       = GMapsRepository::geocode($cepData['logradouro'], $cepData['bairro'], $cepData['localidade'], $cepData['uf']);
      if (!$location['lat'] || !$location['lng']) {
        $this->response('Erro ao encontrar as coordenadas do endereço. Verifique o CEP e tente novamente.', false);
      }
      $aluno->cep          = Helpers::onlyN($req->cep);
      $aluno->lat          = $location['lat'];
      $aluno->lng          = $location['lng'];
      $aluno->uf           = $cepData['uf'];
      $aluno->municipio    = $cepData['localidade'];
      $aluno->bairro       = $cepData['bairro'];
      $aluno->logradouro   = $cepData['logradouro'];

      Log::info(__METHOD__ . ' - ' . $aluno->id . ' - ' . $aluno->cep);
    }

    $aluno->save();

    // Limpa o cache
    AlunoRepository::clearCache($aluno->id);

    return $this->response($aluno);
  }

  public function alunoSolicitacao(Request $req) {
    $aluno = Aluno::where('id', $req->aluno->id)->first();
    $aluno->solicitacao = $req->solicitacao;
    $aluno->save();
    return $this->response(true);
  }

  // {"jornada":{"objetivo":"primeira","etapas":{"iniciarProcesso":{"completed":true,"docCompleted":true,"medicoCompleted":true,"psicologicoCompleted":true},"aulasTeoricas":{"completed":true},"exameTeorico":{"completed":true,"agendaExame":true,"seuExame":true},"aulasPraticas":{"completed":false},"examePratico":{"completed":false,"agendaExame":true,"seuExame":true}}}}
  public function updateJornada(Request $req) {
    $aluno = Aluno::find($req->aluno->id);
    $aluno->jornada = $req->jornada;
    $aluno->save();
    return $this->response(true);
  }

  public function deletarConta(Request $req) {
    $aluno = Aluno::find($req->aluno->id);
    $aluno->status = 'X';
    $aluno->save();
    return $this->response(true);
  }

  public function updateOnboard(Request $req) {
    if (!$req->onboard) return $this->response(true);
    $aluno = Aluno::find($req->aluno->id);
    $aluno->onboard = $req->onboard;
    $aluno->save();

    AlunoRepository::updateSolicitacaoByOnboard($aluno->id);

    return $this->response(true);
  }

  public function recusarProposta(Request $req) {

    // Remover valores da proposta do chatList
    $chatList = ChatList::firstOrNew(['hash' => $req->hash]);
    if (!$chatList) return $this->response('Chat nao encontrado', false);
    $proposta = $chatList->proposta ?? new \stdClass();
    unset($proposta->vBruto);
    unset($proposta->vLiquido);
    $chatList->proposta = $proposta;
    $chatList->save();

    // Enviar msg no chat direcionada pro instrutor, com exclusivo true
    // ----- chatMsg
    $chatMsg                = new ChatMsg();
    $chatMsg->de            = 'Aluno';
    $chatMsg->aluno_id      = $chatList->aluno_id;
    $chatMsg->instrutor_id  = $chatList->instrutor_id;
    $chatMsg->conteudo      = 'Mensagem automática: você recusou a proposta, mas a negociação continua aberta. Envie uma mensagem a este ou outro instrutor(a) para encontrarem um valor ideal.';
    $chatMsg->threadId      = $chatList->threadId;
    $chatMsg->lidoAluno     = 1;
    $chatMsg->lidoInstrutor = 0;
    $chatMsg->oculto        = 1;
    $chatMsg->exclusivo     = 0;
    $chatMsg->save();

    // Push pro instrutor sobre a recuso
    // TODO

    // Enviar msg no chat direcionada pro aluno, com oculto true
    // ----- chatMsg
    $chatMsg                = new ChatMsg();
    $chatMsg->de            = 'Aluno';
    $chatMsg->aluno_id      = $chatList->aluno_id;
    $chatMsg->instrutor_id  = $chatList->instrutor_id;
    $chatMsg->conteudo      = 'Mensagem automática: o aluno clicou em recusar sua proposta. Você pode enviar outra oferta ou continuar conversando.';
    $chatMsg->threadId      = $chatList->threadId;
    $chatMsg->lidoAluno     = 1;
    $chatMsg->lidoInstrutor = 0;
    $chatMsg->oculto        = 0;
    $chatMsg->exclusivo     = 1;
    $chatMsg->save();

    // Enviar push da rejeição da proposta
  NotificationRepository::alunoRejeitaProposta($chatList->aluno_id, $chatList->instrutor_id);

    // Telegram da Jenifinha
    TelegramRepository::sendMessageToTopic($chatList->threadId, 'PROPOSTA RECUSADA ALUNO', 'Aluno');

    return $this->response(true);
  }

  // // Gera o JWT, validando via middleware a chave recebida
  // public function login(Request $req) {

  //   // Conexão
  //   $baseConn = json_decode($req->header('BaseConn'));
  //   Config::set('database.default', $baseConn->base);

  //   if (!RateLimiter::attempt('IP_ADDRESS_' . strtolower($req->email), $perMinute = 10, function(){}))
  //     return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

  //   $user       = User::where('email', strtolower($req->email))->where('status', 'A')->first();
  //   if (!$user || !Hash::check($req->password, $user->password)) {
  //     return $this->response('Usuário ou senha inválidos', false);
  //   }

  //   // JWT
  //   $secret = config('app.JWT_SECRET_ALUNO');
  //   // 'exp'         => Carbon::now()->addWeek(1)->getTimestamp(),   // expiration
  //   $jwt = JWT::encode(
  //     [
  //       'user_id'     => $user->id,
  //       'iss'         => 'nf-backend',                                // issuer
  //       'iat'         => Carbon::now()->getTimestamp(),               // issued at
  //     ],
  //     $secret,
  //     'HS256'
  //   );

  //   // Emp
  //   $emp = Emp::find(1);

  //   return $this->response(['jwt' => $jwt, 'user' => $user, 'emp' => $emp]);
  // }

  // public function changeUserImg(Request $req) {
  //   // $base64     = substr($req->base64, strpos($req->base64, ',') + 1);
  //   // $token      = FirebaseRepository::getToken();
  //   // $imgName    = data_get(FirebaseRepository::postImage($base64, $token), 'name');

  //   // $user       = User::find($req->user->id);
  //   // $user->img  = $imgName;
  //   // $user->save();
  //   // return $this->response($user);
  // }

  // // payload {docs: [base64, base64, base64]}
  // public function sendDocs(Request $req) {

  //   // $token = FirebaseRepository::getToken();
  //   // $identidade = [
  //   //   'status' => 'Em análise',
  //   //   'doc0' => '',
  //   //   'doc1' => '',
  //   //   'doc2' => '',
  //   // ];
  //   // for ($i = 0; $i < count($req->docs); $i++) {
  //   //   $base64     = substr($req->docs[$i], strpos($req->docs[$i], ',') + 1);
  //   //   $startPos   = strpos($req->docs[$i], ':') + 1;
  //   //   $endPos     = strpos($req->docs[$i], ';');
  //   //   $mimeType   = substr($req->docs[$i], $startPos, $endPos - $startPos);
  //   //   $extension  = isset(FirebaseRepository::$mimeToExt[$mimeType]) ? FirebaseRepository::$mimeToExt[$mimeType] : 'jpg';
  //   //   $identidade['doc' . $i] = data_get(FirebaseRepository::postImage($base64, $token, $mimeType, $extension), 'name');
  //   // }

  //   // $user               = User::find($req->user->id);
  //   // $user->identidade   = $identidade;
  //   // $user->save();

  //   // return $this->response($user);
  // }

  // public function getPontos(Request $req) {
  //   $user = User::find($req->user->id);
  //   return $this->response([
  //     'identidade' => $user->identidade,
  //     'pontos' => $user->pontos,
  //     'reais' => $user->reais,
  //     'premium_at' => $user->premium_at
  //   ]);
  // }

  // public function post(Request $req) {
  //   $user = User::find($req->user->id);
  //   $user->fone       = $req->fone;
  //   $user->apelido    = $req->apelido;
  //   $user->chavePix   = $req->chavePix;
  //   $user->save();

  //   return $this->response($user, true);
  // }

  // public function search(Request $req) {
  //   $users = User::orderBy('id', 'desc')
  //     ->offset($req->page * 50)
  //     ->limit(50)
  //     ->get();
  //   return $this->response($users);
  // }

  // public function getIndicacao(Request $req) {
  //   $indicacao = User::select('id', 'apelido', 'created_at')->where('indicacao', $req->user->id)->get();
  //   return $this->response($indicacao);
  // }

  // public function aprovarDocs(Request $req) {
  //   $user               = User::find($req->input('user.id'));
  //   $user->identidade   = ['status' => 'Aprovado'];
  //   $user->doc          = $req->input('user.doc');
  //   $user->nascimento   = $req->input('user.nascimento');
  //   $user->save();

  //   Mail::send('mail.identidade-aprovada', [], fn($m) => $m->to($user->email)->subject('Identidade confirmada!'));

  //   return $this->response($user);
  // }

  // public function reprovarDocs(Request $req) {
  //   $user             = User::find($req->input('user.id'));
  //   $user->identidade = new \stdClass();
  //   $user->save();

  //   Mail::send('mail.identidade-reprovada', [], fn($m) => $m->to($user->email)->subject('Não foi possível confirmar sua identidade'));

  //   return $this->response($user);
  // }

  // public function desconectarContas(Request $req) {
  //   $user         = User::find($req->input('user.id'));
  //   $user->riotId = null;
  //   $user->save();

  //   return $this->response($user);
  // }

  // public function update(Request $req) {
  //   $user = User::find($req->input('user.id'));
  //   $user->pontos = $req->input('user.pontos');
  //   $user->save();
  //   return $this->response();
  // }

  // public function recoverLink(Request $req) {

  //   if (!RateLimiter::attempt('IP_ADDRESS_' . strtolower($req->email), $perMinute = 10, function(){}))
  //     return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

  //   $user = User::where('email', strtolower($req->email))->where('status', 'A')->first();
  //   if (!$user) return $this->response('Usuário inválido', false);

  //   // Salvar token de recuperação
  //   $token = Str::random(64);
  //   $user->token = $token;
  //   $user->save();

  //   // Envio do email
  //   Mail::send('mail.recuperacao-senha', ['url' => config('app.FRONT_URL') . "login#alterar-senha?token=$token"], function ($m) use ($user) {
  //     $m->to($user->email)->subject('Recuperação de senha');
  //   });

  //   return $this->response('Link de recuperação enviado ao e-mail');
  // }

  // public function changePassword(Request $req) {
  //   if (!RateLimiter::attempt('IP_ADDRESS_' . strtolower($req->email), $perMinute = 10, function(){}))
  //     return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

  //   $user = User::where('token', $req->token)->where('status', 'A')->first();
  //   if (!$user) return $this->response('Token inválido', false);
  //   if ($req->pass1 != $req->pass2) return $this->response('Senhas diferentes', false);

  //   // Save new password
  //   $user->password     = Hash::make($req->pass1);
  //   $user->token        = null;
  //   $user->status       = 'A';
  //   $user->save();
  //   return $this->response('Senha alterada com sucesso! Redirecionando para a tela de login...');
  // }

  // public function register(Request $req) {
  //   if (!RateLimiter::attempt('IP_ADDRESS_' . strtolower($req->email), $perMinute = 10, function(){}))
  //     return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

  //   // Verifica se o email já existe
  //   if (User::where('email', $req->email)->exists()) return $this->response('Email já cadastrado', false);
  //   // Verifica se o CPF ou telefone já existe
  //   if (User::where('fone', $req->fone)->orWhere('doc', $req->doc)->exists()) return $this->response('CPF ou telefone já cadastrado', false);

  //   // Cadastra o novo usuário com o token de verificação
  //   $user               = new User();
  //   $user->role         = 'Usuário';
  //   $user->status       = 'A';
  //   $user->nome         = $req->nome;
  //   $user->apelido      = $req->apelido;  // $user->apelido  = Str::before( $req->nome, ' ');
  //   $user->email        = $req->email;
  //   $user->fone         = $req->fone;
  //   $user->doc          = $req->doc;
  //   $user->password     = Hash::make($req->pass1);
  //   $user->token        = Str::random(64);
  //   $user->identidade   = new \stdClass();
  //   $user->pontos       = 0;
  //   $user->reais        = 0;
  //   $indicacao          = $req->indicacao;
  //   if ($req->indicacao === 'femboyslut') $indicacao = 232;
  //   if ($req->indicacao === 'UCLA')       $indicacao = 161;
  //   if ($indicacao) {
  //     $user->indicacao = Helpers::onlyN($indicacao);
  //   }
  //   $user->indTornProc  = false;
  //   $user->save();

  //   if ($req->indicacao === 'UCLA') {
  //     $user->reais = 10;
  //     $user->save();

  //     $pontoMov               = new PontoMov();
  //     $pontoMov->user_id      = $user->id;
  //     $pontoMov->tipo         = 'Premiação';
  //     $pontoMov->descricao    = 'Bônus de indicação na confirmação de cadastro';
  //     $pontoMov->reais        = 10;
  //     $pontoMov->saldoR       = $user->reais;
  //     $pontoMov->save();
  //   }

  //   // // ----- pontoMov bônus
  //   // $pontoMov               = new PontoMov();
  //   // $pontoMov->user_id      = $user->id;
  //   // $pontoMov->tipo         = 'Premiação';
  //   // $pontoMov->descricao    = 'Bônus recebido por cadastro na BLC (versão beta)';
  //   // $pontoMov->pontos       = 5000;
  //   // $pontoMov->saldo        = $user->pontos;
  //   // $pontoMov->save();

  //   // ERRO NA ZOHO
  //   // Enviar o token para o e-mail
  //   // Mail::send('mail.register-confirm', ['nome' => $user->nome, 'url' => config('app.APP_URL') . "v1/user/register-confirm/$user->token"], function ($m) use ($user) {
  //   //   $m->to($user->email)->subject('Confirmação de cadastro');
  //   // });


  //   return $this->response('Seu cadastro foi confirmado com sucesso!');
  //   // return $this->response('Um email de confirmação foi enviado (Verifique a caixa de SPAM)');
  // }

  // public function registerConfirm(Request $req, $token) {
  //   if (!RateLimiter::attempt('IP_ADDRESS_' . strtolower($req->email), $perMinute = 10, function(){}))
  //     return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

  //   // Encontra usuário pelo token, se existir e for status U aprova
  //   $user = User::where('token', $token)->where('status', 'U')->first();
  //   if (!$user) return $this->response('Usuário já aprovado ou token inválido', false);
  //   // $user->token = null;
  //   $user->status = 'A';
  //   $user->save();

  //   // ----- indicacao
  //   if ($user->indicacao) {

  //     // ----- Indicado ganha 500 BLP quando confirma o email do cadastro
  //     // $user->pontos = $user->pontos + 500;
  //     // $user->save();

  //     // // ----- pontoMov indicador
  //     // $pontoMov               = new PontoMov();
  //     // $pontoMov->user_id      = $user->id;
  //     // $pontoMov->tipo         = 'Premiação';
  //     // $pontoMov->descricao    = 'Bônus de indicação na confirmação de cadastro';
  //     // $pontoMov->pontos       = 500;
  //     // $pontoMov->saldo        = $user->pontos;
  //     // $pontoMov->save();

  //     // ----- Indicado ganha 10 BRL quando confirma o email do cadastro
  //     $user->reais = $user->reais + 10;
  //     $user->save();

  //     // ----- pontoMov indicador
  //     $pontoMov               = new PontoMov();
  //     $pontoMov->user_id      = $user->id;
  //     $pontoMov->tipo         = 'Premiação';
  //     $pontoMov->descricao    = 'Bônus de indicação na confirmação de cadastro';
  //     $pontoMov->reais        = 10;
  //     $pontoMov->saldoR       = $user->reais;
  //     $pontoMov->save();
  //   }

  //   // Retorna um redirect para o front
  //   return redirect(config('app.FRONT_URL') . '/login#registro-confirmado');
  // }

}
