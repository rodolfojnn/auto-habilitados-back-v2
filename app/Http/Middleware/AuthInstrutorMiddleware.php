<?php

namespace App\Http\Middleware;

use App\Http\Controllers\InstrutorRepository;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthInstrutorMiddleware
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
      $jwtKey = config('app.JWT_SECRET_INSTRUTOR');
      $decoded = JWT::decode($authHeader, new Key($jwtKey, 'HS256'));
      if (!isset($decoded->instrutor_id)) throw new Exception('Instrutor inválido');

      // Instrutor req
      $req->instrutor       = InstrutorRepository::getById($decoded->instrutor_id);

      // Usuário banido
      if ($req->instrutor->status === 'X') return response('Não foi possível autenticar o usuário', 401);

     } catch (Exception $e) {
      return response($e->getMessage(), 401);
    }
    return $next($req);
  }
}
