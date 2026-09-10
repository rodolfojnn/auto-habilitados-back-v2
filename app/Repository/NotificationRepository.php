<?php

namespace App\Repository;

use App\Http\Controllers\AlunoRepository;
use App\Http\Controllers\InstrutorRepository;
use App\Models\Aluno;
use App\Models\PushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationRepository {

  /**
   * Envia um push segmentando por plataforma.
   *
   * - android -> FCM (FirebaseRepository)
   * - ios     -> APNs (ApnRepository)
   *
   * IMPORTANTE: cada serviço remove do banco os tokens que falham
   * (NotFound no FCM / 410 + BadDeviceToken no APNs). Por isso NUNCA
   * devemos mandar um token iOS para o FCM nem um Android para o APNs,
   * senão o token é apagado por engano.
   *
   * @param \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection $pushTokens  registros PushToken
   */
  public static function enviaPorPlataforma($pushTokens, string $titulo, string $mensagem, array $data = [])
  {
    $pushTokens = collect($pushTokens);

    $androidTokens = $pushTokens->where('platform', 'android')->pluck('token')->filter()->values()->toArray();
    if (!empty($androidTokens)) {
      FirebaseRepository::sendNotificationTokens($androidTokens, $titulo, $mensagem, $data);
    }

    $iosTokens = $pushTokens->where('platform', 'ios')->pluck('token')->filter()->values()->toArray();
    if (!empty($iosTokens)) {
      ApnRepository::sendNotificationTokens($iosTokens, $titulo, $mensagem, $data);
    }
  }

  public static function newChatMsg($receiver_type, $receiver_id) {
    Log::channel('email')->info(__METHOD__ . ' - ' . $receiver_type . ' - ' . $receiver_id);
    $receiver_type = strtolower($receiver_type);

    // Ignora o email para o admin
    if ($receiver_type === 'instrutor' && $receiver_id === 348) return;

    try {

      // 1. PUSH NOTIFICATION
      $tokens = PushToken::where('owner_type', $receiver_type)->where('owner_id', $receiver_id)->get();
      if ($tokens->isNotEmpty()) {
        self::enviaPorPlataforma($tokens, 'Nova mensagem', 'Você recebeu uma nova mensagem no Dirigir Agora');
        return;
      }

      // // 2. EMAIL – throttle de 2 horas
      // $cacheKey = __METHOD__ . ":{$receiver_type}:{$receiver_id}";
      // if (Cache::has($cacheKey)) return;
      // $email = $receiver_type === 'aluno' ? AlunoRepository::getById($receiver_id)->email : InstrutorRepository::getById($receiver_id)->email;
      // if (!$email) return;

      // Log::channel('email')->info(__METHOD__ . ' - ' . $email);
      // Mail::mailer('brevo')->send('mail.chat-mensagem', ['email' => $email], function ($m) use ($email) {
      //   $m->to($email)->subject('Você recebeu uma mensagem no DirigirAgora');
      // });
      // Cache::put($cacheKey, true, now()->addHours(2));
    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }

  }

  public static function newReview($instrutor_id) {

    try {

      // 1. PUSH NOTIFICATION
      $tokens = PushToken::where('owner_type', 'instrutor')->where('owner_id', $instrutor_id)->get();
      if ($tokens->isNotEmpty()) {
        self::enviaPorPlataforma($tokens, 'Nova avaliação', 'Você recebeu uma nova avaliação no Dirigir Agora');
        return;
      }

      // 2. EMAIL – throttle de 2 horas
      $cacheKey = __METHOD__ . ":instrutor:{$instrutor_id}";
      if (Cache::has($cacheKey)) return;
      $email = InstrutorRepository::getById($instrutor_id)->email;
      if (!$email) return;
      Log::channel('email')->info(__METHOD__ . ' - ' . $email);
      Mail::mailer('brevo')->send('mail.review-mensagem', ['email' => $email], function ($m) use ($email) {
        $m->to($email)->subject('Você recebeu uma nova avaliação no DirigirAgora');
      });
      Cache::put($cacheKey, true, now()->addHours(2));
    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }

  }

  public static function newAula($instrutor_id) {

    try {

      // 1. PUSH NOTIFICATION
      $tokens = PushToken::where('owner_type', 'instrutor')->where('owner_id', $instrutor_id)->get();
      if ($tokens->isNotEmpty()) {
        self::enviaPorPlataforma($tokens, 'Nova aula', 'Você vendeu uma nova aula no Dirigir Agora. Acesse o menu "Aulas" e confira!');
        return;
      }

      // 2. EMAIL
      $email = InstrutorRepository::getById($instrutor_id)->email;
      if (!$email) return;
      Log::channel('email')->info(__METHOD__ . ' - ' . $email);
      Mail::mailer('brevo')->send('mail.instrutor-nova-aula', ['email' => $email], function ($m) use ($email) {
        $m->to($email)->subject('Você vendeu uma nova aula no Dirigir Agora!');
      });
    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }

  }

  public static function newAlunoRegiao($lat, $lng, $km) {

    try {
      $instrutores = InstrutorRepository::proximosLinear($lat, $lng, $km);
      if (!$instrutores->count()) return;
      $tokens = PushToken::where('owner_type', 'instrutor')
        ->whereIn('owner_id', $instrutores->pluck('id'))
        ->get();
      if (!$tokens->count()) return;
      self::enviaPorPlataforma($tokens, 'Novo aluno cadastrado', 'Um novo aluno se cadastrou buscando aulas na sua região!');

      // DEBUG
      // $tokens = ['drzlYJlHQF-CnxMkzpGHHC:APA91bERV_fLnjbPgvuy7R-5BVeb08BJN6XCRjuP1Qy8VFMvsEtaL33MCPqOoAKFwrQ4uDHmyFME5E9dCKKCqS6iFXs9Iv8TWZVSIEaCOAIYqPai5Sk9Dso'];
      // FirebaseRepository::sendNotificationTokens($tokens, 'Novo aluno cadastrado', 'Um novo aluno se cadastrou buscando aulas na sua região!');

    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }

  }

  public static function newInstrutorRegiao($lat, $lng, $km) {

    try {
      $alunos = AlunoRepository::proximosLinear($lat, $lng, $km);
      if (!$alunos->count()) return;

      // 1. PUSH NOTIFICATION
      $tokens = PushToken::where('owner_type', 'aluno')->whereIn('owner_id', $alunos->pluck('id'))->get();
      if ($tokens->isNotEmpty()) {
        self::enviaPorPlataforma($tokens, 'Novo instrutor cadastrado', 'Um novo instrutor se cadastrou oferecendo aulas na sua região!');
      }

      // 2. EMAIL
      $alunosSemToken = $alunos
        ->whereNotIn('id', $tokens->pluck('owner_id'))
        ->unique('id')
        ->sortByDesc('created_at')
        ->take(10)
        ->pluck('email')
        ->filter()
        ->values()
        ->toArray();

      if (count($alunosSemToken) > 0) {
        Log::channel('email')->info(__METHOD__ . ' - Enviando email para: ' . implode(', ', $alunosSemToken));
        Mail::mailer('zeptomail')->send(
          'mail.novo-instrutor',
          ['email' => null],
          function ($m) use ($alunosSemToken) {
              $m->to('comercial@dirigiragora.com.br')
                ->bcc($alunosSemToken)
                ->subject('Novo instrutor em sua região!');
          }
        );
      }

    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }

  }

  public static function alunoAvaliar($aluno_id) {
    $tokens = PushToken::where('owner_type', 'aluno')->where('owner_id', $aluno_id)->get();
    if ($tokens->count() === 0) return;

    self::enviaPorPlataforma($tokens, 'Lembrete', 'Avalie para concluir a validação de suas aulas práticas.', ['pagina' => 'my-classes']);

  }

  public static function alunoRejeitaProposta(int $aluno_id, int $instrutor_id) {
    $aluno = AlunoRepository::getById($aluno_id);
    if (!$aluno) return;
    $tokens = PushToken::where('owner_type', 'instrutor')->where('owner_id', $instrutor_id)->get();
    if ($tokens->count() === 0) return;

    $primerNombre = explode(' ', trim($aluno->nome))[0] ?? $aluno->nome;
    $title = "{$primerNombre} recusou sua proposta";
    $body = 'Você pode enviar outra oferta ou continuar conversando para ajustarem.';

    self::enviaPorPlataforma($tokens, $title, $body);

  }

}
