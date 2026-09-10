<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AlunoRepository;
use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthAdminMiddleware
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
      $jwtKey = config('app.JWT_SECRET_ADMIN');
      $decoded = JWT::decode($authHeader, new Key($jwtKey, 'HS256'));
      if (!isset($decoded->admin_id)) throw new Exception('Admin inválido');

      // Aluno req
      $req->admin_id       = $decoded->admin_id;

     } catch (Exception $e) {
      return response($e->getMessage(), 401);
    }
    return $next($req);
  }
}
