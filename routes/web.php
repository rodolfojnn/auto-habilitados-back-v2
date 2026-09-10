<?php

use App\Http\Controllers\AlunoRepository;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatRepository;
use App\Http\Controllers\GMapsRepository;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstrutorRepository;
use App\Libraries\Helpers;
use App\Models\Aluno;
use App\Models\AlunoRenach;
use App\Models\ChatList;
use App\Models\ChatMsg;
use App\Models\Instrutor;
use App\Models\InstrutorBlackList;
use App\Models\InstrutorReview;
use App\Models\Pagamento;
use App\Models\PushToken;
use App\Models\SimAluno;
use App\Models\SimPushToken;
use App\Repository\ApnRepository;
use App\Repository\FirebaseRepository;
use App\Repository\AsaasRepository;
use App\Repository\CNHBrasilRepository;
use App\Repository\MailRepository;
use App\Repository\MetaRepository;
use App\Repository\NotificationRepository;
use App\Repository\PushRepository;
use App\Repository\OpenRouterRepository;
use App\Repository\TelegramRepository;
use App\Repository\WApiRepository;
use Carbon\Carbon;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/interface/{base}/{table}',       [HomeController::class, 'getTableMetadata']);
Route::get('/', function () {
  // return view('welcome');
  echo 'APP_URL ' . config('app.APP_URL'). '<br>';
  echo '[' . app()->version() . '] ' . date('d/m/Y H:i:s') . ' - ' . (new \Carbon\Carbon())->getTimezone() . '<br>';
  echo 'REMOTE_ADDR: ' . request()->server('REMOTE_ADDR') . '<br>';
  echo phpversion() . '<br>';
});
Route::get('/email/track/{email}/{tipo}',       function($email, $tipo) {
  Log::channel('email')->info('Email Track: ' . $tipo . ' - ' . $email);
});

Route::get('/instrutorAvatar/{id}', function ($id) {

    $path = public_path("images/upload/instrutor/picture/{$id}.webp");

    if (!file_exists($path)) {
        abort(404);
    }

    $lastModified = filemtime($path);
    $etag = md5_file($path);

    $response = Response::file($path);

    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->headers->set('Cache-Control', 'public, max-age=31536000'); // 1 ano
    $response->headers->set('ETag', $etag);
    $response->headers->set('Last-Modified', gmdate('D, d M Y H:i:s', $lastModified).' GMT');

    return $response;
});






// ------------------------



// http://localhost/jenifer/auto-habilitados-back/public/updateAlunoOnboardSolicitacao
Route::get('/updateAlunoOnboardSolicitacao', function () {
  $alunos = Aluno::whereNull('solicitacao')->whereNotNull('onboard')->get();
  dd($alunos->toArray());

  foreach ($alunos as $aluno) {
    AlunoRepository::updateSolicitacaoByOnboard($aluno->id);
  }
});

// http://localhost/jenifer/auto-habilitados-back/public/msgInternaInstrutores
Route::get('/msgInternaInstrutores', function () {

  $alunoId = 3282; // Aluno que envia a mensagem
  $mensagem = 'Oi, boa noite. Você aceita dar aula em carro de aluno mesmo então né? Já sei dirigir, quero usar meu carro automático mas fazer umas 4 ou 6 aulas no trajeto de prova. Daí posso usar meu carro para prova também? Vi que tem que botar uma faixa só, isso mesmo?';

  // Instrutores que NÃO possuem push token na plataforma
  // (não aparecem na tabela pushToken com owner_type = 'instrutor')
  $instrutoresIdsComPush = PushToken::where('owner_type', 'instrutor')
    ->distinct()
    ->pluck('owner_id');

  $instrutores = Instrutor::whereNotIn('id', $instrutoresIdsComPush)
    ->where(function($q) {
      $q->where('status', '=', 'AA')
        ->orWhere('status', '=', 'A');
    })
    ->get();

  // dd($instrutores->toArray());

  $enviadas = 0;
  foreach ($instrutores as $instrutor) {
    // Cria/atualiza o chatList e registra a chatMsg do aluno para o instrutor
    $ok = ChatRepository::sendMessage('Aluno', $instrutor->id, $alunoId, $mensagem);
    if ($ok) $enviadas++;
    Log::info('Email Send: msgInternaInstrutores - ' . $instrutor->id . ' - ' . $instrutor->email . ' - ' . $enviadas . ' de ' . $instrutores->count());
    sleep(5);
  }

  dd('OK - ' . $enviadas . ' de ' . $instrutores->count() . ' instrutores sem push receberam a mensagem.');
});

// http://localhost/jenifer/auto-habilitados-back/public/emailsFaltantesBlackList
Route::get('/emailsFaltantesBlackList', function () {
  $blacklistGeral = InstrutorBlackList::pluck('email');
  $blacklistFinal = $blacklistGeral
    ->concat($blacklistGeral)
    ->unique()
    ->map(function ($email) {
        return Str::upper(Str::ascii($email));
    })
    ->toArray();
    $lista      = collect(json_decode(file_get_contents(storage_path('data/cnhBrasilNova.json')), true));
    $faltantes = $lista->whereNotIn('email', $blacklistFinal)->whereNotNull('email')->unique('email');
    dd($faltantes);
});

// http://localhost/jenifer/auto-habilitados-back/public/apnTeste
Route::get('/apnTeste', function () {
  // simulado jenifer F90478B0FBBA7D9C334AE50D1105ABACB73E3C95039387452FBEB6F3C9DB2C66
  // digiri jenifer 4108E027BEC0DBA53B83745BA541C36B3FD2E0222F2FB5AEE79D089392DFB37E
  PushRepository::sendNotificationTokens(
    [['token' => 'F90478B0FBBA7D9C334AE50D1105ABACB73E3C95039387452FBEB6F3C9DB2C66', 'platform' => 'ios']],
    'teste url',
    'teste url',
    ['url' => 'https://www.instagram.com/dirigiragora'],
    ['url' => 'https://www.instagram.com/dirigiragora'],
    PushRepository::CANAL_SIMULADO,
  );
});

// http://localhost/jenifer/auto-habilitados-back/public/testeEmail
Route::get('/testeEmail', function () {
  $email = 'rodolfojnnogueira@gmail.com';
  Mail::mailer('brevo')->send('mail.instrutor-nova-aula', ['email' => $email], function ($m) use ($email) {
    $m->to($email)->subject('Você vendeu uma nova aula no Dirigir Agora!');
  });
});


// http://localhost/jenifer/auto-habilitados-back/public/emailNovaListaCNHBrasil
Route::get('/emailNovaListaCNHBrasil', function () {

  $listaRaw = json_decode(file_get_contents(storage_path('data/cnhBrasilNova.json')), true);
  $find = collect($listaRaw)->filter(fn($item) => str_contains($item['telefone1'] ?? '', '9740059'))->values();
  dd($find);


  // 1. Carrega a Blacklist garantindo que seja um array plano de strings em minúsculo
  $blackListRaw = json_decode(file_get_contents(storage_path('data/emailBlackList.json')), true);
  $blackList = collect($blackListRaw)->pluck('email')->map(fn($email) => strtolower(trim($email)))->toArray();

  // 2. Busca e-mails dos instrutores padronizados em minúsculo
  $emailsInstrutores = Instrutor::whereNotNull('email')->pluck('email')->map(fn($email) => strtolower(trim($email)))->toArray();

  // 3. Processa a lista principal
  $listaRaw = json_decode(file_get_contents(storage_path('data/cnhBrasilNova.json')), true);

  $lista = collect($listaRaw)
    // Aplica o seu filtro de validação de estrutura e regras de negócio do e-mail
    ->where('enderecoMunicipioNome', '=', 'SANTANA')
    ->filter(function ($item) {
      // Extrai o e-mail do item da lista
      $email = $item['email'] ?? null;

      if (empty($email)) return false;

      // Força minúsculo para os testes de texto ficarem mais seguros (case-insensitive)
      $emailMinusculo = strtolower(trim($email));

      if (!filter_var($emailMinusculo, FILTER_VALIDATE_EMAIL)) return false;

      // if (str_ends_with($emailMinusculo, '@hotmail.com')) return false;
      if (str_contains($emailMinusculo, 'autoescola') || str_contains($emailMinusculo, 'cfc')) return false;
      if (str_contains($emailMinusculo, 'xxxxx')) return false;
      if (str_contains($emailMinusculo, '00000')) return false;

      // Validação do tamanho do usuário (antes do @)
      $usuario = explode('@', $emailMinusculo)[0];
      if (strlen($usuario) < 5) return false;

      return true;
    })
    // Aplica o filtro da Blacklist e dos Instrutores já existentes
    ->reject(function ($item) use ($emailsInstrutores, $blackList) {
      $emailFormatado = strtolower(trim($item['email']));

      return in_array($emailFormatado, $emailsInstrutores) || in_array($emailFormatado, $blackList);
    })
    ->values()
    ->unique('email')
    ->pluck('email')
    // ->slice(993, 1000)
    ->values()
    ->toArray();

  dd($lista);

  foreach ($lista as $key => $email) {

    try {
      $email = strtolower(trim($email));
      // $email = 'rodolfojnnogueira@gmail.com';
      $enviado = Mail::mailer('zeptomail')->send('mail.instrutor-uf-municipio-v4', ['municipio' => 'Santana', 'uf' => 'AP'],
        function ($m) use ($email) {
          $m->to(strtolower($email))->subject('Você dá aula prática de primeira habilitação?');
        }
      );
      if ($enviado) Log::channel('email')->info('Email Send: instrutor-uf-municipio-v4 - ' . $email . ' - ' . $key);
      // dd(123);
    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }

  }
});

