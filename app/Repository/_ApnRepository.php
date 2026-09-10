<?php

namespace App\Repository;

use App\Models\PushToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;
use Firebase\JWT\JWT;

class _ApnRepository
{
    private static $config = null;

    /**
     * Lê e faz o cache em memória das credenciais do arquivo JSON
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            $jsonPath = storage_path('apn/dirigiragora.json');

            if (!file_exists($jsonPath)) {
                throw new \Exception("Arquivo de configuração APNs não encontrado: {$jsonPath}");
            }

            self::$config = json_decode(file_get_contents($jsonPath), true);
        }

        return self::$config;
    }

    /**
     * Gera o JWT obrigatório para autenticar na APNs
     */
    private static function generateJwt(): string
    {
        $config = self::getConfig();
        $keyId = $config['APN_KEY_ID'];
        $teamId = $config['APN_TEAM_ID'];

        // Lê o conteúdo do arquivo .p8
        $privateKey = file_get_contents(storage_path('apn/AuthKey_F7QPSSB6C4.p8'));

        $payload = [
            'iss' => $teamId,
            'iat' => time()
        ];

        // A Apple exige o algoritmo ES256 para o JWT
        return JWT::encode($payload, $privateKey, 'ES256', $keyId);
    }

    /**
     * Retorna a URL base do ambiente (Sandbox ou Produção)
     */
    private static function getBaseUrl(): string
    {
        $config = self::getConfig();
        return $config['APN_ENV'] === 'production'
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';
    }

    /**
     * Prepara o corpo do push no formato esperado pela Apple
     */
    private static function buildPayload(string $titulo, string $mensagem, array $data = []): array
    {
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $titulo,
                    'body' => $mensagem
                ],
                'sound' => 'default',
            ]
        ];

        // Dados customizados (url, pagina, etc) devem ficar na raiz do JSON no APNs
        if (!empty($data)) {
            $payload = array_merge($payload, $data);
        }

        return $payload;
    }

    /**
     * Analisa o erro retornado pela Apple e limpa o token se necessário
     */
    private static function handleApnError(string $token, int $status, array $responseBody)
    {
        $reason = $responseBody['reason'] ?? 'Unknown';

        // 410 = App desinstalado/Token expirado | 400 BadDeviceToken = Token inválido
        if ($status === 410 || ($status === 400 && $reason === 'BadDeviceToken')) {
            PushToken::where('token', $token)->delete();
            Log::channel('email')->warning('Token APNs removido (Inválido/Inativo)', ['token' => $token, 'reason' => $reason]);
        } else {
            Log::channel('email')->error('Erro na APNs', ['token' => $token, 'status' => $status, 'reason' => $reason]);
        }
    }

    public static function register(Request $req)
    {
        $pushToken = PushToken::firstOrNew(['token' => $req->token]);
        $pushToken->token        = $req->token;
        $pushToken->owner_type   = $req->aluno ? 'aluno' : 'instrutor';
        $pushToken->owner_id     = $req->aluno ? $req->aluno->id : $req->instrutor->id;
        $pushToken->platform     = $req->platform; // 'ios'
        $pushToken->last_seen_at = now();
        $pushToken->save();
    }

    public static function sendNotificationToken(string $token, string $titulo, string $mensagem, array $data = [])
    {
        Log::channel('email')->info(__METHOD__ . ' - ' . $token);

        try {
            $jwt = self::generateJwt();
            $url = self::getBaseUrl() . '/3/device/' . $token;

            $config = self::getConfig();
            $bundleId = $config['APN_BUNDLE_ID'];

            // Envia a requisição HTTP/2 via facade Http do Laravel
            $response = Http::withHeaders([
                'Authorization'  => 'bearer ' . $jwt,
                'apns-topic'     => $bundleId,
                'apns-push-type' => 'alert'
            ])
            ->withOptions([
                'version' => 2.0,
            ])
            ->post($url, self::buildPayload($titulo, $mensagem, $data));

            if (!$response->successful()) {
                self::handleApnError($token, $response->status(), $response->json() ?? []);
            }

        } catch (\Throwable $e) {
            Log::channel('email')->error('Erro geral no envio APNs', ['error' => $e->getMessage()]);
        }
    }

    public static function sendNotificationTokens(array $tokens, string $titulo, string $mensagem, array $data = [])
    {
        Log::channel('email')->info(__METHOD__ . ' - ' . count($tokens) . ' ' . $mensagem);

        if (empty($tokens)) return ['success' => 0, 'failure' => 0];

        try {
            $jwt = self::generateJwt();
            $baseUrl = self::getBaseUrl();

            $config = self::getConfig();
            $bundleId = $config['APN_BUNDLE_ID'];

            $payload = self::buildPayload($titulo, $mensagem, $data);

            $successCount = 0;
            $failureCount = 0;

            // Dispara requisições simultâneas e assíncronas no Laravel
            $responses = Http::pool(function (Pool $pool) use ($tokens, $baseUrl, $jwt, $bundleId, $payload) {
                foreach ($tokens as $token) {
                    $pool->as($token)
                        ->withHeaders([
                            'Authorization'  => 'bearer ' . $jwt,
                            'apns-topic'     => $bundleId,
                            'apns-push-type' => 'alert'
                        ])
                        ->withOptions([
                            'version' => 2.0,
                        ])
                        ->post("$baseUrl/3/device/$token", $payload);
                }
            });

            // Processa as respostas do Pool
            foreach ($responses as $token => $response) {
                if ($response instanceof \Exception || !$response->successful()) {
                    $failureCount++;
                    $status = $response instanceof \Exception ? 500 : $response->status();
                    $body = $response instanceof \Exception ? [] : ($response->json() ?? []);

                    self::handleApnError($token, $status, $body);
                } else {
                    $successCount++;
                }
            }

            Log::channel('email')->info('Push APNs multicast', ['success' => $successCount, 'failure' => $failureCount]);

            return ['success' => $successCount, 'failure' => $failureCount];

        } catch (\Throwable $e) {
            Log::channel('email')->error('Erro ao enviar push multicast APNs', ['error' => $e->getMessage()]);
            return ['success' => 0, 'failure' => count($tokens)];
        }
    }
}