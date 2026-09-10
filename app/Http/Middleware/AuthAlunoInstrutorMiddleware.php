<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AlunoRepository;
use App\Http\Controllers\InstrutorRepository;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthAlunoInstrutorMiddleware
{
  public function handle($req, Closure $next)
  {

    try {
      $authHeader = $req->header('Authorization');
      if (!$authHeader) {
        throw new Exception('Authorization header inválido');
      }

      /*
      * ===================
      * TENTA VALIDAR ALUNO
      * ===================
      */
      try {
        $jwtKeyAluno = config('app.JWT_SECRET_ALUNO');
        $decoded = JWT::decode($authHeader, new Key($jwtKeyAluno, 'HS256'));

        if (isset($decoded->aluno_id)) {
          $req->auth_type = 'aluno';
          $req->aluno     = AlunoRepository::getById($decoded->aluno_id);

          // Usuário banido
          if ($req->aluno->status === 'X') return response('Não foi possível autenticar o usuário', 401);

          return $next($req);
        }
      } catch (Exception $e) {
        // ignora e tenta instrutor
      }

      /*
      * =======================
      * TENTA VALIDAR INSTRUTOR
      * =======================
      */
      try {
        $jwtKeyInstrutor = config('app.JWT_SECRET_INSTRUTOR');
        $decoded = JWT::decode($authHeader, new Key($jwtKeyInstrutor, 'HS256'));

        if (isset($decoded->instrutor_id)) {
          $req->auth_type = 'instrutor';
          $req->instrutor = InstrutorRepository::getById($decoded->instrutor_id);

          // Usuário banido
          if ($req->instrutor->status === 'X') return response('Não foi possível autenticar o usuário', 401);

          return $next($req);
        }
      } catch (Exception $e) {
        // ignora
      }

      throw new Exception('Token inválido');
    } catch (Exception $e) {
      return response($e->getMessage(), 401);
    }
  }
}