// http://localhost/jenifer/auto-habilitados-back/public/email-aluno-renach-dirigir
Route::get('/email-aluno-renach-dirigir', function () {

  $regiaoMap = [
    'TARUMA - CURITIBA' => 'Curitiba',
    'VILA HAUER-CTBA' => 'Curitiba',
    'COLOMBO - PST AVAN' => 'Colombo',
    'S.JOSE PINHAIS' => 'São José dos Pinhais',
    'C.GRANDE DO SUL' => 'Campina Grande do Sul',
    'ARAUCARIA' => 'Araucária',
    'CASCAVEL' => 'Cascavel',
    'CAMPO LARGO' => 'Campo Largo',
    'PONTA GROSSA' => 'Ponta Grossa',
    'APUCARANA' => 'Apucarana',
    'NOVA AURORA' => 'Nova Aurora',
    'LONDRINA PS CENTRAL' => 'Londrina',
    'LONDRINA - VILA YARA' => 'Londrina',
    'MARINGA - CONTOR SUL' => 'Maringá',
    'MARINGA - PS CENTRAL' => 'Maringá',
  ];

  $alunoRenach = AlunoRenach::select(['id', 'email', 'nome', 'utr'])
    ->where('id', '>', 1196)
    ->whereNotNull('email')
    ->where('email', '!=', 'Não encontrado')
    // ->where(function ($query) {
    //   $query->orWhere('utr', '=', 'VILA HAUER-CTBA')
    //     ->orWhere('utr', '=', 'TARUMA - CURITIBA')
    //     ->orWhere('utr', '=', 'COLOMBO - PST AVAN')
    //     ->orWhere('utr', '=', 'S.JOSE PINHAIS')
    //     ->orWhere('utr', '=', 'C.GRANDE DO SUL')
    //     ->orWhere('utr', '=', 'ARAUCARIA')
    //     ->orWhere('utr', '=', 'CASCAVEL')
    //     ->orWhere('utr', '=', 'CAMPO LARGO')
    //     ->orWhere('utr', '=', 'PONTA GROSSA')
    //     ->orWhere('utr', '=', 'APUCARANA')
    //     ->orWhere('utr', '=', 'NOVA AURORA')
    //     ->orWhere('utr', '=', 'LONDRINA PS CENTRAL')
    //     ->orWhere('utr', '=', 'LONDRINA - VILA YARA')
    //     ->orWhere('utr', '=', 'MARINGA - CONTOR SUL')
    //     ->orWhere('utr', '=', 'MARINGA - PS CENTRAL');
    // })
    ->get();

  // $alunoRenach->each(function ($it) use ($regiaoMap) {
  //   $it->utr = $regiaoMap[$it->utr];
  // });

  dd($alunoRenach->toArray());

  foreach ($alunoRenach as $key => $it) {
    try {

      $email = strtolower(trim($it->email));
      // $email = 'rodolfojnnogueira@gmail.com';

      // Dirigir
      $enviado = Mail::mailer('zeptomail')->send('mail.aluno-renach-dirigir', ['utr' => $it->utr],
        function ($m) use ($email) {
          $m->to(strtolower($email))->subject('Próxima etapa CNH: Aulas Práticas');
        }
      );
      if ($enviado) Log::channel('email')->info('Email Send: mail.aluno-renach-dirigir - ' . $it->id . ' - ' . $it->email . ' - ' . $key);

      // // Simulado
      // $enviado = Mail::mailer('zeptomail')->send('mail.aluno-renach-simulado', ['utr' => $it->utr],
      //   function ($m) use ($email) {
      //     $m->from('comercial@dirigiragora.com.br', 'Simulado CNH do Brasil 2026');
      //     $m->to(strtolower($email))->subject('Simulado para a sua prova teórica');
      //   }
      // );
      // if ($enviado) Log::channel('email')->info('Email Send: mail.aluno-renach-simulado - ' . $it->id . ' - ' . $it->email . ' - ' . $key);

      // dd(123);

    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }
  }
});

// http://localhost/jenifer/auto-habilitados-back/public/email-aluno-renach-simulado
Route::get('/email-aluno-renach-simulado', function () {

  $regiaoMap = [
    'TARUMA - CURITIBA' => 'Curitiba',
    'VILA HAUER-CTBA' => 'Curitiba',
    'COLOMBO - PST AVAN' => 'Colombo',
    'S.JOSE PINHAIS' => 'São José dos Pinhais',
    'C.GRANDE DO SUL' => 'Campina Grande do Sul',
    'ARAUCARIA' => 'Araucária',
    'CASCAVEL' => 'Cascavel',
    'CAMPO LARGO' => 'Campo Largo',
    'PONTA GROSSA' => 'Ponta Grossa',
    'APUCARANA' => 'Apucarana',
    'NOVA AURORA' => 'Nova Aurora',
    'LONDRINA PS CENTRAL' => 'Londrina',
    'LONDRINA - VILA YARA' => 'Londrina',
    'MARINGA - CONTOR SUL' => 'Maringá',
    'MARINGA - PS CENTRAL' => 'Maringá',
  ];

  $alunoRenach = AlunoRenach::select(['id', 'email', 'nome', 'utr'])
    ->where('id', '>', 2167)
    ->whereNotNull('email')
    ->where('email', '!=', 'Não encontrado')
    // ->where(function ($query) {
    //   $query->orWhere('utr', '=', 'VILA HAUER-CTBA')
    //     ->orWhere('utr', '=', 'TARUMA - CURITIBA')
    //     ->orWhere('utr', '=', 'COLOMBO - PST AVAN')
    //     ->orWhere('utr', '=', 'S.JOSE PINHAIS')
    //     ->orWhere('utr', '=', 'C.GRANDE DO SUL')
    //     ->orWhere('utr', '=', 'ARAUCARIA')
    //     ->orWhere('utr', '=', 'CASCAVEL')
    //     ->orWhere('utr', '=', 'CAMPO LARGO')
    //     ->orWhere('utr', '=', 'PONTA GROSSA')
    //     ->orWhere('utr', '=', 'APUCARANA')
    //     ->orWhere('utr', '=', 'NOVA AURORA')
    //     ->orWhere('utr', '=', 'LONDRINA PS CENTRAL')
    //     ->orWhere('utr', '=', 'LONDRINA - VILA YARA')
    //     ->orWhere('utr', '=', 'MARINGA - CONTOR SUL')
    //     ->orWhere('utr', '=', 'MARINGA - PS CENTRAL');
    // })
    ->get();

  // $alunoRenach->each(function ($it) use ($regiaoMap) {
  //   $it->utr = $regiaoMap[$it->utr];
  // });

  dd($alunoRenach->toArray());

  foreach ($alunoRenach as $key => $it) {
    try {

      $email = strtolower(trim($it->email));
      // $email = 'rodolfojnnogueira@gmail.com';

      // Simulado
      $enviado = Mail::mailer('zeptomail')->send('mail.aluno-renach-simulado', [],
        function ($m) use ($email) {
          $m->from('comercial@dirigiragora.com.br', 'Simulado CNH do Brasil 2026');
          $m->to(strtolower($email))->subject('Simulado para a sua prova teórica');
        }
      );
      if ($enviado) Log::channel('email')->info('Email Send: mail.aluno-renach-simulado - ' . $it->id . ' - ' . $it->email . ' - ' . $key);

      // dd(123);

    } catch (\Throwable $th) {
      Log::channel('email')->error($th->getMessage());
    }
  }
});

// // http://localhost/jenifer/auto-habilitados-back/public/consolidarNovaListaCNHBrasil
// Route::get('/consolidarNovaListaCNHBrasil', function () {
//     // Caminho da pasta onde estão os arquivos
//     $directoryPath = 'C:\\Users\\rodol\\Desktop';
//     $outputFileName = 'cnhBrasilNova.json';
//     $outputPath = $directoryPath . DIRECTORY_SEPARATOR . $outputFileName;

//     // 1. Verifica se o diretório existe para evitar erros
//     if (!File::isDirectory($directoryPath)) {
//         return response()->json(['error' => 'Diretório não encontrado.'], 404);
//     }

//     // 2. Coleta todos os arquivos da pasta
//     $files = File::files($directoryPath);

//     // 3. Processa e unifica os dados
//     $resultadoConsolidado = collect($files)
//         // Filtra para pegar apenas arquivos .json e ignora o arquivo de saída gerado por essa rota
//         ->filter(function ($file) use ($outputFileName) {
//             return $file->getExtension() === 'json' && $file->getFilename() !== $outputFileName;
//         })
//         // Mapeia e decodifica o conteúdo de cada arquivo
//         ->flatMap(function ($file) {
//             $content = file_get_contents($file->getRealPath());
//             $dados = json_decode($content, true); // 'true' para transformar em array associativo

//             // Garante que o retorno seja um array para o flatMap funcionar corretamente
//             return is_array($dados) ? $dados : [];
//         })
//         ->values() // Reseta as chaves do array final para indexação sequencial (0, 1, 2...)
//         ->toArray();

//     // 4. Salva o resultado consolidado no arquivo final com formatação limpa
//     file_put_contents($outputPath, json_encode($resultadoConsolidado));

//     // Retorna uma resposta de sucesso na tela
//     return response()->json([
//         'message' => 'Arquivos consolidados com sucesso!',
//         'total_registros' => count($resultadoConsolidado),
//         'caminho' => $outputPath
//     ]);
// });

// // http://localhost/jenifer/auto-habilitados-back/public/testeGMaps
// Route::get('/testeGMaps', function () {
//   dd(GMapsRepository::distanceKm(-25.4284, -49.2733, -23.564, -46.653));
// });

// // http://localhost/jenifer/auto-habilitados-back/public/fakeMsgAlunoInstrutor
// Route::get('/fakeMsgAlunoInstrutor', function () {
//   $aluno = AlunoRepository::getById(1);
//   $request = new HttpRequest([
//     'aluno'         => $aluno,
//     'receiver_id'   => 5,
//     'conteudo'      => 'Teste unitário'
//   ]);
//   $controller = new ChatController();
//   $response = $controller->postChatMessage($request);
// });

