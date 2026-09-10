<?php

namespace App\Repository;

use App\Models\ChatMsg;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterRepository
{

  // http://localhost/jenifer/auto-habilitados-back/public/validate-chat
  public static function validateChat() {
    // Pega as últimas mensagens do último minuto
    $chats = ChatMsg::select('created_at', 'threadId', 'de', 'aluno_id', 'instrutor_id', 'conteudo')
      ->where('created_at', '>', Carbon::now()->subMinutes(60))
      ->where('de', '!=', 'Admin')
      ->orderBy('created_at', 'asc')
      ->get();
    $chats = $chats->groupBy('threadId');

    $resultados = [];

    // Percorrer todas as conversas e gerar em texto corrido
    foreach ($chats as $threadId => $mensagens) {
      $linhas = [];
      foreach ($mensagens as $msg) {
        $quem = $msg->de === 'Aluno' ? 'Aluno' : 'Instrutor';
        $linhas[] = "$quem: {$msg->conteudo}";
      }
      $conversa = implode("\n", $linhas);

      $prompt = "Analise a conversa abaixo entre Aluno e Instrutor sobre auto escola.

Verifique se há TENTATIVA de:
1. Troca de contato pessoal (WhatsApp, Telegram, Instagram, Facebook)
2. Envio de telefone, email ou endereço
3. Qualquer tentativa de burlar a segurança do app combinando algo fora da plataforma

Responda APENAS com a palavra \"true\" se houver violação de segurança, ou \"false\" se estiver tudo ok (conversa normal sobre auto escola, aulas, agendamentos, etc). Não adicione explicações.

Conversa:
{$conversa}";

      try {
        $response = Http::withHeaders([
          'Authorization' => 'Bearer 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',
          'Content-Type' => 'application/json',
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
          'model' => 'openrouter/free',
          'messages' => [
            ['role' => 'user', 'content' => $prompt]
          ],
        ]);

        $data = $response->json();
        $textoGerado = strtolower(trim($data['choices'][0]['message']['content'] ?? ''));

        $temViolacao = $textoGerado === 'true' || str_contains($textoGerado, 'true');

        $resultados[$threadId] = [
          'conversa' => $conversa,
          'violacao' => $temViolacao,
        ];

        if ($temViolacao) {
          Log::warning("[VALIDATE-CHAT] Violação detectada na thread {$threadId}: {$conversa}");
        }
      } catch (\Exception $e) {
        Log::error("[VALIDATE-CHAT] Erro ao chamar IA para thread {$threadId}: " . $e->getMessage());
        $resultados[$threadId] = [
          'conversa' => $conversa,
          'violacao' => false,
          'erro' => $e->getMessage(),
        ];
      }
    }

    return $resultados;
  }
}