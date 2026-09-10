<?php

namespace App\Http\Controllers;

use App\Models\ChatList;
use App\Models\ChatMsg;
use App\Repository\NotificationRepository;
use App\Repository\TelegramRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class ChatRepository
{

  /**
   * Método utilitário para enviar mensagem no chat do sistema.
   * Não dispara exceções, apenas retorna true ou false.
   *
   * @param string $de          'Aluno' ou 'Instrutor'
   * @param int    $instrutor_id
   * @param int    $aluno_id
   * @param string $mensagem
   * @return bool
   */
  public static function sendMessage(string $de, int $instrutor_id, int $aluno_id, string $mensagem): bool
  {
    try {
      // Validações
      if (!in_array($de, ['Aluno', 'Instrutor'])) return false;
      if (!$instrutor_id) return false;
      if (!$aluno_id) return false;
      if (!$mensagem) return false;

      // chatList – cria se não existir
      $chatList = ChatList::firstOrNew(['aluno_id' => $aluno_id, 'instrutor_id' => $instrutor_id]);
      if (!$chatList->id) {
        $chatList->threadId = TelegramRepository::createChatTopic($instrutor_id, $aluno_id);
        $chatList->hash     = (string) Str::ulid();
      }

      $chatList->ultimoDe           = $de;
      $chatList->ultimaMensagem     = $mensagem;
      $chatList->ultimaMensagemAt   = now();
      if ($de === 'Aluno') $chatList->unreadInstrutor += 1;
      if ($de === 'Instrutor') $chatList->unreadAluno += 1;
      $chatList->save();

      // fone para o Telegram
      $fone = '';
      if ($de === 'Aluno') {
        $aluno = AlunoRepository::getById($aluno_id);
        $fone  = $aluno ? $aluno->fone1 : '';
      }
      if ($de === 'Instrutor') {
        $instrutor = InstrutorRepository::getById($instrutor_id);
        $fone      = $instrutor ? $instrutor->fone1 : '';
      }

      // Enviar para Telegram
      if ($chatList->threadId) {
        TelegramRepository::sendMessageToTopic($chatList->threadId, $mensagem, $de, $fone);
      }

      // chatMsg
      $chatMsg                = new ChatMsg();
      $chatMsg->de            = $de;
      $chatMsg->aluno_id      = $aluno_id;
      $chatMsg->instrutor_id  = $instrutor_id;
      $chatMsg->conteudo      = $mensagem;
      $chatMsg->threadId      = $chatList->threadId;
      $chatMsg->lidoAluno     = $de === 'Aluno';
      $chatMsg->lidoInstrutor = $de === 'Instrutor';
      $chatMsg->oculto        = false;
      $chatMsg->save();

      return true;
    } catch (\Throwable $e) {
      Log::error(__METHOD__ . ': ' . $e->getMessage());
      return false;
    }
  }

}