// // http://localhost/jenifer/auto-habilitados-back/public/testePagamento
// Route::get('/testePagamento', function () {
//   $pagamentos = Pagamento::with('pagamentoItem', 'aluno')
//     ->select('id', 'aluno_id', 'forma', 'parcelas', 'categoria', 'pacote', 'rent', 'valoresJ')
//     ->where('instrutor_id', '=', 167)
//     ->where('status', '=', 'Confirmado')
//     ->get();
//   return dd($pagamentos->toArray());
//   // return dd(json_encode($pagamentos->toArray()));
// });

// // http://localhost/jenifer/auto-habilitados-back/public/notificationTeste
// Route::get('/notificationTeste', function () {

//   // NotificationRepository::newInstrutorRegiao(-3.82721330, -38.51418280, 25);

//   // $aluno_ids = ChatMsg::select('aluno_id')->get()->pluck('aluno_id')->unique()->values();
//   // foreach ($aluno_ids as $aluno_id) {
//   //   NotificationRepository::newChatMsg('Aluno', $aluno_id);
//   // }
// });

// // http://localhost/jenifer/auto-habilitados-back/public/valoresInstrutores
// Route::get('/valoresInstrutores', function () {
//   $lista = collect(Helpers::csvToJsonV2(file_get_contents('C:\Users\rodol\Desktop\vcarown.csv')))->toArray();
//   foreach ($lista as $item) {
//     if (!$item->vCarOwn) continue;
//     $instrutor = Instrutor::find($item->id);
//     $instrutor->vCarOwn = $item->vCarOwn;
//     $instrutor->save();
//   }
// });

// // http://localhost/jenifer/auto-habilitados-back/public/proximoAlunoId
// Route::get('/proximoAlunoId', function () {
//   dd(InstrutorRepository::proximoAlunoId(1, 1));
// });

// // http://localhost/jenifer/auto-habilitados-back/public/notificationTeste
// Route::get('/notificationTeste', function () {
//   NotificationRepository::newChatMsg('Aluno', 1);
// });

// // http://localhost/jenifer/auto-habilitados-back/public/telegramTeste
// Route::get('/telegramTeste', function () {

//   // dd((string) Str::ulid());

//   // $threadId = TelegramRepository::createChatTopic(348, 1);
//   // dd($threadId);

//   // TelegramRepository::sendMessageToTopic(
//   //   3,
//   //   'Mensagem de teste no tópico',
//   //   'Aluno'
//   // );

//   // TelegramRepository::sendMessageToTopic(28, 'Aluno: Oi bom dia');

//   // https://api.dirigiragora.com.br/v1/telegram/webhook
//   // dd(TelegramRepository::setWebhook('https://api.dirigiragora.com.br/v1/telegram/webhook'));

// });

// // http://localhost/jenifer/auto-habilitados-back/public/firebaseTeste
// Route::get('/firebaseTeste', function () {
//   $token1 = 'chuI4TuGRQKwLmjUmtOayY:APA91bEVRn7yf0Z8EdezrcJZi15mfrUgr1471X3xxKWpquGO1xfq6_nxumzs94OhGORozO63HpGZkbSeai40Pah7l9YXs9kBKOFNsvvcixuw2HSw-hsJF1U';
//   // $token2 = 'frcFm-uCRtanaWynw4mUMi:APA91bGj5Y9YkLnvESGlLuH1IoqSKfYXL2lH-iRG1Z2C6xWxlFYXOKP8tQWOtyPGfVABS6AFjG6WBixkyGKWJJX1N3ukLgrrQWD6spF9Pb_pRAZrnPkbkls';

//   // FirebaseRepository::sendNotificationToken($token2, 'Assunto', 'Mensagem');
//   FirebaseRepository::sendNotificationTokens([$token1] , 'Mudou de telefone?', 'Alvani, não estamos conseguindo contato com você. Sobre uma aluna interessada em aulas com você.');

// });

// // http://localhost/jenifer/auto-habilitados-back/public/resetSenha
// Route::get('/resetSenha', function () {
//   $aluno = Aluno::find(2349);
//   $aluno->password = Hash::make($aluno->fone1);
//   $aluno->save();

//   // $instrutor = Instrutor::find(895);
//   // $instrutor->password = Hash::make($instrutor->fone1);
//   // $instrutor->save();

//   // $instrutor = Instrutor::find(644);
//   // $instrutor->password = Hash::make('CFCwesley8229@');
//   // $instrutor->save();
// });

// http://localhost/jenifer/auto-habilitados-back/public/emailBlackList
Route::get('/emailBlackList', function () {

  // $blackList = collect(json_decode(file_get_contents('C:\Users\rodol\Desktop\emailBlackList.json')))->toArray();
  // dd($blackList);

  // $csvBrevo = collect(Helpers::csvToJsonV2(file_get_contents('C:\Users\rodol\Desktop\brevo.csv')))->pluck('email')->toArray();
  // $csvZepto1 = collect(Helpers::csvToJsonV2(file_get_contents('C:\Users\rodol\Desktop\zepto1.csv')))->pluck('TO')->toArray();
  // $csvZepto2 = collect(Helpers::csvToJsonV2(file_get_contents('C:\Users\rodol\Desktop\zepto2.csv')))->pluck('TO')->toArray();
  // $csvZepto3 = collect(Helpers::csvToJsonV2(file_get_contents('C:\Users\rodol\Desktop\zepto3.csv')))->pluck('TO')->toArray();
  // $csvSendpulse = collect(Helpers::csvToJsonV3(file_get_contents('C:\Users\rodol\Desktop\sendpulse.csv')), ';')->pluck('Recepient')->toArray();
  // file_put_contents('C:\Users\rodol\Desktop\emailBlackList.json', json_encode(array_merge($csvBrevo, $csvZepto1, $csvZepto2, $csvZepto3, $csvSendpulse)));
});

// http://localhost/jenifer/auto-habilitados-back/public/emailSender
Route::get('/emailSender', function () {

  // // Instrutores - instrutor-uf-liberada
  // $instrutores = Instrutor::whereNotNull('email')
  //   ->where('uf', '=', 'SP')
  //   ->get();
  // // dd($instrutores->toArray());

  // foreach ($instrutores as $instrutor) {
  //   $email = $instrutor->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.instrutor-uf-liberado', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('NOTÍCIA URGENTE sobre prova prática no estado de São Paulo.');
  //   });
  //   // dd($enviado);
  //   if ($enviado) Log::info('Email Send: instrutor-uf-liberado - ' . $email);
  // }

  // // Alunos - aluno-uf-liberada
  // $alunos = Aluno::whereNotNull('email')
  //   ->where('uf', '=', 'SP')
  //   ->get();
  // // dd($alunos->toArray());
  // foreach ($alunos as $aluno) {
  //   $email = $aluno->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.aluno-uf-liberada', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('URGENTE HOJE: Detran-SP libera agendamento de exame prático sem autoescola!');
  //   });
  //   // dd($enviado);
  //   if ($enviado) Log::info('Email Send: aluno-uf-liberada - ' . $email);
  // }

  // $email = 'rodolfojnnogueira@gmail.com';
  // Mail::mailer('sendpulse')->send('mail.chat-mensagem', ['email' => $email], function ($m) use ($email) {
  //   $m->to($email)->subject('Você recebeu uma mensagem no DirigirAgora');
  // });
  // dd($email);

  // $enviado = Mail::mailer('sendpulse')->send('mail.chat-mensagem', ['email' => 'rodolfojnn@gmail.com'], function ($m) {
  //   $m->bcc(['rodolfojnn@gmail.com'])->subject('Você recebeu uma mensagem no DirigirAgora');
  // });
  // dd($enviado);

  // // HUBCNH
  // $file       = collect(json_decode(file_get_contents('C:\Users\rodol\Desktop\dirigir\datasets\hubcnh_instrutores.json')))
  //   ->unique()
  //   // ->slice(39, 200) // lucianopaiva901@gmail.com - 29
  //   ->toArray();

  // foreach ($file as $key => $email) {
  //   try {
  //     // $email = 'rodolfojnnogueira@gmail.com';
  //     $enviado = Mail::send('mail.instrutor-uf-municipio-v2', ['email' => $email], function ($m) use ($email) {
  //       $m->to($email)->subject('Instrutor, você está atuando com primeira habilitação?');
  //     });
  //     if ($enviado) Log::channel('email')->info('Email Send: instrutor-uf-municipio-v2 - ' . $email . ' - ' . $key);
  //     // dd(123);
  //     // sleep(130);
  //   } catch (\Throwable $th) {
  //     Log::channel('email')->error($th->getMessage());
  //   }
  // }

  // // INSTRUTORES ANDRÉ LISTA
  // $emails       = collect(json_decode(file_get_contents('C:\Users\rodol\Desktop\dirigir\datasets\instrutores_andre.json')))->unique()->pluck('E-MAIL')->toArray();
  // // dd($emails);
  // foreach ($emails as $key => $email) {
  //   // dd($email);
  //   try {
  //     // $email = 'rodolfojnn@gmail.com';
  //     // $email = 'andremicolino@gmail.com';
  //     // $email = 'jeniferraffo@gmail.com';
  //     $enviado = Mail::send('mail.instrutores-andre', ['email' => $email], function ($m) use ($email) {
  //       $m->to($email)->subject('Você atende primeira habilitação?');
  //     });
  //     if ($enviado) Log::channel('email')->info('Email Send: instrutores-andre- ' . $email . ' - ' . $key);
  //     // dd(123);
  //     // sleep(random_int(70, 90)); // Limite Sendpulse 50 por hora (80 segundos)
  //     // usleep(250000); // 250ms
  //   } catch (\Throwable $th) {
  //     Log::channel('email')->error($th->getMessage());
  //   }
  // }

  // // LISTA GERAL
  // $hubCnh       = collect(json_decode(file_get_contents('C:\Users\rodol\Desktop\dirigir\datasets\hubcnh_instrutores.json')))->unique()->pluck('email')->toArray();
  // $blacklist    = collect(json_decode(file_get_contents('C:\Users\rodol\Desktop\dirigir\datasets\emailBlackList.json')))->unique()->toArray();
  // $dirigirMails = collect(json_decode(file_get_contents('C:\Users\rodol\Desktop\dirigir\datasets\dirigirAgora_emails.json')))->unique()->toArray();
  // $file       = file_get_contents('C:\Users\rodol\Desktop\dirigir\datasets\instrutores-TOTAL.json');
  // $data       = json_decode($file, true);
  // $emails     = collect($data)
  //   ->pluck('email')
  //   ->unique()
  //   ->merge($hubCnh)
  //   ->map(function ($item) {
  //       if (isset($item)) $item = strtolower(trim($item));
  //       return $item;
  //   })
  //   ->filter(function ($item) {
  //       $email = $item ?? null;
  //       if (empty($email)) return false;
  //       if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
  //       // if (str_ends_with(strtolower($email), '@hotmail.com')) return false;
  //       if (str_contains($email, 'autoescola') || str_contains($email, 'cfc')) return false;
  //       if (str_contains($email, 'xxxxx')) return false;
  //       if (str_contains($email, '00000')) return false;
  //       $usuario = explode('@', $email)[0]; // Pega tudo antes do @
  //       if (strlen($usuario) < 5) return false;
  //       return true;
  //   })
  //   ->sort()
  //   ->diff($blacklist)
  //   ->diff($dirigirMails)
  //   // ->slice(0, 100)
  //   ->values();
  // dd($emails->toArray());
  // foreach ($emails as $key => $email) {
  //   try {
  //     // $email = 'rodolfojnnogueira@gmail.com';
  //     $enviado = Mail::send('mail.instrutor-uf-municipio-v3', ['email' => $email], function ($m) use ($email) {
  //       $m->to($email)->subject('Receba seus adesivos imantados "autoescola"');
  //     });
  //     if ($enviado) Log::channel('email')->info('Email Send: instrutor-uf-municipio-v3 - ' . $email . ' - ' . $key);
  //     // dd(123);
  //     // sleep(random_int(70, 90)); // Limite Sendpulse 50 por hora (80 segundos)
  //     // usleep(250000); // 250ms
  //   } catch (\Throwable $th) {
  //     Log::channel('email')->error($th->getMessage());
  //   }
  // }

  // // Instrutores - UF liberado
  // $instrutores = Instrutor::whereIn('uf', ['SP'])
  //   ->where('status', '!=', 'AA')
  //   ->get();                                                                                                                             // dd($instrutores->toArray());
  // foreach ($instrutores as $instrutor) {
  //   $email = $instrutor->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.instrutor-uf-liberado', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('LIBERADO HOJE: VALIDAÇÃO DE AULAS PARA AUTÔNOMOS - sem CPF vinculado CFC');
  //   });
  //   if ($enviado) Log::info('Email Send: aluno-etapa-liberada - ' . $email);
  //   // dd(123);
  // }

  // // Instrutores - instrutor-whatsapp-offline
  // $instrutores = Instrutor::whereNotNull('email')
  //   ->where('id', '>', 334)
  //   ->get();                                                                                                          // dd($instrutores->toArray());
  // foreach ($instrutores as $instrutor) {
  //   $email = $instrutor->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.instrutor-whatsapp-offline', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('ALERTA DIRIGIR AGORA');
  //   });
  //   if ($enviado) Log::info('Email Send: instrutor-whatsapp-offline - ' . $email);
  //   // dd(123);
  // }

  // // Instrutores - UF - Município
  // $instrutores = Instrutor::where('uf', '=', 'SP')
  //   ->where('status', '!=', 'AA')
  //   // ->where('municipio', '=', 'São Paulo')
  //   ->get()
  //   ->unique('email')
  //   ->values();                                                                                                                          // dd($instrutores->toArray());
  // foreach ($instrutores as $instrutor) {
  //   $email = $instrutor->email;
  //   $enviado = Mail::send('mail.instrutor-uf-municipio', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('URGENTE EM SÃO PAULO!');
  //   });
  //   if ($enviado) Log::info('Email Send: instrutor-uf-municipio - ' . $email);
  // }

  // // Instrutores - Cadastro Ativo
  // $instrutores = Instrutor::where('status', '=', 'A')
  //   ->whereNotNull('finished_at')
  //   // ->where('id', '>', 316)
  //   ->get()
  //   ->unique('email')
  //   ->values();                                                                                                                          // dd($instrutores->toArray());
  // foreach ($instrutores as $instrutor) {
  //   $email = $instrutor->email;
  //   $enviado = Mail::send('mail.instrutor-cadastro-ativo', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('Ação necessária: conclua a última etapa para ser instrutor(a) autônomo(a) em nossa plataforma!');
  //   });
  //   if ($enviado) Log::info('Email Send: instrutor-cadastro-ativo - ' . $email);
  // }

  // // Instrutores - Inativos Cadastro Incompleto
  // $instrutores = Instrutor::where('status', '=', 'I')
  //   ->whereNull('finished_at')
  //   ->where('id', '>', 321)
  //   ->get()
  //   ->unique('email')
  //   ->values();                                                                                                                          // dd($instrutores->toArray());
  // foreach ($instrutores as $instrutor) {
  //   $email = $instrutor->email;
  //   $enviado = Mail::send('mail.instrutor-cadastro-incompleto', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('Conclua seu cadastro, instrutor(a) credenciado(a).');
  //   });
  //   if ($enviado) Log::info('Email Send: instrutor-cadastro-incompleto - ' . $email);
  // }

  // // Instrutores - Inativos Cadastro Completo
  // $instrutores = Instrutor::where('status', '=', 'S')
  //   ->whereNotNull('finished_at')
  //   ->where('id', '>', 306)
  //   ->get()
  //   ->unique('email')
  //   ->values();                                                                                                                             // dd($instrutores->toArray());
  // foreach ($instrutores as $instrutor) {
  //   $email = $instrutor->email;
  //   $enviado = Mail::send('mail.instrutor-completo-inativo', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('Ação necessária: instrutor sem credenciamento SENATRAN');
  //   });
  //   if ($enviado) Log::info('Email Send: instrutor-completo-inativo - ' . $email);
  // }

  // // Alunos - aluno-whatsapp-offline
  // $alunos = Aluno::where('status', '=', 'A')
  //   ->whereNotNull('email')
  //   ->where('id', '>', 1)
  //   ->get();                                                                                                                                  // dd($alunos->toArray());
  // foreach ($alunos as $aluno) {
  //   $email = $aluno->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.aluno-whatsapp-offline', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('ALERTA DIRIGIR AGORA');
  //   });
  //   if ($enviado) Log::info('Email Send: aluno-whatsapp-offline - ' . $email);
  //   // dd(123);
  // }

  // // Alunos - Data Feriado
  // $alunos = Aluno::get();                                                                                                                    // dd($alunos->toArray());
  // foreach ($alunos as $aluno) {
  //   $email = $aluno->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.aluno-data-feriado', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('Saiba o que fazer para não precisar pagar taxa Detran em sua CNH!');
  //   });
  //   if ($enviado) Log::info('Email Send: aluno-data-feriado - ' . $email);
  //   // dd(123);
  // }

  // // Alunos - Inicio Processo CNH
  // $alunos = Aluno::where('uf', 'SP')
  //   ->where('id', '!=', 69)
  //   ->where('id', '>', 30)
  //   ->get();                                                                                                                              // dd($alunos->toArray());
  // foreach ($alunos as $aluno) {
  //   $email = $aluno->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.aluno-inicio-processo-cnh', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('Inicie seu processo de CNH sem autoescola!');
  //   });
  //   if ($enviado) Log::info('Email Send: aluno-inicio-processo-cnh - ' . $email);
  //   // dd(123);
  // }

  // // Alunos - App CNH do Brasil
  // $alunos = Aluno::whereIn('uf', ['GO', 'MG', 'SP', 'PR', 'RJ', 'SC', 'ES', 'PA'])
  //   ->where('id', '>', 189)
  //   ->get();                                                                                                                              // dd($alunos->toArray());
  // foreach ($alunos as $aluno) {
  //   $email = $aluno->email;
  //   $enviado = Mail::send('mail.aluno-app-cnh-brasil', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('Inicie o processo de sua CNH do Brasil!');
  //   });
  //   if ($enviado) Log::info('Email Send: aluno-app-cnh-brasil - ' . $email);
  // }

  // // Alunos - Adequação Detran
  // $alunos = Aluno::whereNotIn('uf', ['GO', 'MG', 'SP', 'PR', 'RJ', 'SC', 'ES', 'PA'])
  //   ->where('id', '>', 183)
  //   ->get();                                                                                                                             // dd($alunos->toArray());
  // foreach ($alunos as $aluno) {
  //   $nome = $aluno->nome;
  //   $email = $aluno->email;
  //   $primeiroNome = explode(' ', $nome)[0];
  //   $enviado = Mail::send('mail.aluno-adequacao-detran', ['nome' => $primeiroNome, 'email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('Aviso importante!');
  //   });
  //   if ($enviado) Log::info('Email Send: aluno-adequacao-detran - ' . $email);
  // }

  // // Alunos - Etapa liberada
  // $alunos = Aluno::whereIn('uf', ['RJ'])
  //   ->where('id', '>', 0)
  //   ->get();                                                                                                                             // dd($alunos->toArray());
  // foreach ($alunos as $aluno) {
  //   $email = $aluno->email;
  //   // $email = 'rodolfojnnogueira@gmail.com';
  //   $enviado = Mail::send('mail.aluno-etapa-liberada', ['email' => $email], function ($m) use ($email) {
  //     $m->to($email)->subject('ETAPA LIBERADA NO RJ!');
  //   });
  //   if ($enviado) Log::info('Email Send: aluno-etapa-liberada - ' . $email);
  //   // dd(123);
  // }


  // $nome = 'Rodolfo';
  // $email = 'rodolfojnnogueira@gmail.com';
  // $primeiroNome = explode(' ', $nome)[0];
  // $enviado = Mail::send('mail.adequacao-detran', ['nome' => $primeiroNome, 'email' => $email], function ($m) use ($email) {
  //   $m->to($email)->subject('Aviso importante!');
  // });

});


// // http://localhost/jenifer/auto-habilitados-back/public/instrutoresIndicacao
// Route::get('/instrutoresIndicacao', function () {
//   $instrutores = Instrutor::where('status', '=', 'AA')
//     ->orderBy('fone1')
//     ->get();
//   // dd($instrutores->count());
//   $urlAluno     = 'https://www.dirigiragora.com.br/indicacao-aluno/';
//   $urlInstrutor = 'https://www.dirigiragora.com.br/indicacao-instrutor/';
//   foreach ($instrutores as $instrutor) {
//     $id = $instrutor->id;
//     echo $instrutor->fone1 . ' <br> ' . $urlAluno . $id . '/' . Str::slug($instrutor->nome) . '<br>';
//     // echo $urlInstrutor . $id . '/' . Str::slug($instrutor->nome) . '<br><br>';
//     echo '<br>';
//   }
// });


// // http://localhost/jenifer/auto-habilitados-back/public/asaasTeste
// Route::get('/asaasTeste', function () {

//   // dd(AsaasRepository::getTransfers());

//   $reference_id = (int) floor(microtime(true) * 100);
//   $res          = AsaasRepository::postTransferPix($reference_id, 0.02, '03663888940', 'CPF', 'Transação Pix Teste 2');
//   dd($res);

//   // $res = AsaasRepository::getListInstallments('04053ed0-4e4c-420d-ac4d-e842a08c674b');
//   // dd($res);
// });

// http://localhost/jenifer/auto-habilitados-back/public/proximosLinear
Route::get('/proximosLinear', function () {
  // // // Instrutor
  // $aluno = Aluno::find(220);
  // dd(InstrutorRepository::proximosLinear($aluno->lat, $aluno->lng)->toArray());

  // Aluno
  $instrutor = Instrutor::find(684);
  dd(AlunoRepository::proximosLinear($instrutor->lat, $instrutor->lng)->toArray(), 25);

});

// http://localhost/jenifer/auto-habilitados-back/public/readCNHBrasilData
Route::get('/readCNHBrasilData', function () {

  // CNHBrasilRepository::procRemoteData();
  // CNHBrasilRepository::mergeData();
  // dd(CNHBrasilRepository::findInstrutor('Gilmar Rolim de Oliveira Junior', 'PR'));
  // dd(CNHBrasilRepository::findInstrutor('Salatiel da Silva Lima', 'PA') ? 'A' : 'S');


  $file   = file_get_contents('C:\Users\rodol\Desktop\dirigir\datasets\instrutores-TOTAL.json');                             // file_get_contents(storage_path('data/instrutores_20251217.json'));
  $data   = json_decode($file, true);
  $collection = collect($data);
  // dd($collection->where('cpf', '!=', '')->whereNotNull('cpf')->count());
  // dd($collection->where('uf', 'SP')->where('municipio', 'PERUÍBE')->whereNotNull('fone1')->where('fone1', '!=', '')->toArray());
  $filter = $collection
    // ->where('uf', 'PA')
    // ->where('municipio', 'LIMOEIRO')
    // ->where('email', '!=', '')
    // ->where(function ($item) {
    //   return $item['fone1'] != '' || $item['fone2'] != '';
    // })
  ->filter(function ($item) {
    // return Str::contains($item['cpf'], strtoupper('04857059827'));
    // return Str::contains($item['nome'], strtoupper("Kerlen Rocha"));
    // return Str::startsWith($item['nome'], 'RONY ');
    return Str::startsWith($item['nome'], strtoupper('JORGE LUIS RODRIGUES SILVA'));
    // return Str::startsWith($item['fone1'], '819');
    return true;
  });
  // dd($filter->pluck('nome', 'fone2')->toArray());
  dd($filter->toArray());


  // $instrutores = Instrutor::whereNotNull('finished_at')->where('status', 'S')->get();
  // dd($instrutores->toArray());
  // foreach ($instrutores as $instrutor) {
  //   $res = CNHBrasilRepository::findInstrutor($instrutor->nome, $instrutor->uf);
  //   $instrutor->status = $res ? 'A' : 'S';
  //   $instrutor->save();
  // }

    // $res = CNHBrasilRepository::getData(null);
  // $last = end($res);
  // $restartTokens = [[
  //   "'{$last['nome']}'",
  //   "'{$last['municipio']}'",
  //   "'{$last['bairro']}'",
  //   "'{$last['uf']}'",
  // ]];
  // $res = CNHBrasilRepository::getData($restartTokens);
  // $last = end($res);
  // $restartTokens = [[
  //   "'{$last['nome']}'",
  //   "'{$last['municipio']}'",
  //   "'{$last['bairro']}'",
  //   "'{$last['uf']}'",
  // ]];
  // $res = CNHBrasilRepository::getData($restartTokens);
  // dd($res);

  /////////////////////////////////////////////////////////////////////////////////

//    $fields = [
//     // 'telefone_1',
//     // 'telefone_2',
//     'telefone_3',
//   ];
// foreach ($fields as $field) {
//   $res = data_get(CNHBrasilRepository::getDataV2($field), 'dados');
//   if (count($res) > 0) {
//     dd($field);
//     break;
//   }
// }
});

// // http://localhost/jenifer/auto-habilitados-back/public/malaDiretaAlunos
// Route::get('/malaDiretaAlunos', function () {
//   // dd(WApiRepository::delFila());
//   dd(collect(WApiRepository::verFila()['messages'])->sortBy('created')->toArray());

// //   $alunos = Aluno::where('id', '>', 2)->get();
// //   // dd($alunos->toArray());

// //   foreach ($alunos as $aluno) {
// //     WApiRepository::sendMessage($aluno->fone1,"Boa tarde, aluno(a) $aluno->nome.

// // Recebemos seu cadastro com interesse em buscar aulas de direção com a plataforma DIRIGIR AGORA.

// // Mas vejo que você ainda não está apto para aulas práticas ☹️. Mas não se preocupe, posso te ajudar: você precisa abrir seu processo de habilitação no app oficial do governo CNH DO BRASIL e seguir as etapas em tela. O seguimento das etapas depende também de regras do Detran de seu estado. Baixe ele nas lojas de aplicativos de seu aparelho:

// // Android: https://play.google.com/store/apps/details?id=br.gov.serpro.cnhe
// // iPhone: https://apps.apple.com/br/app/cnh-do-brasil/id1275057217

// // Ao chegar na etapa de aulas práticas, você pode retomar o contato conosco para visualizar instrutores que atendem em sua região! 🥳🤓🚦🚗🏍️

// // Atenciosamente,
// // Dirigir Agora.

// // www.dirigiragora.com.br
// // ");
// //     sleep(rand(100, 120));
// //   }


// });


// // http://localhost/jenifer/auto-habilitados-back/public/malaDiretaInstrutores
// Route::get('/malaDiretaInstrutores', function () {
//   // dd(WApiRepository::delFila());
//   // dd(WApiRepository::verFila());

//   $instrutores = Instrutor::whereNull('descricao')->where('id', '>', 209)->get();
//   // dd($instrutores->toArray());

//   foreach ($instrutores as $instrutor) {
//     WApiRepository::sendMessage($instrutor->fone1,"Olá, instrutor(a) {$instrutor->nome},

// Você ainda não completou seu cadastro em nosso site *Dirigir Agora*.

// Enviamos abaixo o link, usuário e senha para que possa completar seu cadastro e fazer parte dessa jornada conosco.

// www.dirigiragora.com.br/instrutor/acesso

// *Entre com os seguintes dados*
// Usuário: e-mail
// Senha: WhatsApp com DDD

// Não há custo de cadastro ou adesão!

// Assim que seu cadastro for aprovado, você já poderá receber solicitações de aulas para habilitados. Os pedidos serão enviados no seu WhatsApp e você poderá aceitar ou negar a prestação de serviço.

// Em breve, assim que lançada a resolução no DOU, você também poderá atender primeira habilitação.

// Qualquer dúvida…estamos aqui!
// ");
//     sleep(rand(100, 120));
//   }


// });


// // http://localhost/jenifer/auto-habilitados-back/public/distanceKm
// Route::get('/distanceKm', function () {

//   $destinos = [
//     ['lat' => -23.564, 'lng' => -46.653],
//     ['lat' => -23.550, 'lng' => -46.633],
//     ['lat' => -23.580, 'lng' => -46.620],
//   ];
//   $resultado = GMapsRepository::distanceKmMatrix(-25.4077, -49.2533, $destinos);

//   dd($resultado);

//   // dd(GMapsRepository::distanceKm(-25.4077, -49.2533, -25.4284, -49.2733));
// });

