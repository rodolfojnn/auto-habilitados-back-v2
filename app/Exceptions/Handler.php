<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PDOException;
use Throwable;

class Handler extends ExceptionHandler
{
  /**
   * The list of the inputs that are never flashed to the session on validation exceptions.
   *
   * @var array<int, string>
   */
  protected $dontFlash = [
    'current_password',
    'password',
    'password_confirmation',
  ];

  public function report(Throwable $exception)
  {

    // Mensagem específica que você não quer com stack trace
    if (
        $exception instanceof \Exception &&
        $exception->getMessage() === 'CEP não encontrado. Verifique o CEP e tente novamente. Caso o erro persista, entre em contato com nossa equipe de suporte.'
    ) {
        // Loga só a mensagem, sem stack trace
        Log::warning($exception->getMessage());
        return;
    }

    // Envia o email
    // if (!config('app.debug')) {
    //   try {
    //     Mail::mailer('sendpulse')->raw(
    //       "Erro na aplicação\n\n"
    //         . "Mensagem: {$exception->getMessage()}\n"
    //         . "Arquivo: {$exception->getFile()}\n"
    //         . "Linha: {$exception->getLine()}\n\n"
    //         . "Stacktrace:\n{$exception->getTraceAsString()}",
    //       function ($m) use ($exception) {
    //         $m->to('rodolfojnn@gmail.com')
    //           ->subject('Erro Dirigir Agora ' . date('Y-m-d H:i:s'));
    //       }
    //     );
    //   } catch (\Throwable $e) {
    //     Log::error('Falha ao enviar e-mail de erro', [
    //       'error' => $e->getMessage(),
    //     ]);
    //   }
    // }

    return parent::report($exception);

    // if (env('APP_DEBUG')) {
    //   return parent::report($exception);
    // }
    // try {
    //   // Monta erro
    //   $errorMessage = 'Message: ' . $exception->getMessage() . "\n";
    //   $errorMessage .= 'File: ' . $exception->getFile() . "\n";
    //   $errorMessage .= 'Line: ' . $exception->getLine() . "\n";
    //   $errorMessage .= '[stacktrace]' . "\n" . $exception->getTraceAsString() . "\n";

    //   // Request
    //   $stdout = fopen('php://stdout', 'w');
    //   fwrite($stdout, $errorMessage);
    //   fclose($stdout);
    // } catch (\Throwable $th) {
    //   // dd($th);
    // }
  }

  /**
   * Register the exception handling callbacks for the application.
   */
  public function register(): void
  {
    $this->reportable(function (Throwable $e) {
      // header('Access-Control-Allow-Origin: *');
      // header('Access-Control-Allow-Methods: *');
      // header('Access-Control-Allow-Headers: *');
    });
  }

  public function render($request, Throwable $exception): JsonResponse|\Symfony\Component\HttpFoundation\Response
  {

    // Quando o debug estiver DESATIVADO
    if (!config('app.debug')) {
      // Se for exceção de banco, NÃO reportar
      if ($exception instanceof QueryException || $exception instanceof PDOException) {
        return response()->json(['status' => false, 'data' => 'Erro interno da base de dados.'], 500);
      }
    }

    // Se for uma requisição que espera JSON (ex: API)
    if ($request->expectsJson()) {
      return response()->json([
        'status'  => false,
        'data' => $exception->getMessage(),
      ]);
    }

    // Para requisições web normais, mantém o comportamento padrão
    return parent::render($request, $exception);
  }

}
