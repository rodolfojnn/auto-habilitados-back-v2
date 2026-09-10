<?php

namespace App\Repository;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailRepository {

  public static function msgManager($body, $subject = 'Mensagem do sistema') {
    try {
      Mail::html($body, function ($m) use ($subject) { $m->to('comercial@dirigiragora.com.br')->subject($subject); });
    } catch (\Throwable $th) {}
  }

  public static function sendToken($email, $token) {
    try {
      Log::channel('email')->info(__METHOD__ . ' - ' . $email . ' - ' . $token);
      Mail::send('mail.aluno-token', ['email' => $email, 'token' => $token], function ($m) use ($email, $token) {
        $m->to($email)->subject('Código DIRIGIR AGORA: ' . $token);
      });
    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }
  }

  public static function sendAlunoBoasVindas($email) {
    try {
      return Mail::send('mail.aluno-app-cnh-brasil', ['email' => $email], function ($m) use ($email) {
        $m->to($email)->subject('Inicie o processo de sua CNH do Brasil!');
      });
    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }
  }

  public static function sendNovoChat($email) {
    try {
      Log::channel('email')->info(__METHOD__ . ' - ' . $email);
      return Mail::mailer('brevo')->send('mail.chat-mensagem', ['email' => $email], function ($m) use ($email) {
        $m->to($email)->subject('Você recebeu uma mensagem no DirigirAgora');
      });
    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }
  }

}
