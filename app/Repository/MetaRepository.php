<?php

namespace App\Repository;

use App\Libraries\DefResponseTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaRepository
{
    use DefResponseTrait;

    // Dirigir Agora
    // WHATSAPP_BUSINESS_PHONE_NUMBER_ID=1310895208774063
    // TEMPLATE_NAME=alunosemresposta

    // // Simulado CNH do Brasil
    // WHATSAPP_BUSINESS_PHONE_NUMBER_ID=1270559622811895
    // TEMPLATE_NAME=status_etapa_teorica

    public static $access_token = 'EAAOpixHvZCZBsBSdxDb7R4CRNvvLG0nuIphDixpIbrtLGoy9tjvKwuk3fgwnz9ZApIrJRHfM3DE9ZAfLA6hpXdjxPntVgF2H0fRZCpw9mYhfUFKZCjwRlqPOKsavX1CtQZBcLgop7DvM830LfkZBlzoivwLEZBDYDZCSIghYt4opFqbEgegKPTA8DoQWH7ZCGzgCwZDZD';
    public static $api_version  = 'v20.0'; // Versão recomendada da API

    /**
     * Envia um template do WhatsApp via Meta API.
     *
     * @param string $fromPhoneId ID do número de telefone remetente (Phone Number ID)
     * @param string $template Nome do template aprovado no Meta
     * @param string $toPhone Número do destinatário (com código do país, ex: 5541999999999)
     * @param string $name Variável de nome que vai no corpo do template
     * @return object
     */
    public static function sendMessage($fromPhoneId, $template, $toPhone, $name)
    {
        // Limpa o número de destino para garantir que só tenha números
        $toPhoneClean = preg_replace('/\D/', '', $toPhone);

        $url = "https://graph.facebook.com/" . self::$api_version . "/{$fromPhoneId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $toPhoneClean,
            'type'              => 'template',
            'template' => [
                'name'     => $template,
                'language' => [
                    'code' => 'pt_BR'
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => (string) $name
                            ]
                        ]
                    ]
                ]
            ]
        ];

        try {
            // withToken() adiciona automaticamente o cabeçalho 'Authorization: Bearer <token>'
            $response = Http::withToken(self::$access_token)
                ->asJson()
                ->post($url, $payload);

            $json = (object) $response->json();

            // Se a requisição falhar (código 4xx ou 5xx), lança uma exceção
            $response->throw();

            // Log de sucesso
            Log::channel('whatsapp')->info(__METHOD__ . ' - SUCESSO: ' . json_encode($json));
            Log::channel('whatsapp')->info(__METHOD__ . ' - ' . $template . ' - ' . $toPhone . ' - ' . $name);

            return $json;

        } catch (Throwable $e) {
            // Tratamento de erros da API ou de conexão
            $errorData = [
                'message' => $e->getMessage(),
                'payload' => $payload,
                'response' => isset($response) ? $response->json() : null
            ];

            // Log de erro
            Log::channel('whatsapp')->error(__METHOD__ . ' - ERRO: ' . json_encode($errorData));

            // Como você usa o DefResponseTrait, você pode adaptar esse retorno
            // para o padrão da sua aplicação caso queira não estourar erro na tela.
            return (object) [
                'error' => true,
                'message' => $e->getMessage(),
                'details' => $errorData['response']
            ];
        }
    }
}