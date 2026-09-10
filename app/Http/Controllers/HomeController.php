<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Response;

class HomeController extends Controller
{
  public function __construct() {
  }

  public function getTableMetadata($base, $table) {
    if (!$this->checkBasicAuth()) return Response::make('Unauthorized', 401, ['WWW-Authenticate' => 'Basic']);

    $translateType = function ($type) {
      $pos = strpos($type, 'text');
      if ($pos !== false) return 'string';
      $pos = strpos($type, 'tinyint(1)');
      if ($pos !== false) return 'boolean';
      $pos = strpos($type, 'int');
      if ($pos !== false) return 'number';
      $pos = strpos($type, 'decimal');
      if ($pos !== false) return 'number';
      $pos = strpos($type, 'varchar');
      if ($pos !== false) return 'string';
      $pos = strpos($type, 'datetime');
      if ($pos !== false) return 'string';
      $pos = strpos($type, 'json');
      if ($pos !== false) return 'any';
      $pos = strpos($type, 'date');
      if ($pos !== false) return 'string';

      return '';
    };
    $res =  DB::connection($base)->select("SHOW FULL COLUMNS FROM $table");
    $tableCap = ucfirst($table);
    echo '<pre>';
    echo "export interface I$tableCap {<br>";
    collect($res)->each(function ($it) use ($translateType) {
      $required = ($it->Null === 'NO') ? true : false;
      $default = ($it->Default != '') ? true : false;
      $defaultStr = $it->Default;
      if ($it->Type === 'longtext') {
        $it->Type = 'json';
      }
      $translated = $translateType($it->Type);
      $resStr = '  ' . $it->Field  .  ': ' . $translated . ';';
      if ($default && $translated == 'boolean') {
        $defaultStr = ($it->Default == 0) ? 'false' : 'true';
      }
      if ($default && $translated === 'string') {
        $defaultStr = "'$it->Default'";
      }

      $append = '// ' . $it->Type;
      if ($required) {
        $append = str_pad($append, 18, ' ', STR_PAD_RIGHT);
        $append .= '  REQUIRED';
      }
      if ($default) {
        $append = str_pad($append, 34, ' ', STR_PAD_RIGHT);
        $append .= 'DEF ' . $defaultStr;
      }
      if ($it->Comment != '') {
        $append = str_pad($append, 65, ' ', STR_PAD_RIGHT);
        $append .= $it->Comment;
      }
      $resStr = str_pad($resStr, 38, ' ', STR_PAD_RIGHT);
      $resStr .= $append;
      $resStr .= '<br>';
      echo $resStr;
    });
    echo "}";
    echo '</pre>';

    echo '<pre>';
    echo "----------- FormBuilder fields<br><br>";
    collect($res)->each(function ($it) use ($translateType) {
      $required = ($it->Null === 'NO') ? true : false;
      $default = ($it->Default != '') ? true : false;
      $defaultStr = $it->Default;
      $translated = $translateType($it->Type);
      if ($default && $translated == 'boolean') {
        $defaultStr = ($it->Default == 0) ? 'false' : 'true';
      }
      if ($default && $translated === 'string') {
        $defaultStr = "'$it->Default'";
      }
      $colx = '[';
      // Tipo
      $colx .= "&lt;$translated&gt;";
      if ($it->Field != 'id') {
        if ($default) {
          $colx .= $defaultStr;
        }
        if ($required && !$default) {
          $colx .= 'null';
        }
        if ($required) {
          $colx .= ', Validators.required';
        }
      }
      $colx .= ']';

      $it->Field = str_pad($it->Field, 25, ' ', STR_PAD_RIGHT);
      // ($it->Comment ? '// ' . $it->Comment : '')
      echo str_pad($it->Field . ': ' . $colx . ',', 70, ' ', STR_PAD_RIGHT) . '<br>';
    });
    echo '</pre>';

    echo '<pre>';
    echo "----------- Init state<br><br>";
    echo 'export const frmMainInitState = {<br>';
    collect($res)->each(function ($it) use ($translateType) {
      $default = ($it->Default != '') ? true : false;
      $defaultStr = $it->Default;
      $translated = $translateType($it->Type);
      if (!$default) {
        return;
      }
      if ($default && $translated == 'boolean') {
        $defaultStr = ($it->Default == 0) ? 'false' : 'true';
      }
      if ($default && $translated === 'string') {
        $defaultStr = "'$it->Default'";
      }
      $colx = '';
      if ($it->Field != 'id') {
        if ($default) {
          $colx .= $defaultStr;
        }
      }
      $colx .= '';

      $it->Field = str_pad($it->Field, 15, ' ', STR_PAD_RIGHT);
      echo '  ' . $it->Field . ': ' . $colx . ',<br>';
    });
    echo '};</pre>';


    echo '<pre>';
    echo "----------- TurboTable template<br><br>";
    collect($res)->each(function ($it) use ($translateType) {
      $default = ($it->Default != '') ? true : false;
      $defaultStr = $it->Default;
      $translated = $translateType($it->Type);
      if ($default && $translated == 'boolean') {
        $defaultStr = ($it->Default == 0) ? 'false' : 'true';
      }
      if ($default && $translated === 'string') {
        $defaultStr = "'$it->Default'";
      }
      $colx = '';
      if ($it->Field != 'id') {
        if ($default) {
          $colx .= $defaultStr;
        }
      }
      $colx .= '';

      $classeWitdh = $translated === 'number' ? 'w60' : 'w80';
      switch ($it->Type) {
        case 'decimal(10,2)':
          $classeWitdh = 'w80';
          break;
        case 'datetime':
          $classeWitdh = 'w110';
          break;
        case 'date':
          $classeWitdh = 'w110';
          break;
        case 'tinyint(1)':
          $classeWitdh = 'w60';
          break;
      }
      $it->Field = trim($it->Field);
      echo "&lt;th class=\"$classeWitdh\">$it->Field&lt;/th&gt;<br>";
    });
    echo "<br>";
    collect($res)->each(function ($it) use ($translateType) {
      $default = ($it->Default != '') ? true : false;
      $defaultStr = $it->Default;
      $translated = $translateType($it->Type);
      if ($default && $translated == 'boolean') {
        $defaultStr = ($it->Default == 0) ? 'false' : 'true';
      }
      if ($default && $translated === 'string') {
        $defaultStr = "'$it->Default'";
      }
      $colx = '';
      if ($it->Field != 'id') {
        if ($default) {
          $colx .= $defaultStr;
        }
      }
      $colx .= '';

      $classeWitdh = $translated === 'number' ? 'w60' : 'w80';
      $filtro = '';
      $prefix = '';
      switch ($it->Type) {
        case 'decimal(10,2)':
          $filtro = ' | number : "1.2-2"';
          $classeWitdh = 'w80';
          $prefix = 'R$ ';
          break;
        case 'datetime':
          $filtro = ' | date : "short"';
          $classeWitdh = 'w110';
          break;
        case 'date':
          $filtro = ' | date : "shortDate"';
          $classeWitdh = 'w110';
          break;
        case 'tinyint(1)':
          $classeWitdh = 'w60';
          break;
      }
      $it->Field = trim($it->Field);
      // echo "&lt;th class=\"$classeWitdh\">$it->Field&lt;/th&gt;<br>";
      if ($it->Type === 'tinyint(1)') {
        echo "&lt;td class=\"$classeWitdh text-center\" [innerHTML]=\"it.{$it->Field} | showBoolean\">&lt;/td&gt;<br>";
      } else {
        echo "&lt;td class=\"$classeWitdh\">$prefix{{it.$it->Field$filtro}}&lt;/td&gt;<br>";
      }
    });
    echo '</pre>';
  }

  public function contato(Request $req) {
    if (!RateLimiter::attempt('IP_ADDRESS_' . request()->ip(), $perMinute = 2, function(){}))
      return Response::json(['status' => false, 'data' => 'Muitas tentativas realizadas! Por favor, tente novamente mais tarde'], 429);

    Mail::send('mail.contato-blc', $req->all(), function ($m) {
      $m->to(env('MAIL_FROM_ADDRESS'))->subject('Contado direto do site BLC');
    });

    return $this->response('Mensagem enviada com sucesso!');
  }

  public function logForms(Request $req) {
    Log::channel('logForms')->info(__METHOD__ . ' - ' . json_encode($req->all()));
  }

}
