<?php

namespace App\Repository;

use App\Http\Controllers\AulaRepository;
use App\Libraries\DefResponseTrait;
use App\Models\Aluno;
use App\Models\Pagamento;
use App\Models\PagamentoItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AsaasRepository {

  use DefResponseTrait;

  // // SANDBOX
  // private static $url           = 'https://sandbox.asaas.com/api';
  // private static $access_token  = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjZjYTk1NTY5LTlkMGUtNDYwYi1iMzI3LTJiMGM3NDk3MGM1NTo6JGFhY2hfYjFhMGU1ZjEtNjkxNC00MjcyLTgyN2MtNjQzYWQxYTRlZDBi';

  // PRODUÇÃO
  private static $url           = 'https://api.asaas.com';
  private static $access_token  = '$aact_prod_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjMzYjUzYmNiLWQ1NzYtNDA2Yi1iMjBiLWU5NTE3YTUxZDhlNTo6JGFhY2hfOGQ3NzM4NjEtZGRkNi00ZTQ4LTlhZTItNWIzNzllZDk3YzJj';

  /**
   * Cria um novo cliente
   * https://docs.asaas.com/reference/criar-novo-cliente
   *
   * @param Aluno
   * @return Aluno
   */
  public static function postCustomers($aluno) {
    if ($aluno->asaas_id) return $aluno;

    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'access_token' => self::$access_token
    ])->post(self::$url . '/v3/customers', [
      'name'                  => $aluno->nome,
      'cpfCnpj'               => $aluno->doc,
      'email'                 => $aluno->email,
      'phone'                 => $aluno->fone1,
      'notificationDisabled'  => true
    ]);
    $response->throw();

    $json = (object) $response->json();
    $aluno->asaas_id = $json->id;
    $aluno->save();

    return $aluno;
  }

  // https://docs.asaas.com/reference/criar-nova-cobranca
  // https://docs.asaas.com/reference/criar-nova-cobranca-com-cartao-de-credito
  public static function postPayments($billingType, $customer, $value, $description, $externalReference, $installmentCount, $creditCard = null, $creditCardHolderInfo = null) {
    $payload = [
      'billingType'       => $billingType,
      'customer'          => $customer,
      'value'             => $value,
      'description'       => $description,
      'externalReference' => $externalReference,
      'dueDate'           => Carbon::now()->addDays(10)->format('Y-m-d'),
      'remoteIp'          => request()->ip()
    ];

    if ($creditCard) {
      $payload['creditCard']            = $creditCard;
      $payload['creditCardHolderInfo']  = $creditCardHolderInfo;
      if ($installmentCount > 1) {
        $payload['installmentCount']  = $installmentCount;
        $payload['totalValue']        = $value;
      }
    }

    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($payload));

    $response = Http::withHeaders([
      'Content-Type'      => 'application/json',
      'access_token'      => self::$access_token
    ])->post(self::$url . '/v3/payments', $payload);

    return $response;

  }

  public static function getPaymentStatus($id) {
    $response = Http::withHeaders([
      'Content-Type'      => 'application/json',
      'access_token'      => self::$access_token
    ])->get(self::$url . '/v3/payments/' . $id . '/status');
    $response->throw();
    $json = (object) $response->json();
    return $json;
  }

  public static function getPaymentQrCode($paymentId) {
    $response = Http::withHeaders([
      'Content-Type'      => 'application/json',
      'access_token'      => self::$access_token
    ])->get(self::$url . '/v3/payments/' . $paymentId . '/pixQrCode');

    $response->throw();
    $json = (object) $response->json();
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($json));
    return $json;
  }

  // https://docs.asaas.com/reference/list-payments-of-a-installment
  public static function getListInstallments($installmentId) {
    $response = Http::withHeaders([
      'Content-Type'      => 'application/json',
      'access_token'      => self::$access_token
    ])->get(self::$url . '/v3/installments/' . $installmentId . '/payments');
    return $response->json();
  }

  // https://docs.asaas.com/reference/transferir-para-conta-de-outra-instituicao-ou-chave-pix
  public static function postTransferPix($reference_id, $value, $pixKey, $pixKeyType, $description) {
    $payload = [
      'value'               => $value,
      'operationType'       => 'PIX',
      'pixAddressKey'       => $pixKey,
      'pixAddressKeyType'   => $pixKeyType,     // CPF, CNPJ, EMAIL, PHONE, EVP
      'description'         => $description,
      'externalReference'   => (string) $reference_id
    ];

    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($payload));

    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'access_token' => self::$access_token
    ])->post(self::$url . '/v3/transfers', $payload);

    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($response->json()));

    return $response->json();
  }

  public static function getTransfers() {
    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'access_token' => self::$access_token
    ])->get(self::$url . '/v3/transfers');

    return $response->json();
  }

  // https://api.dirigiragora.com.br/v1/asaas/webhook
  // https://sandbox.asaas.com/customerConfigIntegrations/webhookLogs
  // https://docs.asaas.com/docs/webhook-para-cobrancas

  // Pix - PAYMENT_CREATED
  // {"id":"evt_05b708f961d739ea7eba7e4db318f621&13036282","event":"PAYMENT_CREATED","dateCreated":"2025-12-03 10:50:39","payment":{"object":"payment","id":"pay_uwtftbfred01ypwr","dateCreated":"2025-12-03","customer":"cus_000007259639","checkoutSession":null,"paymentLink":null,"value":1042.53,"netValue":1040.54,"originalValue":null,"interestValue":null,"description":"[1] Rodolfo - 176476983411","billingType":"PIX","pixTransaction":null,"status":"PENDING","dueDate":"2025-12-13","originalDueDate":"2025-12-13","paymentDate":null,"clientPaymentDate":null,"installmentNumber":null,"invoiceUrl":"https:\/\/sandbox.asaas.com\/i\/uwtftbfred01ypwr","invoiceNumber":"12163815","externalReference":"176476983411","deleted":false,"anticipated":false,"anticipable":false,"creditDate":null,"estimatedCreditDate":null,"transactionReceiptUrl":null,"nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":null}}

  // Pix - PAYMENT_REFUNDED
  // {"id":"evt_d313266b1bd58128da69a2a96d82e79e&1228254793","event":"PAYMENT_REFUNDED","dateCreated":"2026-02-19 18:31:26","account":{"id":"282a2fd8-f84e-458d-a1b3-d3a76b98f334","ownerId":null},"payment":{"object":"payment","id":"pay_3ffzlecunz33tvhj","dateCreated":"2026-02-19","customer":"cus_000162281004","checkoutSession":null,"paymentLink":null,"value":197.74,"netValue":196.75,"originalValue":null,"interestValue":null,"description":"[1038] Gabriel Maciel Teixeira  - 177151983046","billingType":"PIX","confirmedDate":"2026-02-19","pixTransaction":null,"status":"REFUNDED","dueDate":"2026-03-01","originalDueDate":"2026-03-01","paymentDate":"2026-02-19","clientPaymentDate":"2026-02-19","installmentNumber":null,"invoiceUrl":"https:\/\/www.asaas.com\/i\/3ffzlecunz33tvhj","invoiceNumber":"747496299","externalReference":"177151983046","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2026-02-19","estimatedCreditDate":"2026-02-19","transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/7168545786046010","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":[{"dateCreated":"2026-02-19 18:31:21","status":"DONE","value":197.74,"endToEndIdentifier":"D19540550202602192131YcGJk2X6rAW","transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/7168545786046010","description":null,"effectiveDate":"2026-02-19 18:31:25","refundedSplits":null}]}}

  // Pix TRANSFER_CREATED - TRANSFER_DONE
  // {"id":"evt_e633de3138cfbe837b624a32f8b242a7&1155725099","event":"TRANSFER_CREATED","dateCreated":"2025-12-12 15:33:59","transfer":{"object":"transfer","id":"1ba54c62-18b3-4311-8e82-56c7f7eb7534","value":0.02,"netValue":0.02,"transferFee":0,"dateCreated":"2025-12-12","status":"PENDING","effectiveDate":null,"confirmedDate":null,"endToEndIdentifier":null,"transactionReceiptUrl":null,"operationType":"PIX","failReason":null,"walletId":null,"description":"Transa\u00e7\u00e3o Pix Teste 2","externalReference":"176556443764","authorized":false,"scheduleDate":"2025-12-12","type":"BANK_ACCOUNT","bankAccount":{"bank":{"code":"077","name":"BANCO INTER S.A.","ispb":"00416968"},"accountName":null,"ownerName":"RODOLFO JORGE NEMER NOGUEIRA","cpfCnpj":"***.638.889-**","type":"CHECKING_ACCOUNT","agency":"0","agencyDigit":null,"account":"000000","accountDigit":"0","pixAddressKey":"03663888940"},"recurring":null,"canBeCancelled":true}}
  // {"id":"evt_a6f35f7766677518090c06e66ea44a4e&1155749477","event":"TRANSFER_DONE","dateCreated":"2025-12-12 15:56:17","transfer":{"object":"transfer","id":"1ba54c62-18b3-4311-8e82-56c7f7eb7534","value":0.02,"netValue":0.02,"transferFee":0,"dateCreated":"2025-12-12","status":"DONE","effectiveDate":"2025-12-12 15:56:11","confirmedDate":"2025-12-12","endToEndIdentifier":"E19540550202512121833ZEOWFHVU9M1","transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/h\/UElYX1RSQU5TQUNUSU9OX0RPTkU6YjJmNGVkNjAtZmEzOS00ODRlLTgzNDktNjcyMzdiYmIzNzQ1%0A","operationType":"PIX","failReason":null,"walletId":null,"description":"Transa\u00e7\u00e3o Pix Teste 2","externalReference":"176556443764","authorized":true,"scheduleDate":null,"type":"BANK_ACCOUNT","bankAccount":{"bank":{"code":"077","name":"BANCO INTER S.A.","ispb":"00416968"},"accountName":null,"ownerName":"RODOLFO JORGE NEMER NOGUEIRA","cpfCnpj":"***.638.889-**","type":"CHECKING_ACCOUNT","agency":"0","agencyDigit":null,"account":"000000","accountDigit":"0","pixAddressKey":"03663888940"},"recurring":null,"canBeCancelled":false}}

  // Crédito PAYMENT_CREATED > PAYMENT_CONFIRMED
  // {"id":"evt_05b708f961d739ea7eba7e4db318f621&12900592","event":"PAYMENT_CREATED","dateCreated":"2025-11-29 21:12:10","payment":{"object":"payment","id":"pay_cgc7l1ccsobs2va5","dateCreated":"2025-11-29","customer":"cus_000007259639","installment":"76082144-9a5f-43b6-b977-760f9a503fd2","checkoutSession":null,"paymentLink":null,"value":1252.07,"netValue":1208.14,"originalValue":null,"interestValue":null,"description":"Parcela 1 de 2. [1] Rodolfo - 176446152594","billingType":"CREDIT_CARD","confirmedDate":"2025-11-29","creditCard":{"creditCardNumber":"4444","creditCardBrand":"VISA","creditCardToken":"3df971b8-8cbb-488e-80bb-6e56cacc1de7"},"pixTransaction":null,"status":"CONFIRMED","dueDate":"2025-12-09","originalDueDate":"2025-12-09","paymentDate":null,"clientPaymentDate":"2025-11-29","installmentNumber":1,"invoiceUrl":"https:\/\/sandbox.asaas.com\/i\/cgc7l1ccsobs2va5","invoiceNumber":"12125022","externalReference":"176446152594","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2025-12-31","estimatedCreditDate":"2025-12-31","transactionReceiptUrl":"https:\/\/sandbox.asaas.com\/comprovantes\/3476380772610775","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":null}}
  // {"id":"evt_05b708f961d739ea7eba7e4db318f621&12900593","event":"PAYMENT_CREATED","dateCreated":"2025-11-29 21:12:10","payment":{"object":"payment","id":"pay_x5j5ibfqc7ao50tq","dateCreated":"2025-11-29","customer":"cus_000007259639","installment":"76082144-9a5f-43b6-b977-760f9a503fd2","checkoutSession":null,"paymentLink":null,"value":1252.08,"netValue":1208.15,"originalValue":null,"interestValue":null,"description":"Parcela 2 de 2. [1] Rodolfo - 176446152594","billingType":"CREDIT_CARD","confirmedDate":"2025-11-29","creditCard":{"creditCardNumber":"4444","creditCardBrand":"VISA","creditCardToken":"3df971b8-8cbb-488e-80bb-6e56cacc1de7"},"pixTransaction":null,"status":"CONFIRMED","dueDate":"2026-01-09","originalDueDate":"2026-01-09","paymentDate":null,"clientPaymentDate":"2025-11-29","installmentNumber":2,"invoiceUrl":"https:\/\/sandbox.asaas.com\/i\/x5j5ibfqc7ao50tq","invoiceNumber":"12125023","externalReference":"176446152594","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2026-02-02","estimatedCreditDate":"2026-02-02","transactionReceiptUrl":"https:\/\/sandbox.asaas.com\/comprovantes\/3476380772610775","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":null}}
  // {"id":"evt_15e444ff9b9ab9ec29294aa1abe68025&12900594","event":"PAYMENT_CONFIRMED","dateCreated":"2025-11-29 21:12:10","payment":{"object":"payment","id":"pay_cgc7l1ccsobs2va5","dateCreated":"2025-11-29","customer":"cus_000007259639","installment":"76082144-9a5f-43b6-b977-760f9a503fd2","checkoutSession":null,"paymentLink":null,"value":1252.07,"netValue":1208.14,"originalValue":null,"interestValue":null,"description":"Parcela 1 de 2. [1] Rodolfo - 176446152594","billingType":"CREDIT_CARD","confirmedDate":"2025-11-29","creditCard":{"creditCardNumber":"4444","creditCardBrand":"VISA","creditCardToken":"3df971b8-8cbb-488e-80bb-6e56cacc1de7"},"pixTransaction":null,"status":"CONFIRMED","dueDate":"2025-12-09","originalDueDate":"2025-12-09","paymentDate":null,"clientPaymentDate":"2025-11-29","installmentNumber":1,"invoiceUrl":"https:\/\/sandbox.asaas.com\/i\/cgc7l1ccsobs2va5","invoiceNumber":"12125022","externalReference":"176446152594","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2025-12-31","estimatedCreditDate":"2025-12-31","transactionReceiptUrl":"https:\/\/sandbox.asaas.com\/comprovantes\/3476380772610775","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":null}}
  // {"id":"evt_15e444ff9b9ab9ec29294aa1abe68025&12900595","event":"PAYMENT_CONFIRMED","dateCreated":"2025-11-29 21:12:10","payment":{"object":"payment","id":"pay_x5j5ibfqc7ao50tq","dateCreated":"2025-11-29","customer":"cus_000007259639","installment":"76082144-9a5f-43b6-b977-760f9a503fd2","checkoutSession":null,"paymentLink":null,"value":1252.08,"netValue":1208.15,"originalValue":null,"interestValue":null,"description":"Parcela 2 de 2. [1] Rodolfo - 176446152594","billingType":"CREDIT_CARD","confirmedDate":"2025-11-29","creditCard":{"creditCardNumber":"4444","creditCardBrand":"VISA","creditCardToken":"3df971b8-8cbb-488e-80bb-6e56cacc1de7"},"pixTransaction":null,"status":"CONFIRMED","dueDate":"2026-01-09","originalDueDate":"2026-01-09","paymentDate":null,"clientPaymentDate":"2025-11-29","installmentNumber":2,"invoiceUrl":"https:\/\/sandbox.asaas.com\/i\/x5j5ibfqc7ao50tq","invoiceNumber":"12125023","externalReference":"176446152594","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2026-02-02","estimatedCreditDate":"2026-02-02","transactionReceiptUrl":"https:\/\/sandbox.asaas.com\/comprovantes\/3476380772610775","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":null}}

  // Crédito PAYMENT_REFUNDED
  // {"id":"evt_d313266b1bd58128da69a2a96d82e79e&1151280349","event":"PAYMENT_REFUNDED","dateCreated":"2025-12-09 12:09:10","payment":{"object":"payment","id":"pay_697cbacod93loimb","dateCreated":"2025-12-09","customer":"cus_000151911871","checkoutSession":null,"paymentLink":null,"value":6.75,"netValue":6.13,"originalValue":null,"interestValue":null,"description":"[1] Rodolfo Jorge Nemer Nogueira  - 176529287351","billingType":"CREDIT_CARD","confirmedDate":"2025-12-09","creditCard":{"creditCardNumber":"1973","creditCardBrand":"MASTERCARD"},"pixTransaction":null,"status":"REFUNDED","dueDate":"2025-12-19","originalDueDate":"2025-12-19","paymentDate":null,"clientPaymentDate":"2025-12-09","installmentNumber":null,"invoiceUrl":"https:\/\/www.asaas.com\/i\/697cbacod93loimb","invoiceNumber":"697309699","externalReference":"176529287351","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2026-01-12","estimatedCreditDate":"2026-01-12","transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/5896440265952909","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"postalService":false,"escrow":null,"refunds":[{"dateCreated":"2025-12-09 12:09:08","status":"DONE","value":6.75,"endToEndIdentifier":null,"transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/5896440265952909","description":null,"refundedSplits":null}]}}
  // {"id":"evt_d313266b1bd58128da69a2a96d82e79e&1204579163","event":"PAYMENT_REFUNDED","dateCreated":"2026-01-30 15:45:42","account":{"id":"282a2fd8-f84e-458d-a1b3-d3a76b98f334","ownerId":null},"payment":{"object":"payment","id":"pay_2igf8gxipm3dnbvg","dateCreated":"2026-01-30","customer":"cus_000158931556","checkoutSession":null,"paymentLink":null,"value":6.17,"netValue":5.56,"originalValue":null,"interestValue":null,"description":"[1] Rodolfo Nogueira Aluno - 176979833234","billingType":"CREDIT_CARD","confirmedDate":"2026-01-30","creditCard":{"creditCardNumber":"1973","creditCardBrand":"MASTERCARD"},"pixTransaction":null,"status":"REFUNDED","dueDate":"2026-02-09","originalDueDate":"2026-02-09","paymentDate":null,"clientPaymentDate":"2026-01-30","installmentNumber":null,"invoiceUrl":"https:\/\/www.asaas.com\/i\/2igf8gxipm3dnbvg","invoiceNumber":"732940060","externalReference":"176979833234","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2026-03-03","estimatedCreditDate":"2026-03-03","transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/4474225603435845","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"postalService":false,"escrow":null,"refunds":[{"dateCreated":"2026-01-30 15:45:40","status":"DONE","value":6.17,"endToEndIdentifier":null,"transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/4474225603435845","description":null,"refundedSplits":null}]}}

  // Crédito PAYMENT_REFUNDED 2x
  // {"id":"evt_d313266b1bd58128da69a2a96d82e79e&1204619152","event":"PAYMENT_REFUNDED","dateCreated":"2026-01-30 16:13:03","account":{"id":"282a2fd8-f84e-458d-a1b3-d3a76b98f334","ownerId":null},"payment":{"object":"payment","id":"pay_tgnug7pg6irb3e5k","dateCreated":"2026-01-30","customer":"cus_000158931556","installment":"79d7d9a1-0264-4124-b280-1b60edf4855d","checkoutSession":null,"paymentLink":null,"value":7.3,"netValue":6.88,"originalValue":null,"interestValue":null,"description":"Parcela 1 de 2. [1] Rodolfo Nogueira Aluno - 176980015809","billingType":"CREDIT_CARD","confirmedDate":"2026-01-30","creditCard":{"creditCardNumber":"1973","creditCardBrand":"MASTERCARD"},"pixTransaction":null,"status":"REFUNDED","dueDate":"2026-02-09","originalDueDate":"2026-02-09","paymentDate":null,"clientPaymentDate":"2026-01-30","installmentNumber":1,"invoiceUrl":"https:\/\/www.asaas.com\/i\/tgnug7pg6irb3e5k","invoiceNumber":"732972740","externalReference":"176980015809","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2026-03-03","estimatedCreditDate":"2026-03-03","transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/0268421277250569","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":[{"dateCreated":"2026-01-30 16:13:00","status":"DONE","value":7.3,"endToEndIdentifier":null,"transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/0268421277250569","description":null,"refundedSplits":null}]}}
  // {"id":"evt_d313266b1bd58128da69a2a96d82e79e&1204619160","event":"PAYMENT_REFUNDED","dateCreated":"2026-01-30 16:13:03","account":{"id":"282a2fd8-f84e-458d-a1b3-d3a76b98f334","ownerId":null},"payment":{"object":"payment","id":"pay_489mm3ozuy5la74t","dateCreated":"2026-01-30","customer":"cus_000158931556","installment":"79d7d9a1-0264-4124-b280-1b60edf4855d","checkoutSession":null,"paymentLink":null,"value":7.31,"netValue":6.89,"originalValue":null,"interestValue":null,"description":"Parcela 2 de 2. [1] Rodolfo Nogueira Aluno - 176980015809","billingType":"CREDIT_CARD","confirmedDate":"2026-01-30","creditCard":{"creditCardNumber":"1973","creditCardBrand":"MASTERCARD"},"pixTransaction":null,"status":"REFUNDED","dueDate":"2026-03-09","originalDueDate":"2026-03-09","paymentDate":null,"clientPaymentDate":"2026-01-30","installmentNumber":2,"invoiceUrl":"https:\/\/www.asaas.com\/i\/489mm3ozuy5la74t","invoiceNumber":"732972747","externalReference":"176980015809","deleted":false,"anticipated":false,"anticipable":false,"creditDate":"2026-04-06","estimatedCreditDate":"2026-04-06","transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/0268421277250569","nossoNumero":null,"bankSlipUrl":null,"lastInvoiceViewedDate":null,"lastBankSlipViewedDate":null,"discount":{"value":0,"limitDate":null,"dueDateLimitDays":0,"type":"FIXED"},"fine":{"value":0,"type":"FIXED"},"interest":{"value":0,"type":"PERCENTAGE"},"postalService":false,"escrow":null,"refunds":[{"dateCreated":"2026-01-30 16:13:00","status":"DONE","value":7.31,"endToEndIdentifier":null,"transactionReceiptUrl":"https:\/\/www.asaas.com\/comprovantes\/0268421277250569","description":null,"refundedSplits":null}]}}

  public static function webhook(Request $req) {
    Log::channel('PAY')->info(__METHOD__ . ' - ' . json_encode($req->all()));

    // Validação do token Asaas
    $token = $req->header('asaas-access-token');
    if ($token !== 'a732fd07-26cf-44a2-8c5f-b69d1e1103be') return response('Token inválido', 200);

    // ----- pagamento
    $reference_id = data_get($req, 'payment.externalReference');
    if (!$reference_id) return response('reference_id não encontrado: ' . $reference_id, 200);
    $pagamento = Pagamento::where('reference_id', $reference_id)->first();
    if (!$pagamento) return response('Pagamento não encontrado: ' . $reference_id, 200);

    // Log webhookJ
    $webhookJ = data_get($pagamento, 'webhookJ', []);
    $webhookJ[] = [
      'event_id'      => $req->id,
      'event'         => $req->event,
      'dateCreated'   => $req->dateCreated,
      'status'        => $req->input('payment.status'),
      'value'         => $req->input('payment.value'),
    ];
    $pagamento->webhookJ = $webhookJ;
    $pagamento->save();

    switch($req->event) {
      case 'PAYMENT_CREATED': break;

      // ▄▀▀ ▄▀▄ █▄ █ █▀ █ █▀▄ █▄ ▄█ ██▀ █▀▄
      // ▀▄▄ ▀▄▀ █ ▀█ █▀ █ █▀▄ █ ▀ █ █▄▄ █▄▀
      case 'PAYMENT_CONFIRMED':
        if ($pagamento->status !== 'Confirmado') {
          $pagamento->confirmed_at  = data_get($req, 'payment.confirmedDate');
          $pagamento->statusAsaas   = 'PAYMENT_CONFIRMED';
          $pagamento->status        = 'Confirmado';
          $pagamento->save();
        }

        // ----- pagamentoItem
        $payment_id = data_get($req, 'payment.id');
        if (PagamentoItem::where('asaas_id', $payment_id)->exists()) {
          return response('PagamentoItem já exite: ' . $payment_id, 200);
        }
        $pagamentoItem                  = new PagamentoItem();
        $pagamentoItem->pagamento_id    = $pagamento->id;
        $pagamentoItem->instrutor_id    = $pagamento->instrutor_id;
        $pagamentoItem->asaas_id        = $payment_id;
        $pagamentoItem->statusAsaas     = $req->input('payment.status');
        $pagamentoItem->parcela         = data_get($req, 'payment.installmentNumber') ?? 1;
        $pagamentoItem->parcelas        = $pagamento->parcelas;
        $pagamentoItem->confirmed_at    = data_get($req, 'payment.confirmedDate');
        $pagamentoItem->estimated_at    = data_get($req, 'payment.estimatedCreditDate');
        $pagamentoItem->vBruto          = data_get($req, 'payment.value');
        $pagamentoItem->vLiquido        = data_get($req, 'payment.netValue');
        if (data_get($pagamento, 'valoresJ.vInstrFinal')) {
          $pagamentoItem->vDevido         = $pagamento->valoresJ->vInstrFinal / $pagamento->parcelas;
          $pagamentoItem->vDevidoRent     = $pagamento->valoresJ->vInstrRent / $pagamento->parcelas;
        } else if (data_get($pagamento, 'valoresJ.chatHash')) {
          $pagamentoItem->vDevido         = $pagamento->valoresJ->vBruto / $pagamento->parcelas;
          $pagamentoItem->vDevidoRent     = null;
        }
        $pagamentoItem->vPago           = null;
        $pagamentoItem->vPagoRent       = null;
        $pagamentoItem->save();

        // ----- aula
        AulaRepository::insertAula($pagamento->id);

        break;

      // █▀▄ ██▀ ▄▀▀ ██▀ █ █ █ ██▀ █▀▄
      // █▀▄ █▄▄ ▀▄▄ █▄▄ █ ▀▄▀ █▄▄ █▄▀
      case 'PAYMENT_RECEIVED':
        if ($pagamento->status !== 'Confirmado') {
          $pagamento->received_at   = new Carbon();
          if (!$pagamento->confirmed_at) $pagamento->confirmed_at = new Carbon();
          $pagamento->statusAsaas   = 'PAYMENT_RECEIVED';
          $pagamento->status        = 'Confirmado';
          $pagamento->save();
        }

        // ----- pagamentoItem
        $payment_id                     = data_get($req, 'payment.id');
        $pagamentoItem                  = PagamentoItem::firstOrNew(['asaas_id' => $payment_id]);
        $pagamentoItem->pagamento_id    = $pagamento->id;
        $pagamentoItem->instrutor_id    = $pagamento->instrutor_id;
        $pagamentoItem->asaas_id        = $payment_id;
        $pagamentoItem->statusAsaas     = $req->input('payment.status');
        $pagamentoItem->parcela         = data_get($req, 'payment.installmentNumber') ?? 1;
        $pagamentoItem->parcelas        = $pagamento->parcelas;
        if (!$pagamentoItem->confirmed_at) {
          $pagamentoItem->confirmed_at  = data_get($req, 'payment.confirmedDate') ?? new Carbon();
        }
        if (!$pagamentoItem->estimated_at) {
          $pagamentoItem->estimated_at  = data_get($req, 'payment.estimatedCreditDate') ?? new Carbon();
        }
        $pagamentoItem->received_at     = new Carbon();
        $pagamentoItem->vBruto          = data_get($req, 'payment.value');
        $pagamentoItem->vLiquido        = data_get($req, 'payment.netValue');
        // Pagamanto via cadastro
        if (data_get($pagamento, 'valoresJ.vInstrFinal')) {
          $pagamentoItem->vDevido         = $pagamento->valoresJ->vInstrFinal / $pagamento->parcelas;
          $pagamentoItem->vDevidoRent     = $pagamento->valoresJ->vInstrRent / $pagamento->parcelas;
        } else if (data_get($pagamento, 'valoresJ.chatHash')) {
          $pagamentoItem->vDevido         = $pagamento->valoresJ->vBruto;
          $pagamentoItem->vDevidoRent     = null;
        }
        $pagamentoItem->vPago           = null;
        $pagamentoItem->vPagoRent       = null;
        $pagamentoItem->save();

        // ----- aula
        AulaRepository::insertAula($pagamento->id);

        break;
    }

    return response('Webhook processado', 200);
  }

}