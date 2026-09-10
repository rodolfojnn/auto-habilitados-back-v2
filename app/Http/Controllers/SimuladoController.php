<?php

namespace App\Http\Controllers;

use App\Libraries\Helpers;
use App\Models\AlunoRenach;
use App\Models\SimAluno;
use App\Models\SimChatList;
use App\Models\SimChatMsg;
use App\Models\SimPushToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use stdClass;

class SimuladoController extends Controller
{

  public function newAutomacaoLead(Request $req) {
    Log::channel('email')->info(__METHOD__ . ' - ' . json_encode($req->all()));
    return response()->json(['status' => true]);
  }

  public function postRenach(Request $req) {
    $alunoRenach                = AlunoRenach::withTrashed()->firstOrNew(['renach' => 'PR' . $req->renach]);
    $alunoRenach->deleted_at    = null;
    if ($req->dataEmissao)      $alunoRenach->dtAbertura    = Carbon::createFromFormat('d/m/Y', $req->dataEmissao)->format('Y-m-d');
    if ($req->nome)             $alunoRenach->nome          = $req->nome;
    if ($req->cpf)              $alunoRenach->cpf           = $req->cpf;
    $alunoRenach->uf            = 'PR';
    $alunoRenach->municipio     = $req->municipio;
    $alunoRenach->utr           = $req->utr;
    $alunoRenach->cep           = Helpers::onlyN($req->cep) ?: null;
    $alunoRenach->fone          = Helpers::onlyN($req->telefone) ?: null;
    $alunoRenach->email         = $req->email;
    $alunoRenach->categoria     = $req->categoria;
    $alunoRenach->motivo        = $req->motivo;
    $alunoRenach->save();
    return response()->json(['status' => true]);
  }

  public function getRenach(Request $req) {
    $alunoRenach = AlunoRenach::select('id', 'renach')->whereNull('dtAbertura')->first();
    if (!$alunoRenach) return response()->json(['status' => true, 'renach' => null]);
    $alunoRenach->delete();
    return response()->json(['status' => true, 'renach' => $alunoRenach->renach]);
  }

  public function newLead(Request $req) {
    // Log::channel('email')->info(__METHOD__ . ' - ' . json_encode($req->all()));
    $payload = $req->all();
    $lead = $payload['lead_data'] ?? [];

    $aluno = SimAluno::firstOrNew([
      'nome' => $lead['nome'],
      'cep' => $lead['cep']
    ]);

    $aluno->email       = null; // $lead['email'] ?? null;
    $aluno->nome        = $lead['nome'] ?? null;
    $aluno->uf          = $lead['uf'] ?? null;
    $aluno->municipio   = $lead['municipio'] ?? null;
    $aluno->cep         = $lead['cep'] ?? null;
    $aluno->solicitacao = null;
    $aluno->extra       = new stdClass(); // ['processo_iniciado' => $payload['processo_iniciado'] ?? null,'metodo_estudo'     => $payload['metodo_estudo'] ?? null,'lead_captured'     => $payload['lead_captured'] ?? null];
    $aluno->origem      = 'simulado';
    $aluno->save();

    // // Caso o id do aluno seja novo, enviar mensagem de boas vindas no simChatList e simChatMsg
    // if ($aluno->wasRecentlyCreated) {
    //   $boasVindas = 'Oi! Tudo bem? Vi que você acabou de entrar no simulado. Já sabe como agendar a sua prova teórica no Detran do seu estado ou tá precisando de ajuda com isso?';

    //   // SimChatList
    //   $chatList                   = new SimChatList();
    //   $chatList->aluno_id         = $aluno->id;
    //   $chatList->ultimoDe         = 'Admin';
    //   $chatList->ultimaMensagem   = $boasVindas;
    //   $chatList->unreadAluno      = 1;
    //   $chatList->save();

    //   // SimChatMsg
    //   $chatMsg            = new SimChatMsg();
    //   $chatMsg->aluno_id  = $aluno->id;
    //   $chatMsg->de        = 'Admin';
    //   $chatMsg->conteudo  = $boasVindas;
    //   $chatMsg->lidoAluno = 0;
    //   $chatMsg->save();
    // }

    return response()->json([
        'success' => true,
        'id' => $aluno->id
    ]);
  }

  public static function pushToken(Request $req) {
    // Log::channel('email')->info(__METHOD__ . ' - ' . json_encode($req->all()));
    if (!$req->nome || !$req->cep) return;
    $aluno = self::cachedAluno($req->nome, $req->cep);
    if (!$aluno) return;

    // Pontuação
    if ($req->pontuacao) {
      $extra = $aluno->extra ?: new stdClass();
      if (!isset($extra->pontuacao) || !is_object($extra->pontuacao)) $extra->pontuacao = new stdClass();
      $extra->pontuacao->{now()->format('my')} = $req->pontuacao;
      $aluno->extra = $extra;
      $aluno->save();
      self::forgetCachedAluno($req->nome, $req->cep);
    }

    $pushToken = SimPushToken::firstOrNew(['token' => $req->token]);
    $pushToken->token         = $req->token;
    $pushToken->owner_type    = 'aluno';
    $pushToken->owner_id      = $aluno->id;
    $pushToken->platform      = $req->platform;
    $pushToken->last_seen_at  = now();
    $pushToken->origem        = 'simulado';
    $pushToken->save();
  }

  public static function sendNotificationTokens(array $tokens, string $titulo, string $mensagem, array $data = []) {
    Log::channel('email')->info(__METHOD__ . ' - ' . count($tokens) . ' ' . $mensagem);

    if (empty($tokens)) return;

    try {
      $factory = (new Factory)->withServiceAccount(storage_path('firebase/firebase-simulado.json'));

      $messaging = $factory->createMessaging();

      $message = CloudMessage::new()
        ->withNotification(
          Notification::create($titulo, $mensagem)
        )
        ->withData($data);

      /** @var MulticastSendReport $report */
      $report = $messaging->sendMulticast($message, $tokens);

      // Processar resultados
      foreach ($report->failures()->getItems() as $failure) {
        $error = $failure->error();

        if ($error instanceof NotFound) {
          $invalidToken = $failure->target()->value();
          // remover token inválido do banco
          SimPushToken::where('token', $invalidToken)->delete();
          Log::channel('email')->warning('Token FCM removido (NotFound)', ['token' => $invalidToken]);
        }
      }

      Log::channel('email')->info('Push multicast', ['success' => $report->successes()->count(), 'failure' => $report->failures()->count()]);

      return ['success' => $report->successes()->count(), 'failure' => $report->failures()->count()];

    } catch (\Throwable $e) {
      Log::channel('email')->error('Erro ao enviar push multicast', ['error' => $e->getMessage()]);
    }
  }

  public static function chat_chatList(Request $req) {
    $aluno = self::cachedAluno($req->nome, $req->cep);
    if (!$aluno) return response()->json(['success' => true, 'messages' => []]);
    $unreadAluno = SimChatList::where('aluno_id', $aluno->id)->where('unreadAluno', '>', 0)->count();

    return response()->json([
      'success' => true,
      'unreadAluno' => $unreadAluno
    ]);
  }

  public static function chat_chatMsgs(Request $req) {
    $aluno = self::cachedAluno($req->nome, $req->cep);
    if (!$aluno) return response()->json(['success' => true, 'messages' => []]);

    // Zerar unreads
    SimChatList::where('aluno_id', $aluno->id)
      ->where('unreadAluno', '>', 0)
      ->update(['unreadAluno' => 0]);
    SimChatMsg::where('aluno_id', $aluno->id)
      ->where('lidoAluno', 0)
      ->update(['lidoAluno' => 1]);

    $messages = SimChatMsg::where('aluno_id', $aluno->id)->orderBy('id', 'desc')
      ->limit(10)
      ->get()
      ->reverse()
      ->values()
      ->toArray();

    return response()->json(['success' => true, 'messages' => $messages]);
  }

  public static function chat_sendMsg(Request $req) {
    $aluno = self::cachedAluno($req->nome, $req->cep);
    if (!$aluno) return response()->json(['success' => false]);

    // SimChatList
    $chatList                   = SimChatList::firstOrNew(['aluno_id' => $aluno->id]);
    $chatList->ultimoDe         = 'Aluno';
    $chatList->ultimaMensagem   = $req->conteudo;
    $chatList->save();

    // SimChatMsg
    $chatMsg                    = new SimChatMsg();
    $chatMsg->aluno_id          = $aluno->id;
    $chatMsg->de                = 'Aluno';
    $chatMsg->conteudo          = $req->conteudo;
    $chatMsg->lidoAluno         = 1;
    $chatMsg->save();

    $messages = SimChatMsg::where('aluno_id', $aluno->id)->orderBy('id', 'desc')
      ->limit(10)
      ->get()
      ->reverse()
      ->values()
      ->toArray();

    return response()->json(['success' => true, 'messages' => $messages]);
  }

  /**
   * Busca o aluno pelo nome+cep usando um cache local de 12h.
   */
  private static function cachedAluno(string $nome, string $cep) {
    return Cache::remember(self::alunoCacheKey($nome, $cep), 60 * 60 * 24, function () use ($nome, $cep) {
      return SimAluno::where('nome', $nome)->where('cep', $cep)->first();
    });
  }

  /**
   * Invalida o cache local do aluno (usado quando o aluno é atualizado).
   */
  private static function forgetCachedAluno(string $nome, string $cep) {
    Cache::forget(self::alunoCacheKey($nome, $cep));
  }

  private static function alunoCacheKey(string $nome, string $cep): string {
    return 'sim_aluno:' . strtolower(trim($nome) . '|' . trim($cep));
  }

}
