<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Aula;
use App\Models\ChatList;
use App\Models\ChatMsg;
use App\Models\Instrutor;
use App\Models\Pagamento;
use App\Repository\MailRepository;
use App\Repository\NotificationRepository;

class AulaRepository {

  public static function insertAula($pagamento_id) {
    $aula                 = Aula::firstOrNew(['pagamento_id' => $pagamento_id]);
    if ($aula->exists) return;

    $pagamento = Pagamento::find($pagamento_id);
    $aula->instrutor_id   = $pagamento->instrutor_id;
    $aula->aluno_id       = $pagamento->aluno_id;
    $aula->status         = 'Em Andamento';
    $aula->categoria      = $pagamento->categoria;
    $aula->pacote         = $pagamento->pacote;
    $aula->rent           = $pagamento->rent;
    $aula->kmDesloc       = $pagamento->kmDesloc;
    $aula->save();

    // Notificação
    NotificationRepository::newAula($aula->instrutor_id);

    // Verifica se é uma compra via chat, se for adiciona a mensagem do aluno > instrutor
    if (data_get($pagamento, 'valoresJ.chatHash')) {
      $chatList = ChatList::firstOrNew(['hash' => data_get($pagamento, 'valoresJ.chatHash')]);
      if ($chatList) {

        // Remover valores do chatList para que o instrutor posso fazer uma nova proposta
        $proposta = $chatList->proposta ?? new \stdClass();
        unset($proposta->vBruto);
        unset($proposta->vLiquido);
        $chatList->proposta = $proposta;
        $chatList->save();

        // Insere mensagem pós CTA
        $chatMsg                = new ChatMsg();
        $chatMsg->de            = 'Aluno';
        $chatMsg->aluno_id      = $chatList->aluno_id;
        $chatMsg->instrutor_id  = $chatList->instrutor_id;
        $chatMsg->conteudo      = 'Parabéns! Compra efetuada com sucesso. Acesse o menu "Aulas".';
        $chatMsg->threadId      = $chatList->threadId;
        $chatMsg->lidoAluno     = 1;
        $chatMsg->lidoInstrutor = 0;
        $chatMsg->oculto        = 0;
        $chatMsg->exclusivo     = 0;
        $chatMsg->save();
      }
    }

    // Mail manager
    $instrutor = Instrutor::find($aula->instrutor_id);
    $aluno     = Aluno::find($aula->aluno_id);
    MailRepository::msgManager(
      "<strong>Instrutor:</strong> {$aula->instrutor_id}<br>" .
      "<strong>Aluno:</strong> {$aula->aluno_id}<br>" .
      "<strong>Categoria:</strong> {$aula->categoria}<br>" .
      "<strong>Pacote:</strong> {$aula->pacote}<br>" .
      "<strong>Aluguel:</strong> {$aula->rent}<br>" .
      "<strong>kmDesloc:</strong> {$aula->kmDesloc}<br>" .
      "<strong>Valor:</strong> R$ {$pagamento->vPago}<br>" .
      "<strong>Forma de Pgto:</strong> {$pagamento->forma}<br>" .
      "<strong>Instrutor Whats:</strong> {$instrutor->fone1}<br>" .
      "<strong>Aluno CEP:</strong> {$aluno->cep}<br>" .
      "<strong>Aluno Whats:</strong> {$aluno->fone1}",
      '🚀 Nova Venda de Aula Instrutor ' . $aula->instrutor_id
    );
  }

}