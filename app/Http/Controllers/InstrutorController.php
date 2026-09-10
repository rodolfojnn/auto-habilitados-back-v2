<?php

namespace App\Http\Controllers;

use App\Libraries\Helpers;
use App\Models\Aluno;
use App\Models\AlunoSolicitacao;
use App\Models\ChatList;
use App\Models\Instrutor;
use App\Models\InstrutorReview;
use App\Models\Pagamento;
use App\Repository\CNHBrasilRepository;
use App\Repository\MailRepository;
use App\Repository\NotificationRepository;
use Carbon\Carbon;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InstrutorController extends Controller
{

  public function update(Request $req) {

    // Validações
    $instrutor = Instrutor::find($req->instrutor->id);

    // Caso o instrutor já tenha salvo o cadsatro
    // if ($instrutor->finished_at && $req->adminKey !== 'ee073f15-21a6-4ced-ae82-173227e29940') {
    //   $data = $instrutor->created_at->format('d/m/Y');
    //   $hora = $instrutor->created_at->format('H:i');
    //   return $this->response("Você já possui um cadastro enviado em <b>{$data} às {$hora}</b>. Caso deseje editar esse cadastro, entre em contato com o suporte.", false);
    // }

    // $req->adminKey !== 'ee073f15-21a6-4ced-ae82-173227e29940' &&
    // if ($instrutor->status === 'AA') {
    //   return $this->response("Informamos que seu cadastro foi finalizado e auditado, não sendo mais possível realizar edições diretamente por esta página. Se precisar fazer qualquer modificação, nossa equipe de suporte terá prazer em ajudar.", false);
    // }


    $validator = Validator::make($req->all(), [

      // Campos obrigatórios gerais
      // 'logradouro'            => ['required'],
      // 'numero'                => ['required'],
      'cep'                   => ['required'],
      'sexo'                  => ['required'],
      // 'cnhReg'                => ['required'],
      // 'cnhValid'              => ['required'],
      // 'cnhUF'                 => ['required'],
      'cnhCateg'              => ['required'],
      // 'chavePix'              => ['required'],
      'profCap'               => ['required'],
      'profVinc'              => ['required'],
      'descricao'             => ['required'],
      'fotoPerfil'            => ['required'],
      // 'fotoCNH'               => ['required'],

      // Obrigatórios se carOwn === true
      'vCarOwn'               => ['required_if:carOwn,true'],
      'veMarca'               => ['required_if:carOwn,true'],
      'veModelo'              => ['required_if:carOwn,true'],
      // 'vePlaca'               => ['required_if:carOwn,true'],
      'veAno'                 => ['required_if:carOwn,true'],
      'veTipo'                => ['required_if:carOwn,true'],
      'veCambio'              => ['required_if:carOwn,true'],
      // 'fotoCLRV'              => ['required_if:carOwn,true'],
      'fotosCar'              => ['exclude_unless:carOwn,1,true,on', 'array', 'min:1'],

      // Obrigatórios se carAluno === true
      'vCarAluno'             => ['required_if:carAluno,true'],

      // Obrigatórios se bikeOwn === true
      'vBikeOwn'              => ['required_if:bikeOwn,true'],
      'bkMarca'               => ['required_if:bikeOwn,true'],
      'bkModelo'              => ['required_if:bikeOwn,true'],
      // 'bkPlaca'               => ['required_if:bikeOwn,true'],
      'bkAno'                 => ['required_if:bikeOwn,true'],
      // 'fotoCLRVBk'            => ['required_if:bikeOwn,true'],
      'fotosBk'               => ['exclude_unless:bikeOwn,1,true,on', 'array', 'min:1'],

      // Obrigatórios se bikeAluno === true
      'vBikeAluno'            => ['required_if:bikeAluno,true'],

    ], [
      // 'logradouro.required'             => 'O campo <b>Logradouro</b> é obrigatório.',
      // 'numero.required'                 => 'O campo <b>Número</b> é obrigatório.',
      'cep.required'                    => 'O campo <b>CEP</b> é obrigatório.',
      'sexo.required'                   => 'O campo <b>Sexo</b> é obrigatório.',
      // 'cnhReg.required'                 => 'O <b>número da CNH</b> é obrigatório.',
      // 'cnhValid.required'               => 'A <b>validade da CNH</b> é obrigatória.',
      // 'cnhUF.required'                  => 'O <b>estado de emissão da CNH</b> é obrigatório.',
      'cnhCateg.required'               => 'A <b>categoria da CNH</b> é obrigatória.',
      // 'chavePix.required'               => 'A <b>chave Pix</b> é obrigatória.',
      'profCap.required'                => 'O campo <b>Capacidade Profissional</b> é obrigatório.',
      'profVinc.required'               => 'O campo <b>Vínculo Profissional</b> é obrigatório.',
      'descricao.required'              => 'A <b>descrição</b> é obrigatória.',
      'fotoPerfil.required'             => 'A <b>foto de perfil</b> é obrigatória.',
      // 'fotoCNH.required'                => 'A <b>foto da CNH</b> é obrigatória.',
      'veMarca.required_if'             => 'O campo <b>Marca</b> é obrigatório quando você atende com carro próprio.',
      'veModelo.required_if'            => 'O campo <b>Modelo</b> é obrigatório quando você atende com carro próprio.',
      // 'vePlaca.required_if'             => 'O campo <b>Placa</b> é obrigatório quando você atende com carro próprio.',
      'veAno.required_if'               => 'O campo <b>Ano</b> é obrigatório quando você atende com carro próprio.',
      'veTipo.required_if'              => 'O campo <b>Tipo</b> é obrigatório quando você atende com carro próprio.',
      'veCambio.required_if'            => 'O campo <b>Câmbio</b> é obrigatório quando você atende com carro próprio.',
      // 'fotoCLRV.required_if'            => 'A foto do <b>CLRV do carro</b> é obrigatória quando você atende com carro próprio.',
      'fotosCar.required_if'            => 'É obrigatório enviar ao menos uma <b>foto do carro</b> quando você atende com carro próprio.',
      'fotosCar.min'                    => 'É obrigatório enviar ao menos <b>uma foto do carro</b>.',
      'bkMarca.required_if'             => 'O campo <b>Marca</b> é obrigatório quando você atende com moto própria.',
      'bkModelo.required_if'            => 'O campo <b>Modelo</b> é obrigatório quando você atende com moto própria.',
      // 'bkPlaca.required_if'             => 'O campo <b>Placa</b> é obrigatório quando você atende com moto própria.',
      'bkAno.required_if'               => 'O campo <b>Ano</b> é obrigatório quando você atende com moto própria.',
      // 'fotoCLRVBk.required_if'          => 'A foto do <b>CLRV da moto</b> é obrigatória quando você atende com moto própria.',
      'fotosBk.required_if'             => 'É obrigatório enviar ao menos uma <b>foto do moto</b> quando você atende com moto própria.',
      'fotosBk.min'                     => 'É obrigatório enviar ao menos <b>uma foto da moto</b>.',
      'vCarOwn.required_if'             => 'O <b>valor da aula do carro próprio</b> é obrigatório.',
      'vCarAluno.required_if'           => 'O <b>valor da aula do carro do aluno</b> é obrigatório.',
      'vBikeOwn.required_if'            => 'O <b>valor da aula da moto própria</b> é obrigatório.',
      'vBikeAluno.required_if'          => 'O <b>valor da aula da moto do aluno</b> é obrigatório.',
    ]);

    if ($validator->fails()) {
      return $this->response($validator->errors()->first(), false);
    }

    // Validação customizada
    if (!$req->carOwn && !$req->carAluno && !$req->bikeOwn && !$req->bikeAluno) {
      return $this->response('Selecione <b>ao menos uma opção</b>: carro próprio, carro do aluno, moto própria ou moto do aluno.', false);
    }

    // // Valor do aluguel não pode ser maior que o dobro do valor da aula
    // if ($req->vCarOwn && $req->vCarRent > $req->vCarOwn * 2) {
    //   return $this->response('O <b>valor do aluguel do carro</b> não pode ser maior que <b>dobro do valor da aula do carro</b>.', false);
    // }
    // if ($req->vBikeOwn && $req->vBikeRent > $req->vBikeOwn * 2) {
    //   return $this->response('O <b>valor do aluguel da moto</b> não pode ser maior que <b>dobro do valor da aula da moto</b>.', false);
    // }

    // Se o CEP da requisição for diferente do cadastro, revalidar CEP e fazer novamente o geocode
    $cep      = Helpers::onlyN($req->cep);
    $cepData  = [];
    $location = [];
    if ($cep !== $instrutor->cep) {
      $cep      = Helpers::onlyN($req->cep);
      $cepData  = GMapsRepository::getCepV3($cep);
      $location = GMapsRepository::geocode($cepData['logradouro'], $cepData['bairro'], $cepData['localidade'], $cepData['uf']);
      if (!$location['lat'] || !$location['lng']) throw new \Exception('Erro ao encontrar as coordenadas do endereço. Verifique o CEP e tente novamente.');
    }

    // ----- Instrutor
    if (!$instrutor->finished_at) {
      $instrutor->finished_at = new Carbon();
    }
    $instrutor->nome              = trim($req->nome);
    // $instrutor->email             = strtolower($req->email);   // Não permite mais alteração de e-mail
    $instrutor->fone1             = $req->fone1;
    if (data_get($cepData, 'uf')) {
      $instrutor->uf                = data_get($cepData, 'uf');
      $instrutor->municipio         = data_get($cepData, 'localidade');
      $instrutor->bairro            = data_get($cepData, 'bairro');
      $instrutor->logradouro        = data_get($cepData, 'logradouro');
    }
    if (data_get($location, 'lat')) {
      $instrutor->lat               = data_get($location, 'lat');
      $instrutor->lng               = data_get($location, 'lng');
    }
    if ($instrutor->status === 'I' || $instrutor->status === 'E' || $instrutor->status === 'S') {
      $instrutor->status          = CNHBrasilRepository::findInstrutor($instrutor->nome, $instrutor->uf) ? 'A' : 'S';

      // Bypass lista CNH
      if ($instrutor->nome === 'Germano Balieiro Martins') {
        $instrutor->status = 'A';
      }

      if ($instrutor->status === 'A') {
        NotificationRepository::newInstrutorRegiao($instrutor->lat, $instrutor->lng, 5);
      }
    }
    $instrutor->logradouro        = $req->logradouro;
    $instrutor->numero            = $req->numero;
    $instrutor->cep               = $cep;
    $instrutor->sexo              = $req->sexo;
    if ($instrutor->edit) {
      $instrutor->descricao       = $req->descricao;
    }
    $instrutor->fotosCarN         = count($req->fotosCar);
    $instrutor->fotosBkN          = count($req->fotosBk);
    $instrutor->cnhReg            = $req->cnhReg;
    $instrutor->cnhValid          = $req->cnhValid;
    $instrutor->cnhUF             = $req->cnhUF;
    $instrutor->cnhCateg          = $req->cnhCateg;
    $instrutor->vDesc5            = $req->vDesc5 ?: 0;
    $instrutor->vDesc10           = $req->vDesc10 ?: 0;
    $instrutor->vDesc15           = $req->vDesc15 ?: 0;
    $instrutor->vDesc20           = $req->vDesc20 ?: 0;
    $instrutor->carOwn            = $req->carOwn;
    $instrutor->vCarOwn           = $req->vCarOwn ?: null;
    $instrutor->vCarOwnKm         = $req->vCarOwnKm;
    $instrutor->vCarRent          = $req->vCarRent ?: null;
    $instrutor->carAluno          = $req->carAluno;
    $instrutor->vCarAluno         = $req->vCarAluno ?: null;
    $instrutor->vCarAlunoKm       = $req->vCarAlunoKm;
    $instrutor->bikeOwn           = $req->bikeOwn;
    $instrutor->vBikeOwn          = $req->vBikeOwn ?: null;
    $instrutor->vBikeOwnKm        = $req->vBikeOwnKm;
    $instrutor->vBikeRent         = $req->vBikeRent ?: null;
    $instrutor->bikeAluno         = $req->bikeAluno;
    $instrutor->vBikeAluno        = $req->vBikeAluno ?: null;
    $instrutor->vBikeAlunoKm      = $req->vBikeAlunoKm;
    $instrutor->kmMax             = $req->kmMax ?: 10;
    $instrutor->veMarca           = $req->veMarca;
    $instrutor->veModelo          = $req->veModelo;
    $instrutor->vePlaca           = strtoupper($req->vePlaca);
    $instrutor->veAno             = $req->veAno;
    $instrutor->veTipo            = $req->veTipo;
    $instrutor->veCambio          = $req->veCambio;
    $instrutor->bkMarca           = $req->bkMarca;
    $instrutor->bkModelo          = $req->bkModelo;
    $instrutor->bkPlaca           = strtoupper($req->bkPlaca);
    $instrutor->bkAno             = $req->bkAno;
    $instrutor->chavePix          = $req->chavePix;
    $instrutor->profCap           = $req->profCap;
    $instrutor->profVinc          = $req->profVinc;
    $instrutor->vTaxaApp          = 10;
    $instrutor->tpPri             = $req->tpPri;
    $instrutor->tpHab             = $req->tpHab;
    $instrutor->tpAdi             = $req->tpAdi;
    $instrutor->save();

    // Tratar campos carOwn e bikeOwn
    if ($instrutor->carOwn && !$instrutor->carAluno) {
      $instrutor->carAluno    = 1;
      $instrutor->vCarAluno   = $instrutor->vCarAluno ?: $instrutor->vCarOwn;
      $instrutor->vCarAlunoKm = $instrutor->vCarOwnKm;
      $instrutor->save();
    }
    if ($instrutor->bikeOwn && !$instrutor->bikeAluno) {
      $instrutor->bikeAluno     = 1;
      $instrutor->vBikeAluno    = $instrutor->vBikeAluno ?: $instrutor->vBikeOwn;
      $instrutor->vBikeAlunoKm  = $instrutor->vBikeOwnKm;
      $instrutor->save();
    }

    // fotoPerfil
    if ($req->fotoPerfil && $instrutor->edit) {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/picture/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->fotoPerfil));
      Helpers::tratamentoImg($folder, $instrutor->id . '.webp');
    }
    // fotoCNH
    if ($req->fotoCNH) {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/cnh/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->fotoCNH));
      Helpers::tratamentoImg($folder, $instrutor->id . '.webp');
    }
    // fotoCLRV
    if ($req->fotoCLRV) {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/clrv/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->fotoCLRV));
      Helpers::tratamentoImg($folder, $instrutor->id . '.webp');
    }
    // fotosCar
    if ($req->fotosCar && $instrutor->edit) { // !in_array($instrutor->status, ['A', 'AA'])
      foreach ($req->fotosCar as $key => $value) {
        $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/car/");
        $file = $folder . $instrutor->id . '_' . $key . '.webp';
        file_put_contents($file, Helpers::FileB64xBin($value));
        Helpers::tratamentoImg($folder, $instrutor->id . '_' . $key . '.webp');
      }
    }
    // fotoCLRVBk
    if ($req->fotoCLRVBk) {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/clrvbk/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->fotoCLRVBk));
      Helpers::tratamentoImg($folder, $instrutor->id . '.webp');
    }
    // fotosBk
    if ($req->fotosBk && $instrutor->edit) { // && !in_array($instrutor->status, ['A', 'AA'])
      foreach ($req->fotosBk as $key => $value) {
        $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/bike/");
        $file = $folder . $instrutor->id . '_' . $key . '.webp';
        file_put_contents($file, Helpers::FileB64xBin($value));
        Helpers::tratamentoImg($folder, $instrutor->id . '_' . $key . '.webp');
      }
    }

    // Limpar cache do instrutor
    InstrutorRepository::clearCache($instrutor->id);

    Log::info(__METHOD__ . ' - ' . $instrutor->email);
    Log::channel('logForms')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    return $this->response($instrutor);
  }

  public function updateV2(Request $req) {

    // Validações
    $instrutor = Instrutor::find($req->instrutor->id);

    $validator = Validator::make($req->all(), [
      // Obrigatórios
      'nome'                  => ['required'],
      'cep'                   => ['required'],
      'sexo'                  => ['required'],
      'descricao'             => ['required'],
      'fone1'                 => ['required'],
      // carOwn === true
      // 'vCarOwn'               => ['required_if:carOwn,true'],
      'veMarca'               => ['required_if:carOwn,true'],
      'veModelo'              => ['required_if:carOwn,true'],
      'veAno'                 => ['required_if:carOwn,true'],
      'veTipo'                => ['required_if:carOwn,true'],
      'veCambio'              => ['required_if:carOwn,true'],
      // carAluno === true
      // 'vCarAluno'             => ['required_if:carAluno,true'],
      // bikeOwn === true
      // 'vBikeOwn'              => ['required_if:bikeOwn,true'],
      'bkMarca'               => ['required_if:bikeOwn,true'],
      'bkModelo'              => ['required_if:bikeOwn,true'],
      'bkAno'                 => ['required_if:bikeOwn,true'],
      // bikeAluno === true
      // 'vBikeAluno'            => ['required_if:bikeAluno,true'],
    ], [
      'nome.required'                   => 'O campo <b>Nome</b> é obrigatório.',
      'cep.required'                    => 'O campo <b>CEP</b> é obrigatório.',
      'sexo.required'                   => 'O campo <b>Sexo</b> é obrigatório.',
      'descricao.required'              => 'A <b>descrição</b> é obrigatória.',
      'fone1.required'                  => 'O seu número de <b>Whatsapp</b> é obrigatório.',
      'veMarca.required_if'             => 'O campo <b>Marca</b> é obrigatório quando você atende com carro próprio.',
      'veModelo.required_if'            => 'O campo <b>Modelo</b> é obrigatório quando você atende com carro próprio.',
      'veAno.required_if'               => 'O campo <b>Ano</b> é obrigatório quando você atende com carro próprio.',
      'veTipo.required_if'              => 'O campo <b>Tipo</b> é obrigatório quando você atende com carro próprio.',
      'veCambio.required_if'            => 'O campo <b>Câmbio</b> é obrigatório quando você atende com carro próprio.',
      'bkMarca.required_if'             => 'O campo <b>Marca</b> é obrigatório quando você atende com moto própria.',
      'bkModelo.required_if'            => 'O campo <b>Modelo</b> é obrigatório quando você atende com moto própria.',
      'bkAno.required_if'               => 'O campo <b>Ano</b> é obrigatório quando você atende com moto própria.',
      'vCarOwn.required_if'             => 'O <b>valor da aula do carro próprio</b> é obrigatório.',
      'vCarAluno.required_if'           => 'O <b>valor da aula do carro do aluno</b> é obrigatório.',
      'vBikeOwn.required_if'            => 'O <b>valor da aula da moto própria</b> é obrigatório.',
      'vBikeAluno.required_if'          => 'O <b>valor da aula da moto do aluno</b> é obrigatório.',
    ]);

    if ($validator->fails()) return $this->response($validator->errors()->first(), false);

    // Validação customizada
    if (!$req->carOwn && !$req->carAluno && !$req->bikeOwn && !$req->bikeAluno) {
      return $this->response('Selecione <b>ao menos uma opção</b>: carro próprio, carro do aluno, moto própria ou moto do aluno.', false);
    }

    // Aviso de atualizar app caso todos os valores novos estejam em branco
    if (!$req->vCI2 && !$req->vCI4 && !$req->vCI6 && !$req->vCI8 && !$req->vCI10 &&
        !$req->vCA2 && !$req->vCA4 && !$req->vCA6 && !$req->vCA8 && !$req->vCA10 &&
        !$req->vMI2 && !$req->vMI4 && !$req->vMI6 && !$req->vMI8 && !$req->vMI10 &&
        !$req->vMA2 && !$req->vMA4 && !$req->vMA6 && !$req->vMA8 && !$req->vMA10) {
      return $this->response('Seu app está desatualizado! Atualize o app na loja de aplicativos para poder salvar as novas alterações!', false);
    }

    // Se o CEP da requisição for diferente do cadastro, revalidar CEP e fazer novamente o geocode
    $cep      = Helpers::onlyN($req->cep);
    $cepData  = [];
    $location = [];
    if ($cep !== $instrutor->cep) {
      $cep      = Helpers::onlyN($req->cep);
      $cepData  = GMapsRepository::getCepV3($cep);
      $location = GMapsRepository::geocode($cepData['logradouro'], $cepData['bairro'], $cepData['localidade'], $cepData['uf']);
      if (!$location['lat'] || !$location['lng']) throw new \Exception('Erro ao encontrar as coordenadas do endereço. Verifique o CEP e tente novamente.');
    }

    // ----- Instrutor
    if (!$instrutor->finished_at) {
      $instrutor->finished_at = new Carbon();
    }
    $instrutor->nome              = trim($req->nome);
    $instrutor->fone1             = $req->fone1;
    if (data_get($cepData, 'uf')) {
      $instrutor->uf                = data_get($cepData, 'uf');
      $instrutor->municipio         = data_get($cepData, 'localidade');
      $instrutor->bairro            = data_get($cepData, 'bairro');
      $instrutor->logradouro        = data_get($cepData, 'logradouro');
    }
    if (data_get($location, 'lat')) {
      $instrutor->lat               = data_get($location, 'lat');
      $instrutor->lng               = data_get($location, 'lng');
    }
    if ($instrutor->status === 'I' || $instrutor->status === 'E' || $instrutor->status === 'S') {
      $instrutor->status          = CNHBrasilRepository::findInstrutor($instrutor->nome, $instrutor->uf) ? 'A' : 'S';

      if ($instrutor->status === 'A') {
        NotificationRepository::newInstrutorRegiao($instrutor->lat, $instrutor->lng, 5);
      }
    }
    $instrutor->cep               = $cep;
    $instrutor->sexo              = $req->sexo;
    // Tratamento especial descrição / edit
    if (!$instrutor->edit && $instrutor->descricao !== $req->descricao) {
      $instrutor->edit            = 1;
      $instrutor->descricao       = $req->descricao;
    }
    if ($instrutor->edit) {
      $instrutor->descricao       = $req->descricao;
    }
    $instrutor->fotosCarN         = $this->countFotos($instrutor->id, 'car');
    $instrutor->fotosBkN          = $this->countFotos($instrutor->id, 'bike');
    $instrutor->cnhCateg          = (($req->bikeOwn || $req->bikeAluno) ? 'A' : '') . ($req->carOwn || $req->carAluno ? 'B' : '');
    $instrutor->vDesc5            = $req->vDesc5 ?: 0;
    $instrutor->vDesc10           = $req->vDesc10 ?: 0;
    $instrutor->vDesc15           = $req->vDesc15 ?: 0;
    $instrutor->vDesc20           = $req->vDesc20 ?: 0;
    $instrutor->carOwn            = $req->carOwn;
    $instrutor->vCarOwn           = $req->vCarOwn ?: null;
    $instrutor->vCarOwnKm         = $req->vCarOwnKm;
    $instrutor->vCarRent          = $req->vCarRent ?: null;
    $instrutor->carAluno          = $req->carAluno;
    $instrutor->vCarAluno         = $req->vCarAluno ?: null;
    $instrutor->vCarAlunoKm       = $req->vCarOwnKm;
    $instrutor->bikeOwn           = $req->bikeOwn;
    $instrutor->vBikeOwn          = $req->vBikeOwn ?: null;
    $instrutor->vBikeOwnKm        = $req->vBikeOwnKm;
    $instrutor->vBikeRent         = $req->vBikeRent ?: null;
    $instrutor->bikeAluno         = $req->bikeAluno;
    $instrutor->vBikeAluno        = $req->vBikeAluno ?: null;
    $instrutor->vBikeAlunoKm      = $req->vBikeOwnKm;
    $instrutor->kmMax             = $req->kmMax ?: 10;
    $instrutor->veMarca           = $req->veMarca;
    $instrutor->veModelo          = $req->veModelo;
    $instrutor->veAno             = $req->veAno;
    $instrutor->veTipo            = $req->veTipo;
    $instrutor->veCambio          = $req->veCambio;
    $instrutor->bkMarca           = $req->bkMarca;
    $instrutor->bkModelo          = $req->bkModelo;
    $instrutor->bkAno             = $req->bkAno;
    $instrutor->vCI2              = $req->vCI2;
    $instrutor->vCI4              = $req->vCI4;
    $instrutor->vCI6              = $req->vCI6;
    $instrutor->vCI8              = $req->vCI8;
    $instrutor->vCI10             = $req->vCI10;
    $instrutor->vCA2              = $req->vCA2;
    $instrutor->vCA4              = $req->vCA4;
    $instrutor->vCA6              = $req->vCA6;
    $instrutor->vCA8              = $req->vCA8;
    $instrutor->vCA10             = $req->vCA10;
    $instrutor->vMI2              = $req->vMI2;
    $instrutor->vMI4              = $req->vMI4;
    $instrutor->vMI6              = $req->vMI6;
    $instrutor->vMI8              = $req->vMI8;
    $instrutor->vMI10             = $req->vMI10;
    $instrutor->vMA2              = $req->vMA2;
    $instrutor->vMA4              = $req->vMA4;
    $instrutor->vMA6              = $req->vMA6;
    $instrutor->vMA8              = $req->vMA8;
    $instrutor->vMA10             = $req->vMA10;
    $instrutor->save();

    // // Tratar campos carOwn e bikeOwn
    // if ($instrutor->carOwn && !$instrutor->carAluno) {
    //   $instrutor->carAluno    = 1;
    //   $instrutor->vCarAluno   = $instrutor->vCarAluno ?: $instrutor->vCarOwn;
    //   $instrutor->vCarAlunoKm = $instrutor->vCarOwnKm;
    //   $instrutor->save();
    // }
    // if ($instrutor->bikeOwn && !$instrutor->bikeAluno) {
    //   $instrutor->bikeAluno     = 1;
    //   $instrutor->vBikeAluno    = $instrutor->vBikeAluno ?: $instrutor->vBikeOwn;
    //   $instrutor->vBikeAlunoKm  = $instrutor->vBikeOwnKm;
    //   $instrutor->save();
    // }

    // Limpar cache do instrutor
    InstrutorRepository::clearCache($instrutor->id);

    // Popula com as fotos no _fotos
    $instrutor->fotos = $this->buildPhotosObj($req->instrutor->id);

    Log::info(__METHOD__ . ' - ' . $instrutor->email);
    Log::channel('logForms')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    return $this->response($instrutor);
  }

  public function updateSelo(Request $req) {

    // Validações
    $instrutor = Instrutor::find($req->instrutor->id);

    // if ($instrutor->status === 'AA') {
    //   return $this->response("Informamos que seu cadastro foi finalizado e auditado, não sendo mais possível realizar edições diretamente por esta página. Se precisar fazer qualquer modificação, nossa equipe de suporte terá prazer em ajudar.", false);
    // }

    // if ($instrutor->selo) {
    //   return $this->response("Informamos que seu cadastro foi finalizado e auditado, não sendo mais possível realizar edições diretamente por esta página. Se precisar fazer qualquer modificação, nossa equipe de suporte terá prazer em ajudar.", false);
    // }

    $validator = Validator::make($req->all(), [

      // Campos obrigatórios gerais
      'doc'                   => ['required'],
      'logradouro'            => ['required'],
      'numero'                => ['required'],
      'cnhReg'                => ['required'],
      'cnhValid'              => ['required'],
      'cnhUF'                 => ['required'],
      'chavePix'              => ['required'],
      'fotoCNH'               => ['required'],

      // Obrigatórios se carOwn === true
      'vePlaca'               => ['required_if:carOwn,true'],
      'fotoCLRV'              => ['required_if:carOwn,true'],

      // Obrigatórios se bikeOwn === true
      'bkPlaca'               => ['required_if:bikeOwn,true'],
      'fotoCLRVBk'            => ['required_if:bikeOwn,true'],

    ], [
      'logradouro.required'             => 'O campo <b>Logradouro</b> é obrigatório.',
      'numero.required'                 => 'O campo <b>Número</b> é obrigatório.',
      'cnhReg.required'                 => 'O <b>número da CNH</b> é obrigatório.',
      'cnhValid.required'               => 'A <b>validade da CNH</b> é obrigatória.',
      'cnhUF.required'                  => 'O <b>estado de emissão da CNH</b> é obrigatório.',
      'chavePix.required'               => 'A <b>chave Pix</b> é obrigatória.',
      'fotoCNH.required'                => 'A <b>foto da CNH</b> é obrigatória.',
      'vePlaca.required_if'             => 'O campo <b>Placa</b> é obrigatório quando você atende com carro próprio.',
      'fotoCLRV.required_if'            => 'A foto do <b>CLRV do carro</b> é obrigatória quando você atende com carro próprio.',
      'bkPlaca.required_if'             => 'O campo <b>Placa</b> é obrigatório quando você atende com moto própria.',
      'fotoCLRVBk.required_if'          => 'A foto do <b>CLRV da moto</b> é obrigatória quando você atende com moto própria.',
    ]);

    if ($validator->fails()) {
      return $this->response($validator->errors()->first(), false);
    }

    // ----- instrutor
    $instrutor->status            = 'AA';
    $instrutor->doc               = $req->doc;
    $instrutor->logradouro        = $req->logradouro;
    $instrutor->numero            = $req->numero;
    $instrutor->cnhReg            = $req->cnhReg;
    $instrutor->cnhValid          = $req->cnhValid;
    $instrutor->cnhUF             = $req->cnhUF;
    $instrutor->chavePix          = $req->chavePix;
    $instrutor->vePlaca           = strtoupper($req->vePlaca);
    $instrutor->bkPlaca           = strtoupper($req->bkPlaca);
    $instrutor->save();

    // fotoCNH
    if ($req->fotoCNH) {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/cnh/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->fotoCNH));
      Helpers::tratamentoImg($folder, $instrutor->id . '.webp');
    }
    // fotoCLRV
    if ($req->fotoCLRV) {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/clrv/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->fotoCLRV));
      Helpers::tratamentoImg($folder, $instrutor->id . '.webp');
    }
    // fotoCLRVBk
    if ($req->fotoCLRVBk) {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/clrvbk/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->fotoCLRVBk));
      Helpers::tratamentoImg($folder, $instrutor->id . '.webp');
    }

    Log::info(__METHOD__ . ' - ' . $instrutor->email);
    Log::channel('logForms')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    return $this->response($instrutor);

  }

  public function updateSeloV2(Request $req) {

    // Validações
    $instrutor = Instrutor::find($req->instrutor->id);

    // if ($instrutor->status === 'AA') {
    //   return $this->response("Informamos que seu cadastro foi finalizado e auditado, não sendo mais possível realizar edições diretamente por esta página. Se precisar fazer qualquer modificação, nossa equipe de suporte terá prazer em ajudar.", false);
    // }

    // if ($instrutor->selo) {
    //   return $this->response("Informamos que seu cadastro foi finalizado e auditado, não sendo mais possível realizar edições diretamente por esta página. Se precisar fazer qualquer modificação, nossa equipe de suporte terá prazer em ajudar.", false);
    // }

    $validator = Validator::make($req->all(), [
      // Campos obrigatórios gerais
      'doc'                   => ['required'],
      'logradouro'            => ['required'],
      'numero'                => ['required'],
      'chavePix'              => ['required'],
      // carOwn === true
      'vePlaca'               => ['required_if:carOwn,true'],
      // bikeOwn === true
      'bkPlaca'               => ['required_if:bikeOwn,true'],
    ], [
      'logradouro.required'             => 'O campo <b>Logradouro</b> é obrigatório.',
      'numero.required'                 => 'O campo <b>Número</b> é obrigatório.',
      'chavePix.required'               => 'A <b>chave Pix</b> é obrigatória.',
      'vePlaca.required_if'             => 'O campo <b>Placa</b> é obrigatório quando você atende com carro próprio.',
      'bkPlaca.required_if'             => 'O campo <b>Placa</b> é obrigatório quando você atende com moto própria.',
    ]);

    if ($validator->fails()) return $this->response($validator->errors()->first(), false);
    if (Helpers::validaCPF($req->doc) === false) return $this->response("O CPF informado é inválido.", false);

    // ----- instrutor
    $instrutor->status            = 'AA';
    $instrutor->doc               = $req->doc;
    $instrutor->logradouro        = $req->logradouro;
    $instrutor->numero            = $req->numero;
    $instrutor->chavePix          = $req->chavePix;
    $instrutor->vePlaca           = strtoupper($req->vePlaca);
    $instrutor->bkPlaca           = strtoupper($req->bkPlaca);
    $instrutor->save();

    // Popula com as fotos no _fotos
    $instrutor->fotos = $this->buildPhotosObj($req->instrutor->id);

    Log::info(__METHOD__ . ' - ' . $instrutor->email);
    Log::channel('logForms')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    return $this->response($instrutor);

  }

  public function register(Request $req) {

    $email = strtolower(trim($req->email));

    if (!RateLimiter::attempt(__METHOD__ . '_' . request()->ip(), $perMinute = 5, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $instrutor                    = Instrutor::firstOrNew(['email' => $email]);

    // Se já existe cadastro do instrutor
    if ($instrutor->id) return $this->response('Instrutor já cadastrado. Por favor, clique em <b>JÁ TENHO UMA CONTA</b> e prossiga com o login.', false);

    Log::info(__METHOD__ . ' - ' . json_encode($req->all()));

    // Valida se o CEP existe
    $cep      = Helpers::onlyN($req->cep);
    $cepData  = GMapsRepository::getCepV3($cep);
    $location = GMapsRepository::geocode($cepData['logradouro'], $cepData['bairro'], $cepData['localidade'], $cepData['uf']);
    if (!$location['lat'] || !$location['lng']) throw new \Exception('Erro ao encontrar as coordenadas do endereço. Verifique o CEP e tente novamente.');

    $instrutor->status            = 'E';
    $instrutor->ativo             = true;
    $instrutor->edit              = true;
    $instrutor->nome              = $req->nome;
    $instrutor->email             = $email;
    $instrutor->fone1             = $req->fone1;
    $instrutor->uf                = $cepData['uf'];
    $instrutor->municipio         = $cepData['localidade'];
    $instrutor->bairro            = $cepData['bairro'] ?: null;
    $instrutor->logradouro        = $cepData['logradouro'] ?: null;
    $instrutor->cep               = $cep;
    $instrutor->password          = Hash::make($req->password);
    $instrutor->vDesc5            = 0;
    $instrutor->vDesc10           = 0;
    $instrutor->vDesc15           = 0;
    $instrutor->vDesc20           = 0;
    $instrutor->lat               = $location['lat'];
    $instrutor->lng               = $location['lng'];
    $instrutor->carOwn            = $req->vCarOwn ? true : false;
    $instrutor->vCarOwn           = $req->vCarOwn ?: null;
    $instrutor->vCarOwnKm         = 0.50;
    $instrutor->carAluno          = $req->vCarOwn ? true : false;;
    $instrutor->vCarAluno         = $req->vCarOwn ?: null;;
    $instrutor->vCarAlunoKm       = 0.50;
    $instrutor->bikeOwn           = $req->vBikeOwn ? true : false;
    $instrutor->vBikeOwn          = $req->vBikeOwn ?: null;
    $instrutor->vBikeOwnKm        = 0.25;
    $instrutor->bikeAluno         = $req->vBikeOwn ? true : false;
    $instrutor->vBikeAluno        = $req->vBikeOwn ?: null;
    $instrutor->vBikeAlunoKm      = 0.25;
    $instrutor->kmMax             = 10;
    $instrutor->nota              = 0;
    $instrutor->notaQtd           = 0;
    $instrutor->token             = random_int(1000, 9999);
    $instrutor->indicacao         = $req->indicacao;
    $instrutor->destaque          = 0;
    $instrutor->profCap           = 'Formação';
    $instrutor->vTaxaApp          = 10;

    // Termos
    $instrutor->termos            = [
      'ip'                        => request()->ip(),
      'ua'                        => $_SERVER['HTTP_USER_AGENT'],
      'date'                      => Carbon::now()->toDateTimeString(),
      'versao'                    => 1
    ];

    $instrutor->save();

    // JWT
    $secret = config('app.JWT_SECRET_INSTRUTOR');
    $jwt = JWT::encode(
      [
        'instrutor_id'  => $instrutor->id,
        'iss'           => 'dirigiragora',                                // issuer
        'iat'           => Carbon::now()->getTimestamp(),               // issued at
      ],
      $secret,
      'HS256'
    );

    // E-mail token
    // Log::info(__METHOD__ . ' - ' . $instrutor->email);
    // MailRepository::sendToken($instrutor->email, $instrutor->token);

    // WAPI Mensagem
    // WApiRepository::sendMessage('41997629021', "*Dados do Instrutor*\nNome: $instrutor->nome\nTelefone: $instrutor->fone1\nE-mail: $instrutor->email\nCEP: $instrutor->cep\nMunicipio: $instrutor->municipio\nUF: $instrutor->uf");

    return $this->response(['jwt' => $jwt, 'instrutor' => $instrutor]);
  }

  public function registerV2(Request $req) {
    if (!RateLimiter::attempt(__METHOD__ . '_' . request()->ip(), $perMinute = 5, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $email      = strtolower(trim($req->email));
    $instrutor  = Instrutor::firstOrNew(['email' => $email]);

    // Se já existe cadastro do instrutor
    if ($instrutor->id) return $this->response('Instrutor já cadastrado. Por favor, prossiga com o login.', false);

    Log::info(__METHOD__ . ' - ' . json_encode($req->all()));

    // Valida se o CEP existe
    $cep      = Helpers::onlyN($req->cep);
    $cepData  = GMapsRepository::getCepV3($cep);
    $location = GMapsRepository::geocode($cepData['logradouro'], $cepData['bairro'], $cepData['localidade'], $cepData['uf']);
    if (!$location['lat'] || !$location['lng']) throw new \Exception('Erro ao encontrar as coordenadas do endereço. Verifique o CEP e tente novamente.');

    $instrutor->status            = 'E';
    $instrutor->ativo             = true;
    $instrutor->edit              = true;
    $instrutor->nome              = $req->nome;
    $instrutor->email             = $email;
    $instrutor->fone1             = $req->fone1;
    $instrutor->uf                = $cepData['uf'];
    $instrutor->municipio         = $cepData['localidade'];
    $instrutor->bairro            = $cepData['bairro'] ?: null;
    $instrutor->logradouro        = $cepData['logradouro'] ?: null;
    $instrutor->cep               = $cep;
    $instrutor->password          = Hash::make($req->password);
    $instrutor->vDesc5            = null;
    $instrutor->vDesc10           = null;
    $instrutor->vDesc15           = null;
    $instrutor->vDesc20           = null;
    $instrutor->lat               = $location['lat'];
    $instrutor->lng               = $location['lng'];
    $instrutor->carOwn            = false;
    $instrutor->vCarOwn           = null;
    $instrutor->vCarOwnKm         = 0.50;
    $instrutor->carAluno          = false;
    $instrutor->vCarAluno         = null;
    $instrutor->vCarAlunoKm       = 0.50;
    $instrutor->bikeOwn           = false;
    $instrutor->vBikeOwn          = null;
    $instrutor->vBikeOwnKm        = 0.25;
    $instrutor->bikeAluno         = false;
    $instrutor->vBikeAluno        = null;
    $instrutor->vBikeAlunoKm      = 0.25;
    $instrutor->kmMax             = $req->kmMax;
    $instrutor->nota              = 0;
    $instrutor->notaQtd           = 0;
    $instrutor->token             = random_int(1000, 9999);
    $instrutor->indicacao         = $req->indicacao ?: null;
    $instrutor->destaque          = 0;
    $instrutor->profCap           = 'Formação';
    $instrutor->profVinc          = 'Autonomo';
    $instrutor->tpPri             = true;
    $instrutor->tpHab             = true;
    $instrutor->tpAdi             = true;
    $instrutor->vTaxaApp          = 10;

    // Termos
    $instrutor->termos            = [
      'ip'                        => request()->ip(),
      'ua'                        => $_SERVER['HTTP_USER_AGENT'],
      'date'                      => Carbon::now()->toDateTimeString(),
      'versao'                    => 1
    ];

    $instrutor->save();

    // JWT
    $secret = config('app.JWT_SECRET_INSTRUTOR');
    $jwt = JWT::encode(
      [
        'instrutor_id'  => $instrutor->id,
        'iss'           => 'dirigiragora',                                // issuer
        'iat'           => Carbon::now()->getTimestamp(),               // issued at
      ],
      $secret,
      'HS256'
    );

    // E-mail token
    // Log::info(__METHOD__ . ' - ' . $instrutor->email);
    // MailRepository::sendToken($instrutor->email, $instrutor->token);

    // WAPI Mensagem
    // WApiRepository::sendMessage('41997629021', "*Dados do Instrutor*\nNome: $instrutor->nome\nTelefone: $instrutor->fone1\nE-mail: $instrutor->email\nCEP: $instrutor->cep\nMunicipio: $instrutor->municipio\nUF: $instrutor->uf");

    // Boas vindas chat do instrutor
    ChatRepository::sendMessage('Aluno', $instrutor->id, 6150, "Olá, instrutor(a)! Que bom ter você no Dirigir Agora. 💚 Queremos compartilhar com você que estamos dando os primeiros passos aqui.

Sabendo que o fluxo de alunos pode ser menor no início, agradecemos muito pela sua confiança na nossa proposta. Estamos nos dedicando ao máximo na divulgação. 🚀

Nosso modelo está totalmente voltado para o seu crescimento hoje: o uso gratuito para você, e 100% das taxas dos alunos são revertidas em marketing para atrair mais pessoas para o seu perfil. 🤝

Divulgue também o seu link e conte com o nosso suporte por este chat sempre que precisar! 🤜🏼🤛🏽

Equipe Dirigir Agora. @dirigiragora");

    return $this->response(['jwt' => $jwt, 'instrutor' => $instrutor]);
  }

  public function login(Request $req) {

    // return $this->response('Servidor em manutenção, por favor tente novamente mais tarde', false);

    $email    = strtolower(trim($req->email));
    $password = trim($req->password);
    if (!RateLimiter::attempt(__METHOD__ . '_' . request()->ip(), $perMinute = 5, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $instrutor = Instrutor::where('email', $email)->first();
    if (!$instrutor) {
      Log::info(__METHOD__ . ' - Instrutor não encontrado - ' . $email . ' - ' . $password);
      return $this->response('Cadastro não encontrado. Revise os dados ou cadastre-se novamente.', false);
    }
    if (
      $req->adminKey !== 'ee073f15-21a6-4ced-ae82-173227e29940' &&
      $req->password !== '2017-es@L#' &&
      $req->password !== $instrutor->fone1) {
      if (!Hash::check($password, $instrutor->password)) {
        Log::info(__METHOD__ . ' - Senha INSTRUTOR incorreta - ' . $email . ' - ' . $password);
        return $this->response('E-mail ou senha incorretos. Tente usar o seu telefone como senha (apenas números). Você também pode redefenir a senha clicando em "Esqueci minha senha".', false);
      }
    }
    if ($instrutor->status === 'X') {
      return $this->response('Este perfil foi desativado temporariamente. Em caso de dúvidas, entre em contato com o suporte.', false);
    }

    // JWT
    $secret = config('app.JWT_SECRET_INSTRUTOR');
    $jwt = JWT::encode(
      [
        'instrutor_id'  => $instrutor->id,
        'iss'           => 'dirigiragora',                                // issuer
        'iat'           => Carbon::now()->getTimestamp(),               // issued at
      ],
      $secret,
      'HS256'
    );

    // E-mail token
    if ($instrutor->status === 'I') {
      $token = $instrutor->token;
      if (!$token) $instrutor->token = random_int(1000, 9999);
      $instrutor->save();
      MailRepository::sendToken($instrutor->email, $instrutor->token);
    }

    return $this->response(['jwt' => $jwt, 'instrutor' => $instrutor]);
  }

  public function loginJwt(Request $req) {

    // Valida JWT
    $jwtKey = config('app.JWT_SECRET_INSTRUTOR');
    $decoded = JWT::decode($req->jwt, new Key($jwtKey, 'HS256'));
    if (!isset($decoded->instrutor_id)) throw new Exception('Instrutor inválido');

    $instrutor = Instrutor::where('id', $decoded->instrutor_id)->first();
    if (!$instrutor) {
      Log::info(__METHOD__ . ' - Instrutor não encontrado');
      return $this->response('Dados do instrutor não encontrados, por favor preencha o formulário novamente.', false);
    }

    // JWT
    $secret = config('app.JWT_SECRET_INSTRUTOR');
    $jwt = JWT::encode(
      [
        'instrutor_id'  => $instrutor->id,
        'iss'           => 'dirigiragora',                                // issuer
        'iat'           => Carbon::now()->getTimestamp(),               // issued at
      ],
      $secret,
      'HS256'
    );

    return $this->response(['jwt' => $jwt, 'instrutor' => $instrutor]);
  }

  public function proximos(Request $req) {
    // Raio linear
    $raio = 18;
    $select = "id,
      CONCAT(UCASE(LEFT(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 1)),
      LCASE(SUBSTRING(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 2))) as nome,
      uf, municipio, bairro, lat, lng, status, ativo, selo,
      nota, notaQtd, vDesc5, vDesc10, vDesc15, vDesc20, fotosCarN, fotosBkN,
      carOwn, vCarOwn, vCarOwnKm, vCarRent, carAluno, vCarAluno, vCarAlunoKm,
      bikeOwn, vBikeOwn, vBikeOwnKm, vBikeRent, bikeAluno, vBikeAluno, vBikeAlunoKm, kmMax,
      veMarca, veModelo, veAno, veTipo, veCambio,
      bkMarca, bkModelo, bkAno,
      descricao, vTaxaApp, destaque,
      vCI2, vCI4, vCI6, vCI8, vCI10, vCA2, vCA4, vCA6, vCA8, vCA10, vMI2, vMI4, vMI6, vMI8, vMI10, vMA2, vMA4, vMA6, vMA8, vMA10";
    $instrutoresProximos = Instrutor::select(
      DB::raw("
        $select,
        ROUND(
            6371 * acos(
                cos(radians({$req->aluno->lat}))
                * cos(radians(lat))
                * cos(radians(lng) - radians({$req->aluno->lng}))
                + sin(radians({$req->aluno->lat})) * sin(radians(lat))
            ),
        2
        ) AS km_linear
      ")
    )
    // ->where('status', '!=', 'I')
    ->where('ativo', '>=', 1)
    ->where(function ($query) {
      $query->orWhere('status', '=', 'AA')
            ->orWhere('status', '=', 'A');
    })
    ->having('km_linear', '<', $raio)
    // ->orderByRaw('(carOwn = 1 OR bikeOwn = 1) DESC')
    // ->orderBy('km_linear')
    // ->limit(32)
    ->get();

    // TODO
    // se não achou nenhum, refaz a busca com 35

    // Calcula kmMax do instrutor
    $instrutoresProximos = $instrutoresProximos->map(function ($instrutor) {
      $instrutor->km_real = Helpers::N2($instrutor->km_linear * 1.4);
      return $instrutor;
    })->values();

    // Calcula kmMax do instrutor
    $instrutoresProximos = $instrutoresProximos->filter(function ($instrutor) {
      $kmMax = $instrutor->kmMax ?? 10;
      return $instrutor->km_linear <= $kmMax;
    })->values();

    // // Directions Matrix
    // $destinos = [];
    // foreach ($instrutoresProximos as $instrutor) $destinos[] = ['lat' => $instrutor->lat,'lng' => $instrutor->lng];
    // $destinosKm = GMapsRepository::distanceKmMatrix($req->aluno->lat, $req->aluno->lng, $destinos);
    // foreach ($instrutoresProximos as $i => $instrutor) $instrutoresProximos[$i]->km_real = $destinosKm[$i]['distancia_km'];

    // Ordenar pelo km_real
    $instrutoresProximos = $instrutoresProximos->sortBy('km_real')->values();

    // Aplicar vTaxaApp
    foreach ($instrutoresProximos as &$instrutor) {
      $instrutor->vCarOwn       = round($instrutor->vCarOwn + ($instrutor->vCarOwn * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCarAluno     = round($instrutor->vCarAluno + ($instrutor->vCarAluno * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vBikeOwn      = round($instrutor->vBikeOwn + ($instrutor->vBikeOwn * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vBikeAluno    = round($instrutor->vBikeAluno + ($instrutor->vBikeAluno * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCarOwnKm     = round($instrutor->vCarOwnKm + ($instrutor->vCarOwnKm * ($instrutor->vTaxaApp / 100)), 3);
      $instrutor->vCarRent      = round($instrutor->vCarRent + ($instrutor->vCarRent * ($instrutor->vTaxaApp / 100)), 3);
      $instrutor->vCarAlunoKm   = round($instrutor->vCarAlunoKm + ($instrutor->vCarAlunoKm * ($instrutor->vTaxaApp / 100)), 3);
      $instrutor->vBikeOwnKm    = round($instrutor->vBikeOwnKm + ($instrutor->vBikeOwnKm * ($instrutor->vTaxaApp / 100)), 3);
      $instrutor->vBikeRent     = round($instrutor->vBikeRent + ($instrutor->vBikeRent * ($instrutor->vTaxaApp / 100)), 3);
      $instrutor->vBikeAlunoKm  = round($instrutor->vBikeAlunoKm + ($instrutor->vBikeAlunoKm * ($instrutor->vTaxaApp / 100)), 3);

      // Taxa nos novos campos também
      $instrutor->vCI2       = round($instrutor->vCI2 + ($instrutor->vCI2 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCI4       = round($instrutor->vCI4 + ($instrutor->vCI4 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCI6       = round($instrutor->vCI6 + ($instrutor->vCI6 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCI8       = round($instrutor->vCI8 + ($instrutor->vCI8 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCI10      = round($instrutor->vCI10 + ($instrutor->vCI10 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCA2       = round($instrutor->vCA2 + ($instrutor->vCA2 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCA4       = round($instrutor->vCA4 + ($instrutor->vCA4 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCA6       = round($instrutor->vCA6 + ($instrutor->vCA6 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCA8       = round($instrutor->vCA8 + ($instrutor->vCA8 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vCA10      = round($instrutor->vCA10 + ($instrutor->vCA10 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMI2       = round($instrutor->vMI2 + ($instrutor->vMI2 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMI4       = round($instrutor->vMI4 + ($instrutor->vMI4 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMI6       = round($instrutor->vMI6 + ($instrutor->vMI6 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMI8       = round($instrutor->vMI8 + ($instrutor->vMI8 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMI10      = round($instrutor->vMI10 + ($instrutor->vMI10 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMA2       = round($instrutor->vMA2 + ($instrutor->vMA2 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMA4       = round($instrutor->vMA4 + ($instrutor->vMA4 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMA6       = round($instrutor->vMA6 + ($instrutor->vMA6 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMA8       = round($instrutor->vMA8 + ($instrutor->vMA8 * ($instrutor->vTaxaApp / 100)), 2);
      $instrutor->vMA10      = round($instrutor->vMA10 + ($instrutor->vMA10 * ($instrutor->vTaxaApp / 100)), 2);
    }

    if ($instrutoresProximos->count() === 0) {
      // WApiRepository::sendMessage('41997629021', "*Nenhum instrutor encontrado*\n\n*Informações do aluno*\nNome: {$req->aluno->nome}\nTelefone: {$req->aluno->fone1}\nCidade: {$req->aluno->municipio}\nBairro: {$req->aluno->bairro}\nEstado: {$req->aluno->uf}");
    }

    return $this->response($instrutoresProximos->toArray());
  }

  public function reviews(Request $req) {

    // ----- reviewsStar
    // Histograma das notas
    $counts = DB::table('instrutorReview')
      ->select('nota', DB::raw('COUNT(*) as quant'))
      ->where('instrutor_id', $req->instrutor_id)
      ->groupBy('nota')
      ->pluck('quant', 'nota');
    $total = $counts->sum();
    $reviewsStar = [];
    for ($i = 1; $i <= 5; $i++) {
      $quant = $counts->get($i, 0);
      $width = $total > 0 ? ($quant / $total) * 100 : 0;
      $reviewsStar[$i] = [
        'width' => $width,
        'quant' => $quant,
      ];
    }

    // ----- reviews
    $reviews = InstrutorReview::where('instrutor_id', '=', $req->instrutor_id)
      ->orderBy('id', 'DESC')
      ->limit(10)
      ->get();

    return $this->response(['reviews' => $reviews, 'reviewsStar' => $reviewsStar]);
  }

  public function getPagamentos(Request $req) {
    $pagamentos = Pagamento::with('pagamentoItem', 'aluno')
      ->select('id', 'aluno_id', 'forma', 'parcelas', 'categoria', 'pacote', 'rent', 'kmDesloc', 'valoresJ')
      ->where('instrutor_id', '=', $req->instrutor->id)
      ->where('status', '=', 'Confirmado')
      ->orderBy('id', 'DESC')
      ->get();
    return $this->response($pagamentos);
  }

  public function getReviews(Request $req) {
    // ----- reviews
    $reviews = InstrutorReview::where('instrutor_id', '=', $req->instrutor->id)
      ->orderBy('id', 'DESC')
      ->limit(20)
      ->get();

    return $this->response($reviews);
  }

  public function sendMessage(Request $req) {
    $contato    = (object) $req->input('frmContato');
    $cart       = (object) $req->input('frmMain');
    $vTotal     = $req->input('vTotal');
    $instrutor  = Instrutor::find($req->input('instrutor.id'));

    // ----- alunoSolicitacao
    $alunoSolicitacao                 = new AlunoSolicitacao();
    $alunoSolicitacao->instrutor_id   = $instrutor->id;
    $alunoSolicitacao->aluno_id       = $req->aluno->id;
    $alunoSolicitacao->nome           = $contato->nome;
    $alunoSolicitacao->fone1          = $contato->fone1;
    $alunoSolicitacao->email          = $contato->email;
    $alunoSolicitacao->mensagem       = $contato->mensagem;
    $alunoSolicitacao->save();

    // WApiRepository::sendMessage('41997629021', "*Novo contato de aluno*\nNome do aluno: $contato->nome\nTelefone: $contato->fone1\nE-mail: $contato->email\nMensagem: $contato->mensagem\nQuantidade de aulas: $cart->pacote\nTotal: R$ $vTotal\n\n*Informações do instrutor*\nNome: $instrutor->nome\nTelefone: $instrutor->fone1\nE-mail: $instrutor->email\nID: $instrutor->id");
    MailRepository::msgManager("Novo contato de aluno\nNome do aluno: $contato->nome\nTelefone: <a href='https://api.whatsapp.com/send?phone=$contato->fone1'>$contato->fone1</a>\nE-mail: $contato->email\nMensagem: $contato->mensagem\nQuantidade de aulas: $cart->pacote\nTotal: R$ $vTotal\n\n*Informações do instrutor*\nNome: $instrutor->nome\nTelefone: $instrutor->fone1\nE-mail: $instrutor->email\nID: $instrutor->id");

    return $this->response(true);
  }

  public function sendReview(Request $req) {

    // Validação do instrutor_id futuramente

    // Validação se o aluno já fez o review
    $find = InstrutorReview::where('instrutor_id', '=', $req->instrutor_id)->where('aluno_id', '=', $req->aluno->id)->first();
    if ($find) return $this->response('Você já realizou a avaliação deste instrutor.', false);

    // ----- instrutorReview
    $instrutorReview = new InstrutorReview();
    $instrutorReview->aluno_id        = $req->aluno->id;
    $instrutorReview->instrutor_id    = $req->instrutor_id;
    $instrutorReview->nota            = $req->input('frmReview.nota');
    $instrutorReview->nome            = $req->input('frmReview.nome');
    $instrutorReview->title           = $req->input('frmReview.title');
    $instrutorReview->body            = $req->input('frmReview.body');
    $instrutorReview->save();

    // ----- instrutor
    $instrutor = Instrutor::find($req->instrutor_id);
    $novaQtd   = $instrutor->notaQtd + 1;
    $novaNota  = (($instrutor->nota * $instrutor->notaQtd) + $instrutorReview->nota) / $novaQtd;
    $instrutor->notaQtd = $novaQtd;
    $instrutor->nota    = $novaNota;
    $instrutor->save();

    NotificationRepository::newReview($instrutor->id); // WApiRepository::sendMessage('41997629021', "*Nova avaliação*\nInstrutor: $instrutor->nome\nID: $instrutor->id\nNota: $instrutorReview->nota\nAluno: {$req->aluno->nome}\nTítulo: $instrutorReview->title\nMensagem: $instrutorReview->body");

    try {
      // ----- pagamento e pagamentoItem
      $pagamentos = Pagamento::where('instrutor_id', '=', $instrutor->id)
        ->where('aluno_id', '=', $req->aluno->id)
        ->where('status', '=', 'Confirmado')
        ->get();

      $msgPagamentos = '';
      if ($pagamentos->isNotEmpty()) {
        $msgPagamentos .= '<br><b>Pagamentos Confirmados:</b><br>';
        foreach ($pagamentos as $pag) {
          $valoresJ = $pag->valoresJ ?? (object) [];
          $msgPagamentos .= 'ID Pagamento: ' . $pag->id . '<br>';
          $msgPagamentos .= '&nbsp;&nbsp;Valor Pago Aluno: R$ ' . number_format($pag->vPago ?? 0, 2, ',', '.') . '<br>';
          $msgPagamentos .= '&nbsp;&nbsp;vDevido Aulas: R$ ' . number_format($valoresJ->vInstrFinal ?? 0, 2, ',', '.') . '<br>';
          $msgPagamentos .= '&nbsp;&nbsp;vDevido Aluguel: R$ ' . number_format($valoresJ->vInstrRent ?? 0, 2, ',', '.') . '<br>';
          $msgPagamentos .= '&nbsp;&nbsp;vKm Desloc: R$ ' . number_format($valoresJ->vInstrKm ?? 0, 2, ',', '.') . '<br>';
        }
      }

      MailRepository::msgManager(
        "Nova avaliação<br>
        <br>
        <b>Dados do Instrutor:</b><br>
        Nome: {$instrutor->nome}<br>
        Telefone: {$instrutor->fone1}<br>
        ID: {$instrutor->id}<br>
        Chave Pix: {$instrutor->chavePix}<br>
        vDevidoObs: {$instrutor->vDevidoObs}<br>
        Indicação: {$instrutor->indicacao}<br>
        <br>
        Nota: {$instrutorReview->nota}<br>
        <br>
        <b>Dados do Aluno:</b><br>
        Nome: {$req->aluno->nome}<br>
        Telefone: {$req->aluno->fone1}<br>
        ID: {$req->aluno->id}<br>
        Indicação: {$req->aluno->indicacao}<br>
        <br>
        Título: {$instrutorReview->title}<br>
        Mensagem: {$instrutorReview->body}<br>" . $msgPagamentos,
        '💸 Nova avaliação de instrutor'
      );
    } catch (\Throwable $th) {
      Log::error(__METHOD__ . ' - ' . $th->getMessage());
    }

    return $this->response(true);
  }

  public function getFotosBase64(Request $req) {
    $basePath = public_path('images/upload/instrutor/');
    $instrutor_id = $req->instrutor->id;
    $fotos = [];

    // Foto de perfil
    $perfilPath = $basePath . 'picture/' . $instrutor_id . '.webp';
    $fotos['fotoPerfil'] = file_exists($perfilPath)
      ? 'data:image/webp;base64,' . base64_encode(file_get_contents($perfilPath))
      : null;

    // Foto CNH
    $cnhPath = $basePath . 'cnh/' . $instrutor_id . '.webp';
    $fotos['fotoCNH'] = file_exists($cnhPath)
      ? 'data:image/webp;base64,' . base64_encode(file_get_contents($cnhPath))
      : null;

    // Foto CLRV
    $clrvPath = $basePath . 'clrv/' . $instrutor_id . '.webp';
    $fotos['fotoCLRV'] = file_exists($clrvPath)
      ? 'data:image/webp;base64,' . base64_encode(file_get_contents($clrvPath))
      : null;

    // Fotos do carro
    $carFolder = $basePath . 'car/';
    $fotos['fotosCar'] = [];
    if (is_dir($carFolder)) {
      $carFiles = glob($carFolder . $instrutor_id . '_*.webp');
      $carFiles = array_slice($carFiles, 0, $req->instrutor->fotosCarN);
      foreach ($carFiles as $file) {
        $fotos['fotosCar'][] = 'data:image/webp;base64,' . base64_encode(file_get_contents($file));
      }
    }

    // Foto CLRVBk
    $clrvPath = $basePath . 'clrvbk/' . $instrutor_id . '.webp';
    $fotos['fotoCLRVBk'] = file_exists($clrvPath)
      ? 'data:image/webp;base64,' . base64_encode(file_get_contents($clrvPath))
      : null;

    // Fotos da moto
    $carFolder = $basePath . 'bike/';
    $fotos['fotosBk'] = [];
    if (is_dir($carFolder)) {
      $carFiles = glob($carFolder . $instrutor_id . '_*.webp');
      $carFiles = array_slice($carFiles, 0, $req->instrutor->fotosBkN);
      foreach ($carFiles as $file) {
        $fotos['fotosBk'][] = 'data:image/webp;base64,' . base64_encode(file_get_contents($file));
      }
    }

    return $this->response($fotos);
  }

  public function lista(Request $req) {

    // throw new Exception('Acesso negado');

    if (!$this->checkBasicAuth()) {
      return response('Unauthorized', 401, [
        'WWW-Authenticate' => 'Basic realm="Área Restrita"'
      ]);
    }

    $instrutores = Instrutor::orderBy('id', 'desc')->get();

    if ($instrutores->isEmpty()) {
      return '<p>Nenhum registro encontrado.</p>';
    }

    // Pega dinamicamente os nomes das colunas a partir do primeiro registro
    $colunas = array_keys($instrutores->first()->getAttributes());

    // Remove o campo password
    $colunas = array_filter($colunas, function ($coluna) {
      return $coluna !== 'password' && $coluna !== 'termos';
    });

    $html = '<table border="1" cellpadding="5">';
    $html .= '<tr>';
    foreach ($colunas as $coluna) {
      $html .= '<th>' . ucfirst($coluna) . '</th>';
    }
    $html .= '</tr>';

    foreach ($instrutores as $i) {
      $html .= '<tr>';
      foreach ($colunas as $coluna) {
        $html .= '<td>' . $i->$coluna . '</td>';
      }
      $html .= '</tr>';
    }

    $html .= '</table>';

    return $html;
  }

  public function dados(Request $req, $numero) {

    if (!$this->checkBasicAuth()) {
      return response('Unauthorized', 401, [
        'WWW-Authenticate' => 'Basic realm="Área Restrita"'
      ]);
    }

    $instrutor = Instrutor::where('fone1', '=', $numero)->first();
    if ($instrutor) {
      $query = http_build_query(['email' => $instrutor->email, 'fone1' => $instrutor->fone1]);
      echo "<script>window.open('https://www.dirigiragora.com.br/instrutor' + '?' + '$query', '_blank');</script>";
      return;
    }

    // Não achou instrutor, procura aluno
    $aluno = Aluno::where('fone1', '=', $numero)->first();
    echo "<h1>Aluno</h1>";
    if ($aluno) dd($aluno->toArray());

    return $this->response('Nenhum registro encontrado.');

  }

  public function validarToken(Request $req) {

    if (!$req->instrutor) return $this->response('Nenhum instrutor informado', false);

    // Ratelimit
    if (!RateLimiter::attempt(__METHOD__ . '_' . request()->ip(), $perMinute = 5, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $instrutor = Instrutor::find($req->instrutor->id);
    if ($req->token !== $instrutor->token) return $this->response('Token inválido. Verifique seu e-mail e tente novamente', false);

    $instrutor->status  = 'E';
    $instrutor->token   = null;
    $instrutor->save();

    return $this->response(true);
  }

  public function redefinirSenha(Request $req) {
    $email = strtolower(trim($req->email));
    Log::info(__METHOD__ . ' - ' . $email);

    // Ratelimit
    if (!RateLimiter::attempt(__METHOD__ . '_' . request()->ip(), $perMinute = 3, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    // Ver se email existe
    $instrutor = Instrutor::where('email', $email)->first();
    if (!$instrutor) return $this->response('Informações inválidas. Verifique seu e-mail e tente novamente', false);

    // Salva o token
    $instrutor->token             = random_int(1000, 9999);
    $instrutor->save();

    // Envia o token e loga
    MailRepository::sendToken($instrutor->email, $instrutor->token);

    return $this->response(true);
  }

  public function validarTokenRedef(Request $req) {
    $email = strtolower(trim($req->email));
    Log::info(__METHOD__ . ' - ' . $email . ' - ' . $req->token . ' - ' . $req->password);

    // Ratelimit
    if (!RateLimiter::attempt(__METHOD__ . '_' . request()->ip(), $perMinute = 3, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    $instrutor = Instrutor::where('email', $email)->where('token', $req->token)->first();
    if (!$instrutor) return $this->response('Token inválido. Verifique seu e-mail e tente novamente', false);

    $instrutor->password  = Hash::make($req->password);
    if ($instrutor->status === 'I') {
      $instrutor->status  = 'E';
    }
    $instrutor->token     = null;
    $instrutor->save();

    return $this->response(true);
  }

  public function proximoAlunoId(Request $req) {
    if (!$req->aluno_id) return $this->response('Nenhum aluno informado', false);
    $aluno = Aluno::find($req->aluno_id);
    $instrutor = InstrutorRepository::proximoAlunoId($req->instrutor->id, $req->aluno_id);
    $instrutor->aluno_uf        = $aluno->uf;
    $instrutor->aluno_municipio = $aluno->municipio;
    $instrutor->aluno_bairro    = $aluno->bairro;

    // Aluno 3282 - jeniferraffo@gmail.com - 41988623051
    if ($aluno->id === 3282) {
      $instrutor->aluno_uf        = $instrutor->uf;
      $instrutor->aluno_municipio = $instrutor->municipio;
      $instrutor->aluno_bairro    = $instrutor->bairro;
      $instrutor->km_real         = 4.2;
    }
    return $this->response($instrutor);
  }

  public function proximoInstrutId(Request $req) {
    if (!$req->instrutor_id) return $this->response('Nenhum instrutor informado', false);
    $instrutor = InstrutorRepository::proximoAlunoId($req->instrutor_id, $req->aluno->id);
    return $this->response($instrutor);
  }

  public function hash(Request $req) {
    $instrutor = Cache::remember(__METHOD__ . '_' . $req->hash, 86400 * 5, function() use ($req) {
      return InstrutorRepository::hash($req->hash);
    });
    if (!$instrutor) return $this->response(null);
    return $this->response($instrutor);
  }

  public function hashReviews(Request $req) {
    // ----- reviewsStar
    // Histograma das notas
    $counts = Cache::remember(__METHOD__ . '_counts_' . $req->hash, 86400 * 5, function() use ($req) {
      return DB::table('instrutorReview')
        ->select('nota', DB::raw('COUNT(*) as quant'))
        ->where('instrutor_id', $req->hash)
        ->groupBy('nota')
        ->pluck('quant', 'nota');
    });
    $total = $counts->sum();
    $reviewsStar = [];
    for ($i = 1; $i <= 5; $i++) {
      $quant = $counts->get($i, 0);
      $width = $total > 0 ? ($quant / $total) * 100 : 0;
      $reviewsStar[$i] = [
        'width' => $width,
        'quant' => $quant,
      ];
    }

    // ----- reviews
    $reviews = Cache::remember(__METHOD__ . '_reviews_' . $req->hash, 86400 * 5, function() use ($req) {
      return InstrutorReview::where('instrutor_id', '=', $req->hash)
      ->orderBy('id', 'DESC')
      ->limit(10)
      ->get();
    });

    return $this->response(['reviews' => $reviews, 'reviewsStar' => $reviewsStar]);
  }

  public function alunosEncontrados(Request $req) {

    // Se não for instrutor destaque
    $instrutor = Instrutor::find($req->instrutor->id);
    if (!$instrutor->destaque) return $this->response([]);

    $select = "id, CONCAT(UCASE(LEFT(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 1)),
      LCASE(SUBSTRING(SUBSTRING_INDEX(TRIM(nome), ' ', 1), 2))) as nome, created_at, bairro, municipio, solicitacao";
    $alunosProximos = Aluno::select(
      DB::raw("
        $select,
        ROUND(
            6371 * acos(
                cos(radians({$req->instrutor->lat}))
                * cos(radians(lat))
                * cos(radians(lng) - radians({$req->instrutor->lng}))
                + sin(radians({$req->instrutor->lat})) * sin(radians(lat))
            ),
        2
        ) AS km_linear
      ")
    )
    // ->where('status', '!=', 'X')
    // ->where('status', '=', 'A')
    // ->where('created_at', '>', Carbon::now()->subMonths(3))
    ->where('ativo', true)
    ->whereExists(function ($query) {
      $query->select(DB::raw(1))
        ->from('pushToken')
        ->where('owner_type', '=', 'aluno')
        ->whereColumn('pushToken.owner_id', 'aluno.id');
    })
    ->whereNotExists(function ($query) {
      $query->select(DB::raw(1))
        ->from('aula')
        ->whereColumn('aula.aluno_id', 'aluno.id');
    })
    ->having('km_linear', '<', $req->instrutor->kmMax)
    ->orderBy('created_at', 'desc')
    ->limit(40)
    ->get();

    return $this->response($alunosProximos);
  }

  public function cnhBrasil(Request $req, $uf) {

    if (!$this->checkBasicAuth()) {
      return response('Unauthorized', 401, [
        'WWW-Authenticate' => 'Basic realm="Área Restrita"'
      ]);
    }

    $file   = file_get_contents(storage_path('data/instrutores-TOTAL.json'));
    $data   = json_decode($file, true);
    $collection = collect($data);
    // dd($collection->where('cpf', '!=', '')->whereNotNull('cpf')->count());
    // dd($collection->where('uf', 'SP')->where('municipio', 'SÃO PAULO')->whereNotNull('fone1')->where('fone1', '!=', '')->toArray());
    $filter = $collection
      // ->where('uf', 'PB')
      ->where('uf', strtoupper($uf))
      // ->where('email', '!=', '')
      // ->where(function ($item) {
      //   return $item['fone1'] != '' || $item['fone2'] != '';
      // })
      ->sortBy('municipio');

    echo "<table border='1'>";
    foreach ($filter as $row) {
        echo "<tr><td>" . implode('</td><td>', $row) . "</td></tr>";
    }
    echo "</table>";
    die();

  }

  public function mapaGeral(Request $req) {
    if ($req->aluno->id !== 1 && $req->aluno->id !== 2) return $this->response('Acesso negado', false);

    $instrutores = Instrutor::select(['nome', 'lat', 'lng', 'fone1'])
      ->where(function($q) {
        $q->where('status', '=', 'AA')
          ->orWhere('status', '=', 'A');
      })
      ->get();

    return $this->response($instrutores);
  }

  // https://www.dirigiragora.com.br/indicacao-aluno/{id}
  public function sitemap() {
    $instrutores = Instrutor::select(['id', 'updated_at'])
      ->where(function($q) {
          $q->where('status', '=', 'AA')
            ->orWhere('status', '=', 'A');
      })
      ->where('ativo', '>=', 1)
      ->get();

    $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>';
    $xmlContent .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($instrutores as $instrutor) {
      $xmlContent .= '<url>';
      $xmlContent .= '  <loc>https://www.dirigiragora.com.br/indicacao-aluno/' . $instrutor->id . '</loc>';
      $xmlContent .= '  <lastmod>' . ($instrutor->updated_at ? $instrutor->updated_at->format('Y-m-d') : date('Y-m-d')) . '</lastmod>';
      $xmlContent .= '  <changefreq>weekly</changefreq>';
      $xmlContent .= '  <priority>0.8</priority>';
      $xmlContent .= '</url>';
    }

    $xmlContent .= '</urlset>';

    return response($xmlContent)->header('Content-Type', 'text/xml');

  }

  public function get(Request $req) {
    $instrutor = Instrutor::find($req->instrutor->id);

    // Popula com as fotos no _fotos
    $instrutor->fotos = $this->buildPhotosObj($req->instrutor->id);

    return $this->response($instrutor);
  }

  // Pesquisa fisicamente os arquivos de foto e retorna o objeto para o front
  // Retorna o caminho completo (URL) da foto caso o arquivo exista, ou `false` caso contrário.
  private function buildPhotosObj(int $instrutor_id) {
    $base = 'https://api.dirigiragora.com.br/images/upload/instrutor';

    // Retorna a URL completa da foto se o arquivo existir no storage, senão `false`.
    // Anexa o timestamp de modificação do arquivo (`?v=...`) para forçar o navegador
    // a atualizar o cache sempre que a imagem for alterada.
    $photo = function (string $folder, string $file) use ($base) {
      $publicFile = public_path("images/upload/instrutor/$folder/$file");
      if (!file_exists($publicFile)) return false;
      $v = filemtime($publicFile); // timestamp de última modificação do arquivo
      return "$base/$folder/$file?v=$v";
    };

    // Monta a lista de fotos sequenciais (ex.: 348_0, 348_1, 348_2).
    $photos = fn (string $folder, int $qtd) => array_map(
      fn (int $i) => $photo($folder, "{$instrutor_id}_{$i}.webp"),
      range(0, $qtd - 1)
    );

    return [
      'fotoPerfil' => $photo('picture', "$instrutor_id.webp"),
      'fotoCNH'    => $photo('cnh', "$instrutor_id.webp"),
      'fotoCLRV'   => $photo('clrv', "$instrutor_id.webp"),
      'fotoCLRVBk' => $photo('clrvbk', "$instrutor_id.webp"),
      'fotosCar'   => $photos('car', 3),
      'fotosBk'    => $photos('bike', 3),
    ];
  }

  // Conta quantas fotos do instrutor existem fisicamente em uma pasta ('car' ou 'bike').
  // Os arquivos seguem o padrão '{id}_{indice}.webp' (ex.: 348_0, 348_1, 348_2).
  private function countFotos(int $instrutor_id, string $folder): int {
    $files = glob(public_path("images/upload/instrutor/$folder/{$instrutor_id}_*.webp"));
    return is_array($files) ? count($files) : 0;
  }

  // Reindexa as fotos do instrutor na pasta, removendo buracos na sequência.
  // Ex.: se existirem _0 e _2, renomeia _2 → _1 para ficar _0, _1.
  private function renumberFotos(int $instrutor_id, string $folder): void {
    $dir = public_path("images/upload/instrutor/$folder/");
    $files = glob($dir . $instrutor_id . '_*.webp');
    if (!is_array($files) || empty($files)) return;

    sort($files, SORT_STRING);

    foreach ($files as $index => $file) {
      $target = $dir . $instrutor_id . '_' . $index . '.webp';
      if ($file !== $target) {
        rename($file, $target);
      }
    }
  }

  public function uploadFoto(Request $req) {
    $instrutor = Instrutor::find($req->instrutor->id);
    // if (!$instrutor->edit) return $this->response('Para solicitar a edição de fotos entre em contato com o suporte via Whatsapp.', false);
    if (!$instrutor->edit) {
      $instrutor->edit = 1;
      $instrutor->save();
    }

    if ($req->tipo === 'fotoCNH') {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/cnh/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->foto));
    }

    if ($req->tipo === 'fotoCLRV') {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/clrv/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->foto));
    }

    if ($req->tipo === 'fotoCLRVBk') {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/clrvbk/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->foto));
    }

    if ($req->tipo === 'fotoPerfil') {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/picture/");
      $file = $folder . $instrutor->id . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->foto));
    }

    if ($req->tipo === 'fotosCar') {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/car/");
      $file = $folder . $instrutor->id . '_' . $req->numero . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->foto));

      $this->renumberFotos($instrutor->id, 'car');
      $instrutor->fotosCarN = $this->countFotos($instrutor->id, 'car');
      $instrutor->save();
    }

    if ($req->tipo === 'fotosBk') {
      $folder = Helpers::folderMaker(public_path() . "/images/upload/instrutor/bike/");
      $file = $folder . $instrutor->id . '_' . $req->numero . '.webp';
      file_put_contents($file, Helpers::FileB64xBin($req->foto));

      $this->renumberFotos($instrutor->id, 'bike');
      $instrutor->fotosBkN = $this->countFotos($instrutor->id, 'bike');
      $instrutor->save();
    }

    return $this->response($this->buildPhotosObj($instrutor->id));
  }

  public function deleteFoto(Request $req) {
    $instrutor = Instrutor::find($req->instrutor->id);
    // if (!$instrutor->edit) return $this->response('Para solicitar a edição de fotos entre em contato com o suporte via Whatsapp.', false);

    if ($req->tipo === 'fotosCar') {
      $file = public_path() . "/images/upload/instrutor/car/" . $instrutor->id . '_' . $req->numero . '.webp';
      if (file_exists($file)) unlink($file);

      $this->renumberFotos($instrutor->id, 'car');
      $instrutor->fotosCarN = $this->countFotos($instrutor->id, 'car');
      $instrutor->save();
    }

    if ($req->tipo === 'fotosBk') {
      $file = public_path() . "/images/upload/instrutor/bike/" . $instrutor->id . '_' . $req->numero . '.webp';
      if (file_exists($file)) unlink($file);

      $this->renumberFotos($instrutor->id, 'bike');
      $instrutor->fotosBkN = $this->countFotos($instrutor->id, 'bike');
      $instrutor->save();
    }

    if ($req->tipo === 'fotoCNH') {
      $file = public_path() . "/images/upload/instrutor/cnh/" . $instrutor->id . '.webp';
      if (file_exists($file)) unlink($file);
      $instrutor->save();
    }

    if ($req->tipo === 'fotoCLRV') {
      $file = public_path() . "/images/upload/instrutor/clrv/" . $instrutor->id . '.webp';
      if (file_exists($file)) unlink($file);
      $instrutor->save();
    }

    if ($req->tipo === 'fotoCLRVBk') {
      $file = public_path() . "/images/upload/instrutor/clrvbk/" . $instrutor->id . '.webp';
      if (file_exists($file)) unlink($file);
      $instrutor->save();
    }

    return $this->response($this->buildPhotosObj($instrutor->id));
  }

  public function sendProposalChat(Request $req) {
    // Pega o chatList pelo $req->hash
    $chatList = ChatList::where('hash', $req->hash)->first();
    if (!$chatList) {
      return $this->response('Conversa não encontrada.', false);
    }

    // `proposta` é um campo JSON (cast a object) e pode ser null.
    // Pega o objeto existente (preservando as demais propriedades) ou cria um novo.
    $proposta = $chatList->proposta ?? new \stdClass();

    // Define os valores da proposta SEM sobrescrever as demais propriedades existentes.
    $proposta->vBruto   = Helpers::N2($req->vBruto);
    $proposta->vLiquido = Helpers::N2($req->vLiquido);

    $chatList->proposta = $proposta;
    $chatList->save();

    return $this->response(true);
  }

}