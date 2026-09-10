<?php

namespace App\Repository;

use App\Models\PushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseRepository
{

  public static function register(Request $req)
  {
    $pushToken = PushToken::firstOrNew(['token' => $req->token]);
    $pushToken->token        = $req->token;
    $pushToken->owner_type   = $req->aluno ? 'aluno' : 'instrutor';
    $pushToken->owner_id     = $req->aluno ? $req->aluno->id : $req->instrutor->id;
    $pushToken->platform     = $req->platform;
    $pushToken->last_seen_at = now();
    $pushToken->save();
  }

  public static function sendNotificationToken(string $token, string $titulo, string $mensagem, array $data = [])
  {
    Log::channel('email')->info(__METHOD__ . ' - ' . $token);

    try {
      $factory = (new Factory)->withServiceAccount(storage_path('firebase/firebase.json'));
      $messaging = $factory->createMessaging();

      try {
        $message = CloudMessage::new()
          ->toToken($token)
          ->withData($data)
          ->withNotification(
            Notification::create($titulo, $mensagem)
          );

        $messaging->send($message);
      } catch (NotFound $e) {
        // AQUI é o ÚNICO lugar onde o token deve ser removido
        PushToken::where('token', $token)->delete();
        Log::channel('email')->warning('Token FCM removido (NotFound)', ['token' => $token]);
      } catch (InvalidMessage $e) {
        // payload inválido (erro seu, não do usuário)
        Log::channel('email')->error('Payload inválido FCM', ['token' => $token, 'error' => $e->getMessage()]);
      } catch (MessagingException $e) {
        // erros do FCM (quota, auth, indisponível, etc)
        Log::channel('email')->warning('Erro Messaging FCM', ['token' => $token, 'error' => $e->getMessage()]);
      } catch (FirebaseException $e) {
        // erro genérico do SDK
        Log::channel('email')->error('Erro Firebase SDK', ['token' => $token, 'error' => $e->getMessage()]);
      }
    } catch (\Throwable $e) {
      Log::channel('email')->error('Erro geral no envio FCM', ['error' => $e->getMessage()]);
    }
  }


  public static function sendNotificationTokens(array $tokens, string $titulo, string $mensagem, array $data = [])
  {
    Log::channel('email')->info(__METHOD__ . ' - ' . count($tokens) . ' ' . $mensagem);

    if (empty($tokens)) return;

    try {
      $factory = (new Factory)->withServiceAccount(storage_path('firebase/firebase.json'));

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
          PushToken::where('token', $invalidToken)->delete();
          Log::channel('email')->warning('Token FCM removido (NotFound)', ['token' => $invalidToken]);
        }
      }

      Log::channel('email')->info('Push multicast', ['success' => $report->successes()->count(), 'failure' => $report->failures()->count()]);

      return ['success' => $report->successes()->count(), 'failure' => $report->failures()->count()];

    } catch (\Throwable $e) {
      Log::channel('email')->error('Erro ao enviar push multicast', ['error' => $e->getMessage()]);
    }
  }
}
