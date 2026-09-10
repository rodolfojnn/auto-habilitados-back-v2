<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AlunoRepository;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthAlunoMiddleware
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
  public function handle($req, Closure $next)
  {
    try {
      $authHeader = $req->header('Authorization');
      if (!$authHeader) throw new Exception('Authorization header inválido');
      $jwtKey = config('app.JWT_SECRET_ALUNO');
      $decoded = JWT::decode($authHeader, new Key($jwtKey, 'HS256'));
      if (!isset($decoded->aluno_id)) throw new Exception('Aluno inválido');

      // Aluno req
      $req->aluno       = AlunoRepository::getById($decoded->aluno_id);

      // Usuário banido
      if ($req->aluno->status === 'X') return response('Não foi possível autenticar o usuário', 401);

     } catch (Exception $e) {
      return response($e->getMessage(), 401);
    }
    return $next($req);
  }
}
