<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AlunoController;
use App\Http\Controllers\AulaController;
use App\Http\Controllers\AulaRepository;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstrutorController;
use App\Http\Controllers\PagamentoController;
use App\Http\Controllers\SimuladoController;
use App\Repository\AsaasRepository;
use App\Repository\CNHBrasilRepository;
use App\Repository\FirebaseRepository;
use App\Repository\TelegramRepository;
use Illuminate\Support\Facades\Route;

Route::post('/asaas/webhook',                   [AsaasRepository::class, 'webhook']);
Route::post('/telegram/webhook',                [TelegramRepository::class, 'webhook']);
Route::post('/aluno/register',                  [AlunoController::class, 'register']);
Route::post('/aluno/login',                     [AlunoController::class, 'login']);
Route::get('/aluno/lista',                      [AlunoController::class, 'lista']);
Route::post('/aluno/promocao',                  [AlunoController::class, 'promocao']);
Route::post('/instrutor/register',              [InstrutorController::class, 'register']);
Route::post('/instrutor/registerV2',            [InstrutorController::class, 'registerV2']);
Route::get('/instrutor/lista',                  [InstrutorController::class, 'lista']);
Route::get('/instrutor/cnhbrasil/{uf}',         [InstrutorController::class, 'cnhbrasil']);
Route::post('/instrutor/login',                 [InstrutorController::class, 'login']);
Route::post('/instrutor/loginJwt',              [InstrutorController::class, 'loginJwt']);
Route::get('/instrutor/dados/{numero}',         [InstrutorController::class, 'dados']);
Route::get('/instrutor/sitemap',                [InstrutorController::class, 'sitemap']);
Route::post('/instrutor/redefinirSenha',        [InstrutorController::class, 'redefinirSenha']);
Route::post('/instrutor/validarTokenRedef',     [InstrutorController::class, 'validarTokenRedef']);
Route::post('/instrutor/hash',                  [InstrutorController::class, 'hash']);
Route::post('/instrutor/hashReviews',           [InstrutorController::class, 'hashReviews']);
Route::post('/admin/login',                     [AdminController::class, 'login']);


// ▄▀▀ █ █▄ ▄█ █ █ █   ▄▀▄ █▀▄ ▄▀▄
// ▄█▀ █ █ ▀ █ ▀▄█ █▄▄ █▀█ █▄▀ ▀▄▀
Route::post('/simulado/newLead',                [SimuladoController::class, 'newLead']);
Route::post('/simulado/pushToken',              [SimuladoController::class, 'pushToken']);
Route::post('/simulado/newAutomacaoLead',       [SimuladoController::class, 'newAutomacaoLead']);
Route::post('/simulado/postRenach',             [SimuladoController::class, 'postRenach']);
Route::get('/simulado/getRenach',               [SimuladoController::class, 'getRenach']);
Route::post('/simulado/chat/chatList',          [SimuladoController::class, 'chat_chatList']);
Route::post('/simulado/chat/chatMsgs',          [SimuladoController::class, 'chat_chatMsgs']);
Route::post('/simulado/chat/sendMsg',           [SimuladoController::class, 'chat_sendMsg']);


// ▄▀▄ █   █ █ █▄ █ ▄▀▄       █ █▄ █ ▄▀▀ ▀█▀ █▀▄ █ █ ▀█▀ ▄▀▄ █▀▄
// █▀█ █▄▄ ▀▄█ █ ▀█ ▀▄▀       █ █ ▀█ ▄█▀  █  █▀▄ ▀▄█  █  ▀▄▀ █▀▄
Route::group(['middleware' => 'AuthAlunoInstrutorMiddleware'], function () {

  // Chat
  Route::get('/chat/chatList',                  [ChatController::class, 'chatList']);
  Route::post('/chat/chatMsgs',                 [ChatController::class, 'chatMsgs']);
  Route::post('/chat/postChatMessage',          [ChatController::class, 'postChatMessage']);

  // Push Notification
  Route::post('/pushTokens/register',           [FirebaseRepository::class, 'register']);

  // Instrutor
  Route::post('/instrutor/reviews',             [InstrutorController::class, 'reviews']);

});


// ▄▀▄ █   █ █ █▄ █ ▄▀▄
// █▀█ █▄▄ ▀▄█ █ ▀█ ▀▄▀
Route::group(['middleware' => 'AuthAlunoMiddleware'], function () {

  // Aluno
  Route::post('/aluno/update',                  [AlunoController::class, 'update']);
  Route::post('/aluno/validarToken',            [AlunoController::class, 'validarToken']);
  Route::post('/aluno/alunoSolicitacao',        [AlunoController::class, 'alunoSolicitacao']);
  Route::post('/aluno/updateJornada',           [AlunoController::class, 'updateJornada']);
  Route::post('/aluno/deletarConta',            [AlunoController::class, 'deletarConta']);
  Route::post('/aluno/updateOnboard',           [AlunoController::class, 'updateOnboard']);
  Route::post('/aluno/recusarProposta',         [AlunoController::class, 'recusarProposta']);

  // Aula
  Route::get('/aula/listAluno',                 [AulaController::class, 'getListAluno']);

  // Instrutor
  Route::post('/instrutor/proximos',            [InstrutorController::class, 'proximos']);
  Route::post('/instrutor/sendMessage',         [InstrutorController::class, 'sendMessage']);
  Route::post('/instrutor/sendReview',          [InstrutorController::class, 'sendReview']);
  Route::get('/instrutor/mapaGeral',            [InstrutorController::class, 'mapaGeral']);
  Route::post('/instrutor/proximoInstrutId',    [InstrutorController::class, 'proximoInstrutId']);

  // Pagamento
  Route::post('/pagamento/credito',             [PagamentoController::class, 'pagamentoCredito']);
  Route::post('/pagamento/creditoV2',           [PagamentoController::class, 'pagamentoCreditoV2']);
  Route::post('/pagamento/credito-chat',        [PagamentoController::class, 'pagamentoCreditoChat']);
  Route::post('/pagamento/pix',                 [PagamentoController::class, 'pagamentoPix']);
  Route::post('/pagamento/pixV2',               [PagamentoController::class, 'pagamentoPixV2']);
  Route::post('/pagamento/pix-chat',            [PagamentoController::class, 'pagamentoPixChat']);
  Route::get('/pagamento/status/{id}',          [PagamentoController::class, 'getPaymentStatus']);

});

// █ █▄ █ ▄▀▀ ▀█▀ █▀▄ █ █ ▀█▀ ▄▀▄ █▀▄
// █ █ ▀█ ▄█▀  █  █▀▄ ▀▄█  █  ▀▄▀ █▀▄

Route::group(['middleware' => 'AuthInstrutorMiddleware'], function () {

  // Aula
  Route::get('/aula/listInstrutor',             [AulaController::class, 'getListInstrutor']);
  Route::post('/aula/concluir',                 [AulaController::class, 'concluir']);

  // Home
  Route::post('/instrutor/logForms',            [HomeController::class, 'logForms']);

  // Instrutor
  Route::post('/instrutor/update',              [InstrutorController::class, 'update']);
  Route::post('/instrutor/updateSelo',          [InstrutorController::class, 'updateSelo']);
  Route::post('/instrutor/updateV2',            [InstrutorController::class, 'updateV2']);
  Route::post('/instrutor/updateSeloV2',        [InstrutorController::class, 'updateSeloV2']);
  Route::post('/instrutor/getFotosBase64',      [InstrutorController::class, 'getFotosBase64']);
  Route::post('/instrutor/validarToken',        [InstrutorController::class, 'validarToken']);
  Route::post('/instrutor/proximoAlunoId',      [InstrutorController::class, 'proximoAlunoId']);
  Route::get('/instrutor/alunosEncontrados',    [InstrutorController::class, 'alunosEncontrados']);
  Route::post('/instrutor/get',                 [InstrutorController::class, 'get']);
  Route::post('/instrutor/uploadFoto',          [InstrutorController::class, 'uploadFoto']);
  Route::post('/instrutor/deleteFoto',          [InstrutorController::class, 'deleteFoto']);
  Route::post('/instrutor/sendProposalChat',    [InstrutorController::class, 'sendProposalChat']);

  // Pagamento
  Route::post('/instrutor/getPagamentos',       [InstrutorController::class, 'getPagamentos']);

  // Reviews
  Route::post('/instrutor/getReviews',          [InstrutorController::class, 'getReviews']);

});


// // ▄▀▄ █▀▄ █▄ ▄█ █ █▄ █
// // █▀█ █▄▀ █ ▀ █ █ █ ▀█

Route::group(['middleware' => 'AuthAdminMiddleware'], function () {
  Route::post('/admin/aluno/buscar',                [AdminController::class, 'alunoBuscar']);
  Route::post('/admin/aluno/getKanban',             [AdminController::class, 'alunoGetKanban']);
  Route::post('/admin/aluno/statusUpdate',          [AdminController::class, 'alunoStatusUpdate']);
  Route::post('/admin/aluno/deletarConta',          [AdminController::class, 'alunoDeletarConta']);
  Route::post('/admin/config/limparCache',          [AdminController::class, 'configLimparCache']);
  Route::post('/admin/envio/emailPost',             [AdminController::class, 'envioEmailPost']);
  Route::post('/admin/envio/pushPost',              [AdminController::class, 'envioPushPost']);
  Route::post('/admin/instrutor/avaliacao',         [AdminController::class, 'instrutorAvaliacao']);
  Route::post('/admin/instrutor/buscar',            [AdminController::class, 'instrutorBuscar']);
  Route::post('/admin/instrutor/cnhBuscar',         [AdminController::class, 'instrutorCnhBuscar']);
  Route::post('/admin/instrutor/radar',             [AdminController::class, 'instrutorRadar']);
  Route::post('/admin/instrutor/update',            [AdminController::class, 'instrutorUpdate']);
});