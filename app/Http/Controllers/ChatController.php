<?php

namespace App\Http\Controllers;

use App\Libraries\Helpers;
use App\Models\Aluno;
use App\Models\ChatList;
use App\Models\ChatMsg;
use App\Repository\MailRepository;
use App\Repository\NotificationRepository;
use App\Repository\TelegramRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class ChatController extends Controller
{

  public function chatList(Request $req)
  {
    $field      = $req->aluno ? 'aluno_id' : 'instrutor_id';
    $id         = $req->aluno ? $req->aluno->id : $req->instrutor->id;
    $with       = $req->aluno ? 'instrutor' : 'aluno';
    $chatList = ChatList::select('aluno_id', 'instrutor_id', 'hash', 'created_at', 'ultimaMensagem', 'ultimaMensagemAt', 'ultimoDe', 'unreadAluno', 'unreadInstrutor', 'proposta')
      ->where($field, $id)
      ->with($with)
      ->orderBy('ultimaMensagemAt', 'DESC')
      ->limit(50)
      ->get();
    return $this->response($chatList);
  }

  public function chatMsgs(Request $req)
  {
    $isAluno     = $req->auth_type === 'aluno';
    $isInstrutor = $req->auth_type === 'instrutor';

    // Validação
    $chatList = ChatList::where('hash', $req->hash)->first();
    if (!$chatList) return $this->response('Chat não encontrado', false);
    if ($req->aluno && $chatList->aluno_id !== $req->aluno->id) return $this->response('Acesso negado', false);
    if ($req->instrutor && $chatList->instrutor_id !== $req->instrutor->id) return $this->response('Acesso negado', false);

    // 🔄 Zerar contadores (sem mexer em chatMsg por enquanto)
    if ($req->aluno) $chatList->unreadAluno = 0;
    if ($req->instrutor) $chatList->unreadInstrutor = 0;
    $chatList->save();

    // chatMsg lidoAluno
    if ($isAluno) {
      ChatMsg::where('aluno_id', $chatList->aluno_id)
        ->where('instrutor_id', $chatList->instrutor_id)
        // ->where('de', 'Instrutor')
        ->where('lidoAluno', 0)
        ->update(['lidoAluno' => 1]);
    }

    // chatMsg lidoInstrutor
    if ($isInstrutor) {
      ChatMsg::where('aluno_id', $chatList->aluno_id)
        ->where('instrutor_id', $chatList->instrutor_id)
        // ->where('de', 'Aluno')
        ->where('lidoInstrutor', 0)
        ->update(['lidoInstrutor' => 1]);
    }

    $chatMsg = ChatMsg::select('created_at', 'de', 'conteudo', 'lidoAluno', 'lidoInstrutor', 'oculto', 'exclusivo')
      ->where('aluno_id', $chatList->aluno_id)
      ->where('instrutor_id', $chatList->instrutor_id)
      ->orderBy('id', 'desc')
      ->limit(20)
      ->get()
      ->reverse()
      ->values()
      ->filter(function ($chatMsgFilter) use ($isAluno, $isInstrutor) {
        // Shadowban: msg oculta só é visível para quem enviou
        if ($chatMsgFilter->oculto) {
          if ($isAluno && $chatMsgFilter->de === 'Aluno') return true;
          if ($isInstrutor && $chatMsgFilter->de === 'Instrutor') return true;
          return false;
        }
        // Exclusivo: msg visível apenas para quem recibe (quien envía no la ve)
        if ($chatMsgFilter->exclusivo) {
          if ($isAluno && $chatMsgFilter->de === 'Instrutor') return true;
          if ($isInstrutor && $chatMsgFilter->de === 'Aluno') return true;
          return false;
        }
        return true;
      })
      ->values();

    $instrutor = InstrutorRepository::getById($chatList->instrutor_id);
    $aluno     = AlunoRepository::getById($chatList->aluno_id);
    $instrutor = ['id' => $instrutor->id, 'nome' => explode(' ', trim($instrutor->nome))[0]];
    $aluno     = ['id' => optional($aluno)->id, 'nome' => optional($aluno)->nome ? explode(' ', trim($aluno->nome))[0] : null];

    return $this->response([
      'instrutor' => $instrutor,
      'aluno'     => $aluno,
      'proposta'  => $chatList->proposta,
      'messages'  => $chatMsg
    ]);
  }

  public function postChatMessage(Request $req)
  {

    // return $this->response('Chat desativado temporariamente. Em caso de dúvidas entre em contato com o nosso suporte.', false);

    // Usamos 19:59:59 para que às 20:00:00 o bloqueio já passe a valer
    // if (!Carbon::now()->isBetween('09:00', '21:59:59')) {
    //   return $this->response('O envio de mensagens está disponível todos os dias, das 09h às 22h. Tente novamente nestes horários.', false);
    // }

    // Ratelimit pelo IP
    // return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);
    if (!RateLimiter::attempt(__METHOD__ . '_' . request()->ip(), 15, function () {}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    Log::info(__METHOD__ . ' - ' . json_encode($req->all()));

    if (!$req->receiver_id) return $this->response('Destinatário inválido. Aguarde alguns segundos e tente novamente', false);
    $de           = $req->aluno ? 'Aluno' : 'Instrutor';
    $aluno_id     = null;
    $instrutor_id = null;
    $fone         = '';
    if ($de === 'Aluno') {
      $aluno_id   = $req->aluno->id;
      $fone       = $req->aluno->fone1;

      // Solicitação Aluno
      if ($req->aluno->solicitacao === 'Aulas de Carro e Moto') $fone = $fone . ' AB';
      if ($req->aluno->solicitacao === 'Aulas de Carro')        $fone = $fone . ' B';
      if ($req->aluno->solicitacao === 'Aulas de Moto')         $fone = $fone . ' A';

      $instrutor = InstrutorRepository::getById($req->receiver_id);
      if (!$instrutor) return $this->response('Erro na busca do instrutor. Por favor, atualize e tente novamente', false);
      $instrutor_id = $instrutor->id;
    }
    if ($de === 'Instrutor') {
      $aluno_id     = $req->receiver_id;
      $instrutor_id = $req->instrutor->id;
      $fone         = $req->instrutor->fone1;
    }

    if (!$instrutor_id) return $this->response('Instrutor inválido', false);
    if (!$aluno_id) return $this->response('Aluno inválido', false);
    if (!$req->conteudo) return $this->response('Mensagem inválida', false);

    // TODO
    // Instrutor banido, testar com o X no status depois
    // if ($instrutor_id === 705) return $this->response('Erro na busca do instrutor. Por favor, atualize e tente novamente', false);

    // Apenas criar chat
    $onlyCreateChat = $req->conteudo === '##onlyCreateChat##';
    if ($onlyCreateChat) {
      $req->conteudo = '';
    }

    // chatList
    $chatList = ChatList::firstOrNew(['aluno_id' => $aluno_id, 'instrutor_id' => $instrutor_id]);
    // Caso não exista, cria uma thread no Telegram
    if (!$chatList->id) {
      $chatList->threadId = TelegramRepository::createChatTopic($instrutor_id, $aluno_id);
      $chatList->hash     = (string) Str::ulid();
    }
    // Validar se faz ou não checagem de mensagem
    $oculto = $this->contemContatoPessoal($req->conteudo);

    // Não bloquear o ID 3282 que é o fake
    if ($de === 'Aluno' && $aluno_id === 3282) {
      $oculto = false;
    }

    if ($oculto) {
      TelegramRepository::sendMessageToTopic($chatList->threadId, 'BLOQUEADA: ' . $req->conteudo, $de, $fone);
    }
    if (!$onlyCreateChat && !$oculto) {
      $chatList->ultimoDe           = $de;
      $chatList->ultimaMensagem     = $req->conteudo;
      $chatList->ultimaMensagemAt   = now();
      if ($de === 'Aluno') $chatList->unreadInstrutor += 1;
      if ($de === 'Instrutor') $chatList->unreadAluno += 1;
    }

    $chatList->save();

    if ($onlyCreateChat) return $this->response($chatList->hash);

    // Sincroniza `onboard` da proposta caso não exista.
    $proposta = $chatList->proposta ?? new \stdClass();
    if (!data_get($proposta, 'onboard') && $instrutor_id !== 348) {
      $proposta->onboard  = Aluno::find($aluno_id)?->onboard;
      $vTaxaApp           = InstrutorRepository::getById($instrutor_id)?->vTaxaApp ?: 10;
      $proposta->km_real  = InstrutorRepository::getDistanceAluno($aluno_id, $instrutor_id);
      $proposta->vTaxaApp = $vTaxaApp;
      $chatList->proposta = $proposta;
      $chatList->save();
    }

    // Enviar para Telegram
    if ($chatList->threadId && !$oculto) {
      TelegramRepository::sendMessageToTopic($chatList->threadId, $req->conteudo, $de, $fone);
      // Em caso de proposta do instrutor, enviar em seguida o valor para a jenifinha
      if ($req->conteudo === 'Nova proposta enviada! Clique em Aceitar proposta e vamos iniciar sua jornada.') {
        $vBruto =  Helpers::N2(data_get($chatList, 'proposta.vBruto'));
        $vLiquido = Helpers::N2(data_get($chatList, 'proposta.vLiquido'));
        $vDirigir = Helpers::N2($vLiquido - $vBruto);
        TelegramRepository::sendMessageToTopic($chatList->threadId,
          'Valor instrutor: ' . $vBruto . ' - ' .
          'Valor aluno: ' . $vLiquido . ' - ' .
          'Valor Dirigir: ' . $vDirigir
          , $de, $fone);
      }
    }

    // ----- chatMsg
    $chatMsg                = new ChatMsg();
    $chatMsg->de            = $de;
    $chatMsg->aluno_id      = $aluno_id;
    $chatMsg->instrutor_id  = $instrutor_id;
    $chatMsg->conteudo      = $req->conteudo;
    $chatMsg->threadId      = $chatList->threadId;
    $chatMsg->lidoAluno     = $de === 'Aluno';
    $chatMsg->lidoInstrutor = $de === 'Instrutor';
    $chatMsg->oculto        = $oculto;
    $chatMsg->save();

    // Notification
    $receiver_type = $de === 'Aluno' ? 'Instrutor' : 'Aluno';
    $receiver_id   = $de === 'Aluno' ? $instrutor_id : $aluno_id;
    NotificationRepository::newChatMsg($receiver_type, $receiver_id);

    return $this->response($chatList->hash);
  }

  private function contemContatoPessoal($texto)
  {
    $texto = mb_strtolower($texto);

    // Remove acentos (ex: "número" vira "numero")
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

    // 1. BLOQUEIO POR QUANTIDADE DE NÚMEROS NA MENSAGEM
    // Conta todos os dígitos presentes na mensagem, estejam eles juntos ou separados.
    // Ex: "Meu número é 11999999999" -> bloqueia
    // Ex: "Tenho 2 gatos e 1 cachorro" -> NÃO bloqueia
    // Ex: "Tenho 2 gatos, 1 cachorro e 3 peixes" -> bloqueia
    preg_match_all('/\d/', $texto, $matches);
    if (count($matches[0]) > 1) return true;

    // 2. BUSCA POR NÚMEROS POR EXTENSO
    $regexNumerosExtenso = '/\b(zero|um|dois|tres|quatro|cinco|seis|sete|oito|nove)\b/i';
    if (preg_match_all($regexNumerosExtenso, $texto, $matches)) {
        // Atenção: Frases como "Atendo de um a dois clientes" vão acionar esse bloqueio.
        if (count($matches[0]) >= 2) {
            return true;
        }
    }

    // 3. E-MAILS
    $padraoEmail = '/\b[a-z0-9._%+\-]+@[a-z0-9\-]+(\.[a-z0-9\-]+)*\.[a-z]{2,}\b/i';
    if (preg_match($padraoEmail, $texto)) {
        return true;
    }

    // 4. USUÁRIO DE REDE SOCIAL
    // Ex.: @joao, @joao.silva, @joao_123
    $padraoArroba = '/(?<!\w)@[a-z0-9][a-z0-9._]{1,29}\b/i';
    if (preg_match($padraoArroba, $texto)) {
      return true;
    }
    // Ex.: ig: joao, insta: joao, fb: joao, face: joao
    $padraoRedeSocial = '/\b(?:ig|insta|instagram|fb|face|facebook)\s*:\s*[a-z0-9][a-z0-9._]{1,29}\b/i';
    if (preg_match($padraoRedeSocial, $texto)) {
      return true;
    }

    // 5. PALAVRAS-CHAVE SUSPEITAS
    $palavrasSuspeitas = [
        '/\bbrasil\b/',
        '/\bwhatsapp\b/',
        '/\bwpp\b/',
        '/\bctt\b/',
        '/\bcpf\b/',
        '/\binstagram\b/',
        '/\bnome\b/',
        '/\bme chamo\b/',
        '/\bnúmero\b/',
        '/\bnumero\b/',
        '/\blicença\b/',
        '/\blicenca\b/',
        '/\bladv\b/',
        '/\binsta\b/',
        '/\bcelular\b/',
        '/\bwhats\b/',
        '/\bwhts\b/',
        '/\bwppp\b/',
        '/\bzap\b/',
        '/\bfacebook\b/',
        '/\bface\b/',
        '/\bcontato\b/',
        '/\bcontatar\b/',
        '/\bno ig\b/',
        '/\bno fb\b/',
        '/\bseu ig\b/',
        '/\brede social\b/',
        '/\bredes sociais\b/',
        '/\bseu fb\b/',
        '/\brua\b/',
        '/\bendereco\b/',
        '/\bwww\b/',
        '/\bhttps\b/',
        '/\bhttps\b/',
        '/\btelegram\b/'
    ];

    foreach ($palavrasSuspeitas as $regex) {
        if (preg_match($regex, $texto)) {
            return true;
        }
    }

    return false;
  }

}
