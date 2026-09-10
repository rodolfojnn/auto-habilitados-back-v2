<?php

namespace App\Http\Controllers;

use App\Models\Aula;
use App\Models\InstrutorReview;
use App\Repository\NotificationRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AulaController extends Controller
{

  public function getListInstrutor(Request $req) {
    $aulas = Aula::with('aluno', 'pagamento')
      ->where('instrutor_id', $req->instrutor->id)
      ->orderBy('id', 'DESC')
      ->get();
    return $this->response($aulas);
  }

  public function getListAluno(Request $req) {
    $aulas = Aula::with('instrutor', 'pagamento')
      ->where('aluno_id', $req->aluno->id)
      ->orderBy('id', 'DESC')
      ->get();

    $reviews = InstrutorReview::distinct()
      ->select('aluno_id', 'instrutor_id')
      ->where('aluno_id', $req->aluno->id)
      ->get();

    $aulas->each(function ($aula) use ($reviews) {
      $aula->instrutorReview = $reviews->first(function ($review) use ($aula) {
        return $review->aluno_id == $aula->aluno_id
          && $review->instrutor_id == $aula->instrutor_id;
      });
    });

    return $this->response($aulas);
  }

  public function concluir(Request $req) {
    $aula = Aula::where('instrutor_id', $req->instrutor->id)->where('id', $req->id)->first();

    // Validação
    if (!$aula) return $this->response('Aula não encontrada', false);
    if ($aula->finished_at) return $this->response('Aula já concluída', false);

    $aula->finished_at  = Carbon::now();
    $aula->status       = 'Concluída';
    $aula->save();

    // Push pro aluno avaliar
    NotificationRepository::alunoAvaliar($aula->aluno_id);

    return $this->response($aula);
  }

}