// // http://localhost/jenifer/auto-habilitados-back/public/latLngSearch
// Route::get('/latLngSearch', function () {
//   $lat = -25.4284;
//   $lng = -49.2733;
//   $raio = 10;
//   $instrutoresProximos = Instrutor::select(DB::raw("*,  ROUND(6371 * acos(
//       cos(radians(?)) * cos(radians(lat)) *
//       cos(radians(lng) - radians(?)) +
//       sin(radians(?)) * sin(radians(lat))
//     ), 2) AS distancia_km"))
//     ->setBindings([$lat, $lng, $lat])
//     ->having('distancia_km', '<', $raio)
//     ->orderBy('distancia_km')
//     ->get();

//   dd($instrutoresProximos->toArray());
// });

// // http://localhost/jenifer/auto-habilitados-back/public/geocodeTest
// Route::get('/geocodeTest', function () {
//   dd(GMapsRepository::geocode('Rua Alcebíades Plaisant', 'Água Verde', 'Curitiba', 'PR'));
// });

// // http://localhost/jenifer/auto-habilitados-back/public/WApiRepositoryTeste
// Route::get('/WApiRepositoryTeste', function () {

//   WApiRepository::sendMessage('41997629021',"*Dados do Aluno*\nNome: Cristiane
// Telefone: 42996004483
// E-mail: cristianesouzadacosta@gmail.com
// Bairro: Bigorrilho
// Cidade: Curitiba");

// });

// // http://localhost/jenifer/auto-habilitados-back/public/corrigirSenha
// Route::get('/corrigirSenha', function () {
//   $instrutores = Instrutor::all();

//   foreach ($instrutores as $instrutor) {
//     $instrutor->password = Hash::make($instrutor->fone1);
//     $instrutor->save();
//   }
// });

// // http://localhost/jenifer/auto-habilitados-back/public/imagem
// Route::get('/imagem', function () {
//   $path = 'C:\Users\rodol\Desktop\compress\picture';
//   $files = glob($path . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
//   foreach ($files as $file) {
//     Helpers::tratamentoImg($path, '/' . basename($file));
//   }
// });



// $res = data_get(CNHBrasilRepository::getDataV2('e_mail'), 'dados');
//   dd($res);

// // http://localhost/jenifer/auto-habilitados-back/public/instrutoresReviews
// Route::get('/instrutoresReviews', function () {
  // // $instrutores_ids = [1, 3, 9, 12, 13, 18, 23, 27, 31, 45, 52, 53, 56, 70, 75, 80, 82, 85, 105, 120, 140, 167, 172, 189, 199, 207, 209, 210, 220, 226, 229, 242, 247, 249, 250, 255, 259, 269, 292, 297, 303, 306, 315, 316, 319, 320, 321, 323, 328, 332, 333, 334, 341, 343, 349, 350, 354, 356, 370, 373, 378, 386, 388, 390, 393, 403, 409, 412, 421, 424, 429, 432, 435, 440, 441, 443, 451, 455, 459, 460, 463, 464, 465, 466, 470, 472, 483, 487, 488, 499, 502, 517, 518, 519, 520, 526, 527, 534, 538, 539, 540, 541, 554, 555, 565, 570, 580, 583, 590, 591, 595, 596, 598, 600, 602, 607, 609, 613, 615, 618, 626, 632, 647, 655, 658, 660, 662, 663, 666, 667, 671, 676, 680, 684, 691, 692, 696, 698, 699, 710, 720, 722, 726, 733, 738, 745, 752, 760, 761, 763, 769, 772, 775, 789, 797, 799, 802, 803, 804, 805, 814, 815, 825, 826, 827, 829, 837, 847, 848, 852, 853, 856, 857, 862, 866, 867, 872, 875, 876, 877, 883, 885, 887, 895, 898, 899, 900, 909, 911, 912, 915, 916, 920, 924, 927, 928, 930, 932, 933, 939, 941, 950, 964, 972, 978, 980, 981, 982, 988, 989, 994, 995, 998, 1001, 1009, 1013, 1024, 1033, 1038, 1045, 1046, 1053, 1059, 1061, 1062, 1063, 1066, 1073, 1080, 1093, 1095, 1098, 1100, 1110, 1117, 1127, 1132, 1135, 1136, 1141, 1149, 1151, 1155, 1158, 1162, 1167, 1168, 1174, 1176, 1185, 1196];
  // // $instrutores_ids = [348];

  // // Caminho absoluto para a pasta de Downloads no Windows
  // $caminhoArquivo = 'C:\Users\rodol\Downloads\avaliacoes.php';

  // if (!file_exists($caminhoArquivo)) return;
  // require $caminhoArquivo;                                                                    // dd($avaliacoes);
  // $instrutores = Instrutor::whereIn('status', ['A', 'AA'])
    // ->whereNotIn('uf', ['SP', 'AC'])
    // ->get();                                                                                  // dd($instrutores->toArray());

  // $avaliacaoIdx = 0;
  // foreach ($instrutores as $instrutor) {

    // $quantidade_avaliacoes = rand(1, 4);
    // for ($i = 0; $i < $quantidade_avaliacoes; $i++) {
      // $avaliacao = $avaliacoes[$avaliacaoIdx];
      // $review = new InstrutorReview();
      // $review->aluno_id = 1;
      // $review->instrutor_id = $instrutor->id;
      // $review->nota = 5;
      // $review->nome = $avaliacao['nome'];
      // $review->title = $avaliacao['title'];
      // $review->body = $avaliacao['body'];
      // $review->save();

      // // Incrementa o contador de avaliações do instrutor
      // $instrutor->notaQtd = $instrutor->notaQtd + 1;
      // $instrutor->nota    = 5;
      // $instrutor->save();

      // $avaliacaoIdx++;
      // Log::info($avaliacaoIdx . ' - ' . $instrutor->id . ' - ' . $avaliacao['title']);
    // }


  // }

  // // foreach ($instrutores_ids as $id) {

  // //   $instrutor = Instrutor::find($id);
  // //   if (!$instrutor) continue;

  // //   // Gera aleatoriamente de 1 a 4 avaliações por instrutor
  // //   $quantidade_avaliacoes = rand(1, 4);

  // //   for ($i = 0; $i < $quantidade_avaliacoes; $i++) {

  // //       $nomeAleatorio = $nomes[array_rand($nomes)];

  // //       // pega UMA avaliação completa aleatória
  // //       $avaliacaoAleatoria = $avaliacoes[array_rand($avaliacoes)];

  // //       $review = new InstrutorReview();
  // //       $review->aluno_id = 1;
  // //       $review->instrutor_id = $instrutor->id;
  // //       $review->nota = 5;
  // //       $review->nome = $nomeAleatorio;
  // //       $review->title = $avaliacaoAleatoria['title'];
  // //       $review->body = $avaliacaoAleatoria['body'];
  // //       $review->save();

  // //       // Incrementa o contador de avaliações do instrutor
  // //       $instrutor->notaQtd = $instrutor->notaQtd + 1;
  // //       $instrutor->save();
  // //   }
  // // }
// });

// █▄ █ ▄▀▄ ▀█▀ █ █▀ █ ▄▀▀ ▄▀▄ ▀█▀ █ ▄▀▄ █▄ █
// █ ▀█ ▀▄▀  █  █ █▀ █ ▀▄▄ █▀█  █  █ ▀▄▀ █ ▀█

// http://localhost/jenifer/auto-habilitados-back/public/push-simulado-iframe
Route::get('/push-simulado-iframe', function () {

  // // 'Participe do curso ao vivo!',
  // // 'No turno que quiser! 100% aprovação! Interaja com alunos do Brasil todo!',

  // $alunos = SimAluno::where('origem', 'simulado')
  //   // ->where('extra->metodo_estudo', 'sozinho')
  //   ->get();
  // // dd($alunos->toArray());
  // $tokens = SimPushToken::where('origem', 'simulado')
  //   ->whereIn('owner_id', $alunos->pluck('id'))
  //   ->get();
  // dd($tokens->toArray());

  // PushRepository::sendNotificationTokens(
  //   $tokens,
  //   //   [
  //   // 'eSpq1uZ6QOCbWVxn3A1JHB:APA91bGtwJYMkqtWtdJCQBAhlCK2cXQctcj4eraqnFoEghu85x39c8yZUIzrbuDhveGlo1tGc3rN6YTh46VLEHBHdBSBwuQMWleq35St2iqGXm3Rx3OLZWQ',
  //   // 'eIZFyq08Rzqc9bi-rNe7sy:APA91bGOXguHuK-LQIP-fKSNFAC19OKIT_xlsFYHSIaHZbUPZ_GpLq_luSK-9h0wisyWFopzxn0S6tpjMvz7J8mThfYRzbWexFN9dwpTBwUCVB__rPL6vmI'
  //   // ],
  //   'Conheça o "Uber dos instrutores"!',
  //   'Visualize perfis e compare preços de instrutores em sua região. Opcional atendimento em domicílio',
  //   ['pagina' => 'iframe-container'],
  //   ['pagina' => 'iframe-container'],
  //   PushRepository::CANAL_SIMULADO,
  // );
});

// http://localhost/jenifer/auto-habilitados-back/public/push-dirigir-iframe
Route::get('/push-dirigir-iframe', function () {

  // $alunos = Aluno::where('ativo', 1)->get();
  // // dd($alunos->toArray());
  // $tokens = PushToken::where('owner_type', 'aluno')->whereIn('owner_id', $alunos->pluck('id'))->get();
  // // dd($tokens->toArray());
  // FirebaseRepository::sendNotificationTokens(
  //   $tokens->pluck('token')->toArray(),
  //   // [
  //   //   'fvEQzvv_RM2n9rVHpNDRKL:APA91bGnh1LiK5iUEOhbActBhNp1EO61CN0Hai1cKCfPP8p9r8lopYyo3YnULgpSky_N2X-vINcrPkXByb8lfmmpoOdVX4ZR7S8tlEN5ayzblWQB7Br3Y8A'
  //   // ],
  //   'Participe do curso ao vivo!',
  //   'No turno que quiser! 100% aprovação! Interaja com alunos do Brasil todo!',
  //   ['pagina' => 'iframe-container']
  // );

});

// http://localhost/jenifer/auto-habilitados-back/public/push-geral
Route::get('/push-geral', function () {
  // $alunos = Aluno::where('ativo', 1)
  // ->whereNotExists(function ($query) {
  //   $query->select(DB::raw(1))
  //     ->from('aula')
  //     ->whereColumn('aula.aluno_id', 'aluno.id');
  // })
  // ->get();
  // $tokens = PushToken::where('owner_type', 'aluno')->whereIn('owner_id', $alunos->pluck('id'))->get();
  // // dd($tokens->toArray());
  // FirebaseRepository::sendNotificationTokens(
  //   $tokens->pluck('token')->toArray(),
  //   'Nova lei: Exame toxicológico passa a ser exigido para CNH!',
  //   'Evite este custo a mais! Corra garantir suas aulas antes!'
  // );
});

// http://localhost/jenifer/auto-habilitados-back/public/push-promocao-regiao
Route::get('/push-promocao-regiao', function () {

  $promoValorPartir = 'R$442,00';

  $alunos = Aluno::where('municipio', 'Curitiba')
  ->where('ativo', 1)
  ->whereNotExists(function ($query) {
    $query->select(DB::raw(1))
      ->from('aula')
      ->whereColumn('aula.aluno_id', 'aluno.id');
  })
  ->get();
  dd($alunos->toArray());

  // ----- PUSH NOTIFICATION
  $tokens = PushToken::where('owner_type', 'aluno')->whereIn('owner_id', $alunos->pluck('id'))->get();                    // dd($tokens->toArray());
  PushRepository::sendNotificationTokens(
    $tokens,
    'Novas condições e preços',
    "Valores promocionais a partir de $promoValorPartir (2 aulas + aluguel veículo). Clique e aproveite!",
    ['pagina' => 'promocao-curitiba'],
    ['pagina' => 'promocao-curitiba'],
    PushRepository::CANAL_DIRIGIR,
  );

  // ----- EMAIL CASO NÃO TENHA PUSH
  $alunosComPushIds = PushToken::where('owner_type', 'aluno')
    ->whereIn('owner_id', $alunos->pluck('id'))
    ->distinct()
    ->pluck('owner_id')
    ->toArray();
  $alunosSemPush = $alunos->reject(fn($aluno) => in_array($aluno->id, $alunosComPushIds))->values();

  foreach ($alunosSemPush as $aluno) {
    $email = $aluno->email;
    // $email = 'rodolfojnnogueira@gmail.com';
    $enviado = Mail::mailer('zeptomail')->send('mail.aluno-promocao-regiao', ['email' => $email], function ($m) use ($email, $promoValorPartir) {
      $m->to($email)->subject("Sua CNH a partir de $promoValorPartir (2 aulas + aluguel veículo)");
    });
    if ($enviado) Log::channel('email')->info('Email Send: aluno-promocao-regiao - ' . $email);
    // dd($enviado);
  }

  dd('OK');

});


// ▄▀▀ █▀▄ ▄▀▄ █▄ █ ▄▀▀
// ▀▄▄ █▀▄ ▀▄▀ █ ▀█ ▄█▀

// http://localhost/jenifer/auto-habilitados-back/public/cron-alunos-email-cnh-brasil
Route::get('/cron-alunos-email-cnh-brasil',      [CNHBrasilRepository::class, 'cronNovosAlunosProcessEmail']);

// http://localhost/jenifer/auto-habilitados-back/public/cron-msg-instrutos-nao-lidas
Route::get('/cron-msg-instrutos-nao-lidas', function () {
  // $tokens = PushToken::where('owner_type', 'instrutor')
  //   ->whereIn('owner_id', function ($query) {
  //       $query->select('instrutor_id')
  //           ->from('chatMsg')
  //           ->where('lidoInstrutor', false)
  //           ->groupBy('instrutor_id');
  //   })
  //   ->get();
  // PushRepository::sendNotificationTokens(
  //   $tokens,
  //   'Nova mensagem',
  //   'Aluno interessado em aulas aguardando sua resposta no chat Dirigir Agora.',
  //   [],
  //   PushRepository::CANAL_DIRIGIR,
  // );
});

// http://localhost/jenifer/auto-habilitados-back/public/cron-novos-leads
Route::get('/cron-novos-leads', function () {
  // created_at de hoje das 00:00 até as 23:59:59 - created_at é datetime
  $alunos       = Aluno::whereDate('created_at', today())->get();

  // Instrutores próximos
  foreach ($alunos as $aluno) {
    $instrutoresProximos = InstrutorRepository::proximosLinear($aluno->lat, $aluno->lng);

    // Contagens
    $carOwn     = $instrutoresProximos->where('carOwn', 1)->count();
    $carAluno   = $instrutoresProximos->where('carAluno', 1)->count();
    $bikeOwn    = $instrutoresProximos->where('bikeOwn', 1)->count();
    $bikeAluno  = $instrutoresProximos->where('bikeAluno', 1)->count();

    $aluno->instrutores_proximos = $instrutoresProximos;
    $aluno->count_carro_proprio = $carOwn;
    $aluno->count_carro_aluno = $carAluno;
    $aluno->count_moto_proprio = $bikeOwn;
    $aluno->count_moto_aluno = $bikeAluno;
  }

  $instrutores  = Instrutor::whereDate('created_at', today())->get();
  $body = '
    <h2>Relatório de Hoje</h2>

    <h3>Novos Alunos:</h3>
    <ul>';
  foreach ($alunos as $aluno) {
    $primeiroNome = Str::before($aluno->nome, ' ');
    $body .= '<li>' .
      $primeiroNome . ' - ' . $aluno->fone1 . ' - ' . $aluno->uf . ' - ' . $aluno->municipio . ' - ' . $aluno->solicitacao .
        '<br>' .
        'Instrutores Próximos: ' . count($aluno->instrutores_proximos) .
        '<br>' .
        'Carro Próprio: ' . $aluno->count_carro_proprio .
        '<br>' .
        'Carro do Aluno: ' . $aluno->count_carro_aluno .
        '<br>' .
        'Moto Própria: ' . $aluno->count_moto_proprio .
        '<br>' .
        'Moto do Aluno: ' . $aluno->count_moto_aluno .
        '<br>' .
      '</li>';
  }

  $body .= '
  </ul>

  <h3>Novos Instrutores:</h3>
  <ul>';
  foreach ($instrutores as $instrutor) {
    $primeiroNome = Str::before($instrutor->nome, ' ');
    $body .= '<li>'
      . $primeiroNome . ' - '
      . $instrutor->fone1 . ' - Status: '
      . $instrutor->status
      . '</li>';
  }
  $body .= '</ul>';
  // MailRepository::msgManager($body, '❤️ Criados hoje Jenifinha: ' . $alunos->count() . ' alunos e ' . $instrutores->count() . ' instrutores');
  MailRepository::msgManager($body, 'Cadastrados hoje: ' . $alunos->count() . ' alunos e ' . $instrutores->count() . ' instrutores');

});

// http://localhost/jenifer/auto-habilitados-back/public/cron-push-simulado-insta-dirigir
Route::get('/cron-push-simulado-insta-dirigir', function () {
  set_time_limit(0);

  $title  = 'Combinado fechado? 🤝';
  $body   = 'Ajude a fortalecer para manter o app grátis para todos: siga a gente no Instagram! 🚀';
  $pagina = ['url' => 'https://www.instagram.com/dirigiragora'];

  $alunos = SimAluno::where('origem', 'simulado')
    // ->where('extra->metodo_estudo', 'sozinho')
    ->get();
  // dd($alunos->toArray());
  $tokens = SimPushToken::where('origem', 'simulado')
    ->whereIn('owner_id', $alunos->pluck('id'))
    ->get();
  // dd($tokens->toArray());

  // // DEBUG
  // $tokens = [[
  //   'platform' => 'android',
  //   'token' => 'fB1Pk-HoQUS7E8vav-rFq_:APA91bHLcZ8hvYaxbybil4urKBx2pIV9_kx2fzZguf2uR8buJv4lcPKejLfuY2wg5m7cnRE4rNQxoRHI_LruMMxrbT83_ZMunRApwXhoooxNReCzWO0T5xs'
  // ]];

  PushRepository::sendNotificationTokens(
    $tokens,
    $title,
    $body,
    $pagina,
    $pagina,
    PushRepository::CANAL_SIMULADO,
  );
});

// http://localhost/jenifer/auto-habilitados-back/public/cron-push-simulado-dirigir
Route::get('/cron-push-simulado-dirigir', function () {
  set_time_limit(0);

  $title          = 'Primeira autoescola digital do 🇧🇷 !';
  $body           = 'Clique e conheça: instrutores credenciados ao Detran vão até você para as aulas!';
  $paginaAndroid  = ['pagina' => 'iframe-container'];
  $paginaIos      = ['pagina' => 'iframe-container'];
  // Se o horário for depois das 15h mudar o title e o body e o page
  if (Carbon::now()->hour > 17) {
    $title  = 'Você não está sozinho(a)!';
    $body   = 'No app Dirigir Agora você tem suporte gratuito do início ao fim do processo da sua CNH!';
    $paginaAndroid  = ['url' => 'https://play.google.com/store/apps/details?id=br.com.dirigiragora'];
    $paginaIos      = ['url' => 'https://apps.apple.com/br/app/dirigir-agora-cnh-do-brasil/id6801734923'];
  }

  $alunos = SimAluno::where('origem', 'simulado')
    // ->where('extra->metodo_estudo', 'sozinho')
    ->get();
  // dd($alunos->toArray());
  $tokens = SimPushToken::where('origem', 'simulado')
    ->whereIn('owner_id', $alunos->pluck('id'))
    ->get();
  // dd($tokens->toArray());

  // DEBUG
  // $tokens = [[
  //   'platform' => 'android',
  //   'token' => 'fB1Pk-HoQUS7E8vav-rFq_:APA91bHLcZ8hvYaxbybil4urKBx2pIV9_kx2fzZguf2uR8buJv4lcPKejLfuY2wg5m7cnRE4rNQxoRHI_LruMMxrbT83_ZMunRApwXhoooxNReCzWO0T5xs'
  // ]];
  // $tokens = [[
  //   'platform' => 'ios',
  //   'token' => 'E7E8F0E9A5C386B23B248CD070DC100E3D4AF8E33837CB138B01ACD1CDD60B31'
  // ]];

  PushRepository::sendNotificationTokens(
    $tokens,
    $title,
    $body,
    $paginaAndroid,
    $paginaIos,
    PushRepository::CANAL_SIMULADO,
  );
});

// http://localhost/jenifer/auto-habilitados-back/public/cron-push-simulado-teorico
Route::get('/cron-push-simulado-teorico', function () {
  set_time_limit(0);
  $alunos = SimAluno::where('origem', 'simulado')
    ->where('created_at', '>', Carbon::now()->subDays(1))
    ->get();
  // dd($alunos->toArray());
  $tokens = SimPushToken::where('origem', 'simulado')
    ->whereIn('owner_id', $alunos->pluck('id'))
    ->get();
  // dd($tokens->toArray());

  PushRepository::sendNotificationTokens(
    $tokens,
    // [
    // 'fB1Pk-HoQUS7E8vav-rFq_:APA91bHLcZ8hvYaxbybil4urKBx2pIV9_kx2fzZguf2uR8buJv4lcPKejLfuY2wg5m7cnRE4rNQxoRHI_LruMMxrbT83_ZMunRApwXhoooxNReCzWO0T5xs'
    // ],
    'Curso teórico ao vivo',
    'Clique e conheça! Com garantia de aprovação. Você aprovado ou seu dinheiro de volta!',
    ['url' => 'https://teorico.dirigiragora.com.br/promo-dg'],
    ['url' => 'https://teorico.dirigiragora.com.br/promo-dg'],
    PushRepository::CANAL_SIMULADO,
  );
});

// http://localhost/jenifer/auto-habilitados-back/public/cron-push-dirigir-teorico
Route::get('/cron-push-dirigir-teorico', function () {
  set_time_limit(0);
  $alunos = Aluno::where('ativo', 1)
  ->where('created_at', '>', Carbon::now()->subDays(1))
  ->get();
  $tokens = PushToken::where('owner_type', 'aluno')
    ->whereIn('owner_id', $alunos->pluck('id'))
    ->get();
  // dd($tokens->toArray());
  PushRepository::sendNotificationTokens(
    $tokens,
    // [
    //   'f0cH3h2OR7mjQYrA5cTsz6:APA91bHY1jqx3Tzn8zr2mSnuAOwrO98jnNCxHqd_EF4VUBqEDJeMANpWJZrbTFH5yW8PRIVgd8arUqHF75PMS8giaeiIcNjElDm-pMfhqsjkepGf3b0hweo',
    //   'c-upLZEuRRifKRpMY6BrGt:APA91bFPxXXwFvvX4aeffloA-5V5RoiBgCWj5uvVScCjNR97zAOycbYs7NOH-sOPBWeoO9L3vSLPgyCzkvAtWRtVeGd7dXimNTfmBHby66imUxWrYpY51_M'
    // ],
    'Curso teórico ao vivo',
    'Clique e conheça! Com garantia de aprovação. Você aprovado ou seu dinheiro de volta!',
    ['url' => 'https://teorico.dirigiragora.com.br/promo-dg'],
    ['url' => 'https://teorico.dirigiragora.com.br/promo-dg'],
    PushRepository::CANAL_DIRIGIR,
  );
});

// http://localhost/jenifer/auto-habilitados-back/public/cron-alerta-instrutor-falta-resposta-chat
Route::get('/cron-alerta-instrutor-falta-resposta-chat', function () {

  // $resultado = MetaRepository::sendMessage(
  //   '1310895208774063',
  //   'alunosemresposta',
  //   '5541997629021',
  //   'Carla'
  // );

  // TODOS na história com pushzero ativo 1 status A e AA

  $rows = DB::select("
    SELECT
        i.id AS instrutor_id,
        i.status,
        i.fone1,
        i.nome,
        CASE WHEN EXISTS (
            SELECT 1 FROM pushToken WHERE owner_id = i.id AND owner_type = 'instrutor'
        ) THEN 1 ELSE 0 END AS temPush
    FROM instrutor i
    WHERE i.status IN ('A', 'AA')
    AND i.ativo = 1
      AND EXISTS (
          SELECT 1
          FROM chatMsg c
          WHERE c.instrutor_id = i.id
            AND c.de = 'Aluno'
            AND c.aluno_id NOT IN (1, 3282)
            AND c.created_at BETWEEN NOW() - INTERVAL 360 DAY AND NOW() - INTERVAL 24 HOUR
            AND NOT EXISTS (
                SELECT 1
                FROM chatMsg resp
                WHERE resp.instrutor_id = c.instrutor_id
                  AND resp.aluno_id = c.aluno_id
                  AND resp.de = 'Instrutor'
                  AND resp.created_at > c.created_at
            )
      )
      having temPush = 0
  ");

  dd($rows);

  foreach ($rows as $row) {
    $primeiroNome = ucfirst(Str::before($row->nome, ' '));
    MetaRepository::sendMessage(
      '1310895208774063',
      'alunosemresposta',
      '55' . $row->fone1,
      $primeiroNome
    );
    sleep(10);
  }

//   $rows = DB::select("
//     SELECT
//     i.id AS instrutor_id,
//     i.status,
//     i.fone1,
//     CASE WHEN EXISTS (
//         SELECT 1 FROM pushToken WHERE owner_id = i.id AND owner_type = 'instrutor'
//     ) THEN 1 ELSE 0 END AS temPush
// FROM instrutor i
// WHERE i.status IN ('A', 'AA')
// AND i.ativo = 1
//   AND EXISTS (
//       SELECT 1
//       FROM chatMsg c
//       WHERE c.instrutor_id = i.id
//         AND c.de = 'Aluno'
//         AND c.aluno_id NOT IN (1, 3282)
//         AND c.created_at BETWEEN NOW() - INTERVAL 96 HOUR AND NOW() - INTERVAL 24 HOUR
//         AND NOT EXISTS (
//             SELECT 1
//             FROM chatMsg resp
//             WHERE resp.instrutor_id = c.instrutor_id
//               AND resp.aluno_id = c.aluno_id
//               AND resp.de = 'Instrutor'
//               AND resp.created_at > c.created_at
//         )
//   );
//   ");

//   dd($rows);
});

// http://localhost/jenifer/auto-habilitados-back/public/cron-push-dirigir-chat-proximo-contato
Route::get('/cron-push-dirigir-chat-proximo-contato', function () {

  // Última mensagem de boas-vindas enviada automaticamente
  // E então Lucas, vamos iniciar suas aulas?
  $trechoIniciarAulas = ', vamos iniciar suas aulas?';

  // 1. Busca os chats com o admin (348) cuja última mensagem foi a de boas-vindas
  $chats = ChatList::where('instrutor_id', 348)
    ->where('created_at', '>', '2026-08-22')
    ->where('ultimoDe', 'Instrutor')
    ->where('ultimaMensagem', 'like', '%' . $trechoIniciarAulas . '%')
    ->get();

  // dd($chats->toArray());

  foreach ($chats as $chat) {
    $aluno = Aluno::find($chat->aluno_id);
    if (!$aluno) continue;

    $primeiroNome = Str::before($aluno->nome, ' ');
    if (!$primeiroNome) $primeiroNome = $aluno->nome;

    $mensagem = "Bem, como não tivemos seu retorno... futuramente pode nos acionar se precisar de algo! 🤝

Lembrando que oferecemos suporte gratuito para todas as etapas.

Por aqui você só precisa se preocupar em escolher seu instrutor para as aulas práticas (que no mínimo 2 são obrigatórias pelo Detran, para podermos agendar sua prova). ✅";

    // 2. Envia a segunda mensagem no chat
    ChatRepository::sendMessage('Instrutor', 348, $aluno->id, $mensagem);

    // 3. Dispara o push notification para o aluno
    NotificationRepository::newChatMsg('Aluno', $aluno->id);

    sleep(3);
  }

  // Mensagem de boas-vindas enviada automaticamente no cadastro do aluno
  // (admin = instrutor 348).
  $trechoBoasVindas = 'Que bom ter você com a gente';

  // 1. Busca os chats com o admin (348) cuja última mensagem foi a de boas-vindas
  $chats = ChatList::where('instrutor_id', 348)
    ->where('created_at', '>', '2026-08-22')
    ->where('ultimoDe', 'Instrutor')
    ->where('ultimaMensagem', 'like', '%' . $trechoBoasVindas . '%')
    ->get();

  // dd($chats->toArray());

  foreach ($chats as $chat) {
    $aluno = Aluno::find($chat->aluno_id);
    if (!$aluno) continue;

    $primeiroNome = Str::before($aluno->nome, ' ');
    if (!$primeiroNome) $primeiroNome = $aluno->nome;

    $mensagem = "E então {$primeiroNome}, vamos iniciar suas aulas?";

    // 2. Envia a segunda mensagem no chat
    ChatRepository::sendMessage('Instrutor', 348, $aluno->id, $mensagem);

    // 3. Dispara o push notification para o aluno
    NotificationRepository::newChatMsg('Aluno', $aluno->id);

    sleep(3);
  }

});