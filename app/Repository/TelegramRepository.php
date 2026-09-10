<?php

namespace App\Repository;

use App\Http\Controllers\AlunoRepository;
use App\Http\Controllers\InstrutorRepository;
use App\Models\Aluno;
use App\Models\ChatList;
use App\Models\ChatMsg;
use App\Models\PushToken;
use Illuminate\Support\Facades\Http;
use App\Jobs\SendTelegramMessageJob;
use App\Libraries\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramRepository
{

  // https://chatgpt.com/c/69667de5-40d0-832e-b4b3-2529c2dfed21
  private const BOT_TOKEN = '8012871602:AAH-qPXKyJqfaWbPYoFFnpHYPpYUrYrzPkc';
  private const GROUP_ID = -1003532265386;

  /**
   * Cria um tópico (Forum Topic) para um chat
   */
  public static function createChatTopic(string $instrutor_id, string $aluno_id) {
    $instrutor  = InstrutorRepository::getById($instrutor_id);
    $aluno      = AlunoRepository::getById($aluno_id);
    try {
      $instrutorNome  = explode(' ', $instrutor->nome)[0];
      $alunoNome      = explode(' ', $aluno->nome)[0];
      $response = Http::post(
        'https://api.telegram.org/bot' . self::BOT_TOKEN . '/createForumTopic',
        [
          'chat_id' => self::GROUP_ID,
          'name' => "$instrutor_id $instrutorNome | $aluno_id $alunoNome",
        ]
      );

      if (!$response->ok()) {
        Log::error('Telegram createForumTopic error', $response->json());
        return null;
      }
      if (!isset($response['result']['message_thread_id'])) {
        Log::error('Thread ID ausente', $response->json());
        return null;
      }
      return (int) $response['result']['message_thread_id'];
    } catch (\Throwable $th) {
      Log::error('Telegram error', ['message' => $th->getMessage()]);
      return null;
    }
  }

  /**
   * Envia mensagem para um tópico existente
   */
    public static function sendMessageToTopic(int $messageThreadId, string $text, string $de, string $fone = '') {
    // Envio en segundo plano: a API responde a la hora y el envio HTTP Telegram se
    // ejecuta DESPUES de ya haberse enviado la respuesta (afterResponse).
    SendTelegramMessageJob::dispatch(
      'https://api.telegram.org/bot' . self::BOT_TOKEN . '/sendMessage',
      [
        'chat_id' => self::GROUP_ID,
        'message_thread_id' => $messageThreadId,
        'text' => $de . ' ' . $fone . ' :' . $text,
        'parse_mode' => 'HTML',
      ]
    );
  }

  public static function setWebhook(string $url) {
    // https://api.telegram.org/botTOKEN/setWebhook?url=https://seusite.com/telegram/webhook
    $response = Http::get('https://api.telegram.org/bot' . self::BOT_TOKEN . '/setWebhook?url=' . $url);

    if (!$response->ok()) {
      Log::error('Telegram setWebhook error', $response->json());
      throw new \Exception('Erro ao configurar o webhook no Telegram');
    }
    return $response->json();
  }

  // {"update_id":87140467,"message":{"message_id":36,"from":{"id":1505347978,"is_bot":false,"first_name":"Rodolfo","last_name":"Nogueira","username":"rodolfojnn","language_code":"pt-br"},"chat":{"id":-1003424252989,"title":"Grupo Dirigir Agora","is_forum":true,"type":"supergroup"},"date":1768330929,"message_thread_id":28,"reply_to_message":{"message_id":28,"from":{"id":8217018384,"is_bot":true,"first_name":"Dirigir Agora BOT","username":"dirigir_agora_teste_bot"},"chat":{"id":-1003424252989,"title":"Grupo Dirigir Agora","is_forum":true,"type":"supergroup"},"date":1768329227,"message_thread_id":28,"forum_topic_created":{"name":"389 Rodolfo - 178 Jenifer","icon_color":7322096},"is_topic_message":true},"text":"oi","is_topic_message":true}}
  public static function webhook(Request $req) {
    // Log::info(json_encode($req->all()));
    $threadId = $req->input('message.message_thread_id');
    $text     = $req->input('message.text');
    if (!$threadId || !$text) return;

    // ----- chatList
    $chatList = ChatList::where('threadId', $threadId)->first();
    if (!$chatList) return;

    // Comando do admin
    // #remove_N# remove as últimas N mensagens do ChatMsg
    if (preg_match('/^#remove_(\d+)#$/', $text, $matches)) {
      $n = (int) $matches[1];
      if ($n > 0) {
        $msg = ChatMsg::where('threadId', $threadId)
          ->orderBy('id', 'desc')
          ->skip($n - 1)
          ->first();
        if ($msg) {
          $msg->conteudo = '(Mensagem não enviada: Conteúdo não permitido. Dados pessoais apenas após a compra. Aluno deve selecionar categoria e quantidade de aulas desejadas e pagar em cartão ou PIX).';
          $msg->save();
          if ($n === 1) {
            $chatList->ultimaMensagem   = $msg->conteudo;
            $chatList->ultimaMensagemAt = now();
            $chatList->save();
          }
        }
      }
      return;
    }

    // #instrutores# gera informações do instrutor para o admin
    if ($text === '#instrutores#') {
      $aluno = Aluno::find($chatList->aluno_id);
      $instrutoresProximos = InstrutorRepository::proximosLinear($aluno->lat, $aluno->lng);
      $carOwn     = $instrutoresProximos->where('carOwn', 1)->count();
      $carAluno   = $instrutoresProximos->where('carAluno', 1)->count();
      $bikeOwn    = $instrutoresProximos->where('bikeOwn', 1)->count();
      $bikeAluno  = $instrutoresProximos->where('bikeAluno', 1)->count();
      $msg = "{$aluno->cep}, {$aluno->municipio}, {$aluno->uf}\ $carOwn / $carAluno / $bikeOwn / $bikeAluno";
      self::sendMessageToTopic($threadId, $msg, 'Admin');
      return;
    }

    // #oculto_N# oculta a linha do lado oposto
    if (preg_match('/^#oculto_(\d+)#$/', $text, $matches)) {
      $n = (int) $matches[1];
      if ($n > 0) {
        $msg = ChatMsg::where('threadId', $threadId)
          ->orderBy('id', 'desc')
          ->skip($n - 1)
          ->first();
        if ($msg) {
          $msg->oculto = true;
          $msg->save();
          if ($n === 1) {
            $chatList->ultimaMensagem   = '';
            $chatList->ultimaMensagemAt = now();
            $chatList->save();
          }
        }
      }
      return;
    }

    // #desoculto_N# oculta a linha do lado oposto
    if (preg_match('/^#desoculto_(\d+)#$/', $text, $matches)) {
      $n = (int) $matches[1];
      if ($n > 0) {
        $msg = ChatMsg::where('threadId', $threadId)
          ->orderBy('id', 'desc')
          ->skip($n - 1)
          ->first();
        if ($msg) {
          $msg->oculto = false;
          $msg->save();
          if ($n === 1) {
            $chatList->ultimaMensagem   = '';
            $chatList->ultimaMensagemAt = now();
            $chatList->save();
          }
        }
      }
      return;
    }

    // #proposta_NNNN# cria/atualiza a proposta do chat com o valor bruto NNNN (3 ou 4 dígitos)
    if (preg_match('/^#proposta_(\d{3,4})#$/', $text, $matches)) {
      $vBruto = (int) $matches[1];
      if ($vBruto > 0) {
        // Taxa do app do instrutor (padrão 10%)
        $instrutor = InstrutorRepository::getById($chatList->instrutor_id);
        $vTaxaApp  = $instrutor->vTaxaApp ?: 10;

        // vLiquido = vBruto + taxa do app
        $vLiquido = $vBruto * (1 + $vTaxaApp / 100);

        // Preserva as demais propriedades da proposta existente
        $proposta = $chatList->proposta ?? new \stdClass();
        $proposta->vBruto   = Helpers::N2($vBruto);
        $proposta->vLiquido = Helpers::N2($vLiquido);

        $chatList->proposta = $proposta;
        $chatList->save();
      }
      return;
    }

    // #perfil# url do perfil do instrutor pro admin
    // https://www.dirigiragora.com.br/indicacao-aluno/{instrutor_id}
    if ($text === '#perfil#') {
      self::sendMessageToTopic($threadId, 'https://www.dirigiragora.com.br/indicacao-aluno/' . $chatList->instrutor_id, 'Admin');
      return;
    }

    // #onboar# envia as informações da proposta (chatList.proposta) para o admin, em JSON formatado
    if ($text === '#onboard#') {
      $proposta = $chatList->proposta ?? new \stdClass();
      $json = json_encode($proposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      self::sendMessageToTopic($threadId, $json, 'Admin');
      return;
    }

    // #aluno_mensagem# cria a mensagem como se fosse o aluno (de = 'Aluno')
    // Ex.: #aluno_oi tudo bem? a proposta é a seguinte...# -> mensagem = "oi tudo bem? a proposta é a seguinte..."
    if (preg_match('/^#aluno_(.+)#$/s', $text, $matches)) {
      $mensagem = $matches[1];

      // ----- chatMsg
      $chatMsg                = new ChatMsg();
      $chatMsg->de            = 'Aluno';
      $chatMsg->aluno_id      = $chatList->aluno_id;
      $chatMsg->instrutor_id  = $chatList->instrutor_id;
      $chatMsg->conteudo      = $mensagem;
      $chatMsg->threadId      = $threadId;
      $chatMsg->lidoAluno     = 1;
      $chatMsg->lidoInstrutor = 0;
      $chatMsg->exclusivo     = 1;
      $chatMsg->save();

      // Atualizar chatList
      $chatList->ultimoDe         = 'Aluno';
      $chatList->ultimaMensagem   = '';
      $chatList->ultimaMensagemAt = now();
      $chatList->unreadInstrutor += 1;
      $chatList->save();

      return;
    }

    // #instrutor_mensagem# cria a mensagem como se fosse o instrutor (de = 'Instrutor')
    // Ex.: #instrutor_oi tudo bem? a proposta é a seguinte...# -> mensagem = "oi tudo bem? a proposta é a seguinte..."
    if (preg_match('/^#instrutor_(.+)#$/s', $text, $matches)) {
      $mensagem = $matches[1];

      // ----- chatMsg
      $chatMsg                = new ChatMsg();
      $chatMsg->de            = 'Instrutor';
      $chatMsg->aluno_id      = $chatList->aluno_id;
      $chatMsg->instrutor_id  = $chatList->instrutor_id;
      $chatMsg->conteudo      = $mensagem;
      $chatMsg->threadId      = $threadId;
      $chatMsg->lidoAluno     = 0;
      $chatMsg->lidoInstrutor = 1;
      $chatMsg->exclusivo     = 1;
      $chatMsg->save();

      // Atualizar chatList
      $chatList->ultimoDe         = 'Instrutor';
      $chatList->ultimaMensagem   = '';
      $chatList->ultimaMensagemAt = now();
      $chatList->unreadAluno     += 1;
      $chatList->save();

      return;
    }

    // ----- chatMsg
    $chatMsg                = new ChatMsg();
    $chatMsg->de            = 'Admin';
    $chatMsg->aluno_id      = $chatList->aluno_id;
    $chatMsg->instrutor_id  = $chatList->instrutor_id;
    $chatMsg->conteudo      = $text;
    $chatMsg->threadId      = $threadId;
    $chatMsg->lidoAluno     = 0;
    $chatMsg->lidoInstrutor = 0;
    $chatMsg->save();

    // Atualizar chatList
    $chatList->ultimoDe         = 'Admin';
    $chatList->ultimaMensagem   = $text;
    $chatList->ultimaMensagemAt = now();
    $chatList->unreadAluno     += 1;
    $chatList->unreadInstrutor += 1;
    $chatList->save();

    // 1. PUSH ALUNO
    $tokens = PushToken::where('owner_type', 'aluno')->where('owner_id', $chatList->aluno_id)->get();
    if ($tokens->isNotEmpty()) {
      // ----- ANDROID
      $androidTokens = $tokens->where('platform', 'android')->pluck('token')->toArray();
      if (!empty($androidTokens)) {
        FirebaseRepository::sendNotificationTokens($androidTokens, 'Nova mensagem', 'Você recebeu uma nova mensagem no Dirigir Agora');
      }
      // ----- IOS
      $iosTokens = $tokens->where('platform', 'ios')->pluck('token')->toArray();
      if (!empty($iosTokens)) {
        ApnRepository::sendNotificationTokens($iosTokens, 'Nova mensagem', 'Você recebeu uma nova mensagem no Dirigir Agora');
      }
    }

    // 2. PUSH INSTRUTOR
    // TODO


  }

}