<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Instrutor;
use App\Models\Pagamento;
use App\Repository\AsaasRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class PagamentoController extends Controller
{

  public function pagamentoPix(Request $req) {
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->aluno));
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    if (!config('app.debug') && !RateLimiter::attempt('IP_ADDRESS_' . request()->ip(), 5, function(){}))
      return $this->response('Muitas tentativas realizadas! Por favor, tente novamente mais tarde', false);

    try {
      $valores    = PagamentoRepository::getTotalPagamento($req->instrutor_id, $req->forma, $req->categoria, $req->pacote, 1, $req->km_real, $req->aluguel, $req->kmDesloc);
      $vCalculado = $valores['vDirigirTotal'] - 1;
      // Enviado pelo usuário, filtrado pela forma
      $vEnviado      = $req->vTotal;
      if ($req->forma === 'Cartão de Crédito') $vEnviado = $req->input('ccParcela.vTotal', 0);
      // Conferência final
      if ($vEnviado < $vCalculado) {
        throw new \Exception('O valor enviado não corresponde ao valor calculado. Por favor, faça o login novamente e tente gerar um novo pedido.');
      }
    } catch (\Throwable $th) {
      Log::channel('PAY')->error(__METHOD__ . ' - ' . $th->getMessage());
      return $this->response($th->getMessage(), false);
    }

    // ALERTA TEMPORÁRIO
    // return $this->response('Pix temporariamente indisponível. Por favor, utilize cartão de crédito ou contate o suporte.', false);

    // Pedido OK
    $instrutor = Instrutor::find($req->instrutor_id);

    // ----- aluno
    $aluno              = Aluno::find($req->aluno->id);
    $aluno->logradouro  = $req->aLogradouro;
    $aluno->numero      = $req->aNumero;
    $aluno->bairro      = $req->aBairro;
    $aluno->doc         = $req->aDoc;
    $aluno->save();
    $aluno              = AsaasRepository::postCustomers($aluno);   // Para retornar o aluno com o asaas_id


    // █▀▄ █ ▀▄▀
    // █▀  █ █ █
    $reference_id = (int) floor(microtime(true) * 100);
    $description  = "[$aluno->id] " . $aluno->nome . " - " . $reference_id;
    $response     = AsaasRepository::postPayments('PIX', $aluno->asaas_id, $vEnviado, $description, $reference_id, 1);
    try {
      $response->throw();
    } catch (\Throwable $th) {
      $message = data_get($response, 'errors.0.description', $th->getMessage());
      Log::channel('PAY')->info(__METHOD__ . ' - ' . $message);
      return $this->response($message, false);
    }
    Log::channel('PAY')->info(__METHOD__ . ' - ' . $response->body());
    $response = (object) $response->json();

    // Adiciona o pagamento com o status 'Criado'
    $pagamento                    = new Pagamento();
    $pagamento->aluno_id          = $aluno->id;
    $pagamento->instrutor_id      = $req->instrutor_id;
    $pagamento->reference_id      = $reference_id;
    $pagamento->statusAsaas       = $response->status;
    $pagamento->status            = 'Pendente';
    $pagamento->forma             = $req->forma;
    $pagamento->parcelas          = 1;
    $pagamento->categoria         = $req->categoria;
    $pagamento->pacote            = $req->pacote;
    $pagamento->rent              = $req->aluguel;
    $pagamento->kmDesloc          = $req->kmDesloc;
    $pagamento->vPago             = $vEnviado;
    $pagamento->vTaxaApp          = $instrutor->vTaxaApp;
    $pagamento->valoresJ          = $valores;
    $pagamento->responseJ         = $response;
    $pagamento->save();

    // Gerar QRCode Pix
    $response->pix = AsaasRepository::getPaymentQrCode($response->id);

    return $this->response($response);
  }

  public function pagamentoPixV2(Request $req) {
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->aluno));
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    if (!config('app.debug') && !RateLimiter::attempt('IP_ADDRESS_' . request()->ip(), 5, function(){}))
      return $this->response('Muitas tentativas realizadas! Por favor, tente novamente mais tarde', false);

    try {
      $valores    = PagamentoRepository::getTotalPagamentoV2($req->instrutor_id, $req->forma, $req->categoria, $req->pacote, 1, $req->km_real, $req->aluguel, $req->kmDesloc);
      $vCalculado = $valores['vDirigirTotal'] - 1;
      // Enviado pelo usuário, filtrado pela forma
      $vEnviado      = $req->vTotal;
      if ($req->forma === 'Cartão de Crédito') $vEnviado = $req->input('ccParcela.vTotal', 0);
      // Conferência final
      if ($vEnviado < $vCalculado) {
        throw new \Exception('O valor enviado não corresponde ao valor calculado. Por favor, faça o login novamente e tente gerar um novo pedido.');
      }
    } catch (\Throwable $th) {
      Log::channel('PAY')->error(__METHOD__ . ' - ' . $th->getMessage());
      return $this->response($th->getMessage(), false);
    }

    // ALERTA TEMPORÁRIO
    // return $this->response('Pix temporariamente indisponível. Por favor, utilize cartão de crédito ou contate o suporte.', false);

    // Pedido OK
    $instrutor = Instrutor::find($req->instrutor_id);

    // ----- aluno
    $aluno              = Aluno::find($req->aluno->id);
    $aluno->logradouro  = $req->aLogradouro;
    $aluno->numero      = $req->aNumero;
    $aluno->bairro      = $req->aBairro;
    $aluno->doc         = $req->aDoc;
    $aluno->save();
    $aluno              = AsaasRepository::postCustomers($aluno);   // Para retornar o aluno com o asaas_id


    // █▀▄ █ ▀▄▀
    // █▀  █ █ █
    $reference_id = (int) floor(microtime(true) * 100);
    $description  = "[$aluno->id] " . $aluno->nome . " - " . $reference_id;
    $response     = AsaasRepository::postPayments('PIX', $aluno->asaas_id, $vEnviado, $description, $reference_id, 1);
    try {
      $response->throw();
    } catch (\Throwable $th) {
      $message = data_get($response, 'errors.0.description', $th->getMessage());
      Log::channel('PAY')->info(__METHOD__ . ' - ' . $message);
      return $this->response($message, false);
    }
    Log::channel('PAY')->info(__METHOD__ . ' - ' . $response->body());
    $response = (object) $response->json();

    // Adiciona o pagamento com o status 'Criado'
    $pagamento                    = new Pagamento();
    $pagamento->aluno_id          = $aluno->id;
    $pagamento->instrutor_id      = $req->instrutor_id;
    $pagamento->reference_id      = $reference_id;
    $pagamento->statusAsaas       = $response->status;
    $pagamento->status            = 'Pendente';
    $pagamento->forma             = $req->forma;
    $pagamento->parcelas          = 1;
    $pagamento->categoria         = $req->categoria;
    $pagamento->pacote            = $req->pacote;
    $pagamento->rent              = $req->aluguel;
    $pagamento->kmDesloc          = $req->kmDesloc;
    $pagamento->vPago             = $vEnviado;
    $pagamento->vTaxaApp          = $instrutor->vTaxaApp;
    $pagamento->valoresJ          = $valores;
    $pagamento->responseJ         = $response;
    $pagamento->save();

    // Gerar QRCode Pix
    $response->pix = AsaasRepository::getPaymentQrCode($response->id);

    return $this->response($response);
  }

  public function pagamentoPixChat(Request $req) {
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->aluno));
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    if (!config('app.debug') && !RateLimiter::attempt('IP_ADDRESS_' . request()->ip(), 5, function(){}))
      return $this->response('Muitas tentativas realizadas! Por favor, tente novamente mais tarde', false);

    $valor    = $req->vTotal;
    if ($valor < 100) return $this->response('O valor enviado está incorreto.', false);

    // ALERTA TEMPORÁRIO
    // return $this->response('Pix temporariamente indisponível. Por favor, utilize cartão de crédito ou contate o suporte.', false);

    // Pedido OK
    $instrutor = Instrutor::find($req->instrutor_id);

    // ----- aluno
    $aluno              = Aluno::find($req->aluno->id);
    $aluno->logradouro  = $req->aLogradouro;
    $aluno->numero      = $req->aNumero;
    $aluno->bairro      = $req->aBairro;
    $aluno->doc         = $req->aDoc;
    $aluno->save();
    $aluno              = AsaasRepository::postCustomers($aluno);   // Para retornar o aluno com o asaas_id


    // █▀▄ █ ▀▄▀
    // █▀  █ █ █
    $reference_id = (int) floor(microtime(true) * 100);
    $description  = "[$aluno->id] " . $aluno->nome . " - " . $reference_id;
    $response     = AsaasRepository::postPayments('PIX', $aluno->asaas_id, $valor, $description, $reference_id, 1);
    try {
      $response->throw();
    } catch (\Throwable $th) {
      $message = data_get($response, 'errors.0.description', $th->getMessage());
      Log::channel('PAY')->info(__METHOD__ . ' - ' . $message);
      return $this->response($message, false);
    }
    Log::channel('PAY')->info(__METHOD__ . ' - ' . $response->body());
    $response = (object) $response->json();

    // Adiciona o pagamento com o status 'Criado'
    $pagamento                    = new Pagamento();
    $pagamento->aluno_id          = $aluno->id;
    $pagamento->instrutor_id      = $req->instrutor_id;
    $pagamento->reference_id      = $reference_id;
    $pagamento->statusAsaas       = $response->status;
    $pagamento->status            = 'Pendente';
    $pagamento->forma             = $req->forma;
    $pagamento->parcelas          = 1;
    $pagamento->categoria         = $req->categoria;
    $pagamento->pacote            = $req->pacote;
    $pagamento->rent              = $req->aluguel;
    $pagamento->kmDesloc          = $req->kmDesloc;
    $pagamento->vPago             = $valor;
    $pagamento->vTaxaApp          = $instrutor->vTaxaApp;
    $pagamento->valoresJ          = ['chatHash' => $req->chatHash, 'vBruto' => $req->vBruto, 'vLiquido' => $valor];
    $pagamento->responseJ         = $response;
    $pagamento->save();

    // Gerar QRCode Pix
    $response->pix = AsaasRepository::getPaymentQrCode($response->id);

    return $this->response($response);
  }

  public function pagamentoCredito(Request $req) {
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->aluno));
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    if (!config('app.debug') && !RateLimiter::attempt('IP_ADDRESS_' . request()->ip(), 5, function(){}))
      return $this->response('Muitas tentativas realizadas! Por favor, tente novamente mais tarde', false);

    // Confere se o valor enviado pelo usuário corresponde ao valor que será cobrado
    $parcela = $req->input('ccParcela.parcela', 1);
    try {
      $valores    = PagamentoRepository::getTotalPagamento($req->instrutor_id, $req->forma, $req->categoria, $req->pacote, $parcela, $req->km_real, $req->aluguel, $req->kmDesloc);
      $vCalculado = $valores['vDirigirTotal'] - 1;
      // Enviado pelo usuário, filtrado pela forma
      $vEnviado      = $req->vTotal;
      if ($req->forma === 'Cartão de Crédito') $vEnviado = $req->input('ccParcela.vTotal', 0);
      // Conferência final
      if ($vEnviado < $vCalculado) {
        throw new \Exception('O valor enviado não corresponde ao valor calculado. Por favor, faça o login novamente e tente gerar um novo pedido.');
      }
    } catch (\Throwable $th) {
      Log::channel('PAY')->error(__METHOD__ . ' - ' . $th->getMessage());
      return $this->response($th->getMessage(), false);
    }

    // Pedido OK
    $instrutor = Instrutor::find($req->instrutor_id);

    // ----- aluno
    $aluno              = Aluno::find($req->aluno->id);
    $aluno->logradouro  = $req->aLogradouro;
    $aluno->numero      = $req->aNumero;
    $aluno->bairro      = $req->aBairro;
    $aluno->doc         = $req->aDoc;
    $aluno->save();
    $aluno              = AsaasRepository::postCustomers($aluno);   // Para retornar o aluno com o asaas_id

    // ▄▀▀ ▄▀▄ █▀▄ ▀█▀ ▄▀▄ ▄▀▄    █▀▄ ██▀    ▄▀▀ █▀▄ ██▀ █▀▄ █ ▀█▀ ▄▀▄
    // ▀▄▄ █▀█ █▀▄  █  █▀█ ▀▄▀    █▄▀ █▄▄    ▀▄▄ █▀▄ █▄▄ █▄▀ █  █  ▀▄▀
    $card = [
      'holderName'    => $req->ccName,
      'number'        => $req->ccNumber,
      'expiryMonth'   => $req->ccMonth,
      'expiryYear'    => $req->ccYear,
      'ccv'           => $req->ccCSC
    ];
    $cardHolder = [
      'name'          => $req->ccName,
      'email'         => $aluno->email,
      'cpfCnpj'       => $req->ccCPF,
      'postalCode'    => $aluno->cep,
      'addressNumber' => $aluno->numero,
      'phone'         => $aluno->fone1
    ];
    $reference_id = (int) floor(microtime(true) * 100);
    $description  = "[$aluno->id] " . $aluno->nome . " - " . $reference_id;
    $response     = AsaasRepository::postPayments('CREDIT_CARD', $aluno->asaas_id, $vEnviado, $description, $reference_id, $parcela, $card, $cardHolder);
    try {
      $response->throw();
    } catch (\Throwable $th) {
      $message = data_get($response, 'errors.0.description', $th->getMessage());
      Log::channel('PAY')->info(__METHOD__ . ' - ' . $message);
      return $this->response($message, false);
    }
    Log::channel('PAY')->info(__METHOD__ . ' - ' . $response->body());
    $response = (object) $response->json();

    // Adiciona o pagamento com o status 'Criado'
    $pagamento                    = new Pagamento();
    $pagamento->aluno_id          = $aluno->id;
    $pagamento->instrutor_id      = $req->instrutor_id;
    $pagamento->reference_id      = $reference_id;
    $pagamento->statusAsaas       = $response->status;
    $pagamento->status            = 'Pendente';
    $pagamento->forma             = $req->forma;
    $pagamento->parcelas          = $parcela;
    $pagamento->categoria         = $req->categoria;
    $pagamento->pacote            = $req->pacote;
    $pagamento->rent              = $req->aluguel;
    $pagamento->kmDesloc          = $req->kmDesloc;
    $pagamento->vPago             = $vEnviado;
    $pagamento->vTaxaApp          = $instrutor->vTaxaApp;
    $pagamento->valoresJ          = $valores;
    $pagamento->responseJ         = $response;
    $pagamento->save();

    return $this->response($response);

  }

  public function pagamentoCreditoV2(Request $req) {
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->aluno));
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    if (!config('app.debug') && !RateLimiter::attempt('IP_ADDRESS_' . request()->ip(), 5, function(){}))
      return $this->response('Muitas tentativas realizadas! Por favor, tente novamente mais tarde', false);

    $parcela = $req->input('ccParcela.parcela', 1);
    try {
      $valores    = PagamentoRepository::getTotalPagamentoV2($req->instrutor_id, $req->forma, $req->categoria, $req->pacote, 1, $req->km_real, $req->aluguel, $req->kmDesloc);
      $vCalculado = $valores['vDirigirTotal'] - 1;
      // Enviado pelo usuário, filtrado pela forma
      $vEnviado      = $req->vTotal;
      if ($req->forma === 'Cartão de Crédito') $vEnviado = $req->input('ccParcela.vTotal', 0);
      // Conferência final
      if ($vEnviado < $vCalculado) {
        throw new \Exception('O valor enviado não corresponde ao valor calculado. Por favor, faça o login novamente e tente gerar um novo pedido.');
      }
    } catch (\Throwable $th) {
      Log::channel('PAY')->error(__METHOD__ . ' - ' . $th->getMessage());
      return $this->response($th->getMessage(), false);
    }

    // Pedido OK
    $instrutor = Instrutor::find($req->instrutor_id);

    // ----- aluno
    $aluno              = Aluno::find($req->aluno->id);
    $aluno->logradouro  = $req->aLogradouro;
    $aluno->numero      = $req->aNumero;
    $aluno->bairro      = $req->aBairro;
    $aluno->doc         = $req->aDoc;
    $aluno->save();
    $aluno              = AsaasRepository::postCustomers($aluno);   // Para retornar o aluno com o asaas_id

    // ▄▀▀ ▄▀▄ █▀▄ ▀█▀ ▄▀▄ ▄▀▄    █▀▄ ██▀    ▄▀▀ █▀▄ ██▀ █▀▄ █ ▀█▀ ▄▀▄
    // ▀▄▄ █▀█ █▀▄  █  █▀█ ▀▄▀    █▄▀ █▄▄    ▀▄▄ █▀▄ █▄▄ █▄▀ █  █  ▀▄▀
    $card = [
      'holderName'    => $req->ccName,
      'number'        => $req->ccNumber,
      'expiryMonth'   => $req->ccMonth,
      'expiryYear'    => $req->ccYear,
      'ccv'           => $req->ccCSC
    ];
    $cardHolder = [
      'name'          => $req->ccName,
      'email'         => $aluno->email,
      'cpfCnpj'       => $req->ccCPF,
      'postalCode'    => $aluno->cep,
      'addressNumber' => $aluno->numero,
      'phone'         => $aluno->fone1
    ];
    $reference_id = (int) floor(microtime(true) * 100);
    $description  = "[$aluno->id] " . $aluno->nome . " - " . $reference_id;
    $response     = AsaasRepository::postPayments('CREDIT_CARD', $aluno->asaas_id, $vEnviado, $description, $reference_id, $parcela, $card, $cardHolder);
    try {
      $response->throw();
    } catch (\Throwable $th) {
      $message = data_get($response, 'errors.0.description', $th->getMessage());
      Log::channel('PAY')->info(__METHOD__ . ' - ' . $message);
      return $this->response($message, false);
    }
    Log::channel('PAY')->info(__METHOD__ . ' - ' . $response->body());
    $response = (object) $response->json();

    // Adiciona o pagamento com o status 'Criado'
    $pagamento                    = new Pagamento();
    $pagamento->aluno_id          = $aluno->id;
    $pagamento->instrutor_id      = $req->instrutor_id;
    $pagamento->reference_id      = $reference_id;
    $pagamento->statusAsaas       = $response->status;
    $pagamento->status            = 'Pendente';
    $pagamento->forma             = $req->forma;
    $pagamento->parcelas          = $parcela;
    $pagamento->categoria         = $req->categoria;
    $pagamento->pacote            = $req->pacote;
    $pagamento->rent              = $req->aluguel;
    $pagamento->kmDesloc          = $req->kmDesloc;
    $pagamento->vPago             = $vEnviado;
    $pagamento->vTaxaApp          = $instrutor->vTaxaApp;
    $pagamento->valoresJ          = $valores;
    $pagamento->responseJ         = $response;
    $pagamento->save();

    return $this->response($response);

  }

  public function pagamentoCreditoChat(Request $req) {
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->aluno));
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    if (!config('app.debug') && !RateLimiter::attempt('IP_ADDRESS_' . request()->ip(), 5, function(){}))
      return $this->response('Muitas tentativas realizadas! Por favor, tente novamente mais tarde', false);

    // Confere se o valor enviado pelo usuário corresponde ao valor que será cobrado
    $parcela  = $req->input('ccParcela.parcela', 1);
    $valor    = $req->vTotal;
    if ($valor < 100) return $this->response('O valor enviado está incorreto.', false);

    // Calcula vTotal com taxas para parcelamento
    $valor = PagamentoRepository::calcTotalParcelas($valor, $parcela);

    // Pedido OK
    $instrutor = Instrutor::find($req->instrutor_id);

    // ----- aluno
    $aluno              = Aluno::find($req->aluno->id);
    $aluno->logradouro  = $req->aLogradouro;
    $aluno->numero      = $req->aNumero;
    $aluno->bairro      = $req->aBairro;
    $aluno->doc         = $req->aDoc;
    $aluno->save();
    $aluno              = AsaasRepository::postCustomers($aluno);   // Para retornar o aluno com o asaas_id

    // ▄▀▀ ▄▀▄ █▀▄ ▀█▀ ▄▀▄ ▄▀▄    █▀▄ ██▀    ▄▀▀ █▀▄ ██▀ █▀▄ █ ▀█▀ ▄▀▄
    // ▀▄▄ █▀█ █▀▄  █  █▀█ ▀▄▀    █▄▀ █▄▄    ▀▄▄ █▀▄ █▄▄ █▄▀ █  █  ▀▄▀
    $card = [
      'holderName'    => $req->ccName,
      'number'        => $req->ccNumber,
      'expiryMonth'   => $req->ccMonth,
      'expiryYear'    => $req->ccYear,
      'ccv'           => $req->ccCSC
    ];
    $cardHolder = [
      'name'          => $req->ccName,
      'email'         => $aluno->email,
      'cpfCnpj'       => $req->ccCPF,
      'postalCode'    => $aluno->cep,
      'addressNumber' => $aluno->numero,
      'phone'         => $aluno->fone1
    ];
    $reference_id = (int) floor(microtime(true) * 100);
    $description  = "[$aluno->id] " . $aluno->nome . " - " . $reference_id;
    $response     = AsaasRepository::postPayments('CREDIT_CARD', $aluno->asaas_id, $valor, $description, $reference_id, $parcela, $card, $cardHolder);
    try {
      $response->throw();
    } catch (\Throwable $th) {
      $message = data_get($response, 'errors.0.description', $th->getMessage());
      Log::channel('PAY')->info(__METHOD__ . ' - ' . $message);
      return $this->response($message, false);
    }
    Log::channel('PAY')->info(__METHOD__ . ' - ' . $response->body());
    $response = (object) $response->json();

    // Adiciona o pagamento com o status 'Criado'
    $pagamento                    = new Pagamento();
    $pagamento->aluno_id          = $aluno->id;
    $pagamento->instrutor_id      = $req->instrutor_id;
    $pagamento->reference_id      = $reference_id;
    $pagamento->statusAsaas       = $response->status;
    $pagamento->status            = 'Pendente';
    $pagamento->forma             = $req->forma;
    $pagamento->parcelas          = $parcela;
    $pagamento->categoria         = $req->categoria;
    $pagamento->pacote            = $req->pacote;
    $pagamento->rent              = $req->aluguel;
    $pagamento->kmDesloc          = $req->kmDesloc;
    $pagamento->vPago             = $valor;
    $pagamento->vTaxaApp          = $instrutor->vTaxaApp;
    $pagamento->valoresJ          = ['chatHash' => $req->chatHash, 'vBruto' => $req->vBruto, 'vLiquido' => $valor];
    $pagamento->responseJ         = $response;
    $pagamento->save();

    return $this->response($response);

  }

  public function getPaymentStatus(Request $req, $id) {
    $response = AsaasRepository::getPaymentStatus($id);
    return $this->response($response);
  }

}

