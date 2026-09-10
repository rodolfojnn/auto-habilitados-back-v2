<?php

namespace App\Repository;

use App\Models\PushToken;
use App\Models\SimPushToken;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ApnRepository
{
    /**
     * Bundle ID utilizado nas notificações APNs.
     *
     * Definido manualmente quando necessário, antes de chamar
     * sendNotificationTokens(). Ex.:
     * ApnRepository::bundleID = 'br.com.simulado.cnh.brasil';
     *
     * Quando não definido, assume o valor padrão 'br.com.dirigiragora'.
     */
    public static string $bundleID = 'br.com.dirigiragora';

    private static $config = null;

    /**
     * JWT em cache durante a vida do processo PHP.
     *
     * O JWT do APNs pode ser reutilizado enquanto estiver válido.
     */
    private static ?string $jwt = null;

    /**
     * Timestamp de criação do JWT em cache.
     */
    private static ?int $jwtCreatedAt = null;

    /**
     * Chave privada .p8 em cache durante a vida do processo PHP.
     */
    private static ?string $privateKey = null;

    /**
     * Quantidade máxima de requests simultâneos por lote.
     *
     * 50 é um valor conservador para começar.
     * Você pode ajustar posteriormente conforme sua infraestrutura.
     */
    private const POOL_SIZE = 50;

    /**
     * Quantidade de tentativas adicionais para erros temporários.
     */
    private const MAX_RETRIES = 2;

    /**
     * Delay entre tentativas, em milissegundos.
     */
    private const RETRY_DELAY_MS = 250;

    /**
     * Lê e faz o cache em memória das credenciais do arquivo JSON.
     */
    private static function getConfig(): array
    {
        if (self::$config === null) {
            $jsonPath = storage_path('apn/dirigiragora.json');

            if (! file_exists($jsonPath)) {
                throw new \Exception(
                    "Arquivo de configuração APNs não encontrado: {$jsonPath}"
                );
            }

            $config = json_decode(
                file_get_contents($jsonPath),
                true
            );

            if (! is_array($config)) {
                throw new \Exception(
                    'Arquivo de configuração APNs inválido.'
                );
            }

            self::$config = $config;
        }

        return self::$config;
    }

    /**
     * Gera ou reutiliza o JWT do APNs.
     *
     * O APNs aceita reutilização do JWT enquanto ele estiver dentro
     * da janela de validade.
     */
    private static function generateJwt(): string
    {
        $now = time();

        /*
         * Reutiliza o JWT por até 50 minutos.
         *
         * O token do APNs possui validade máxima de aproximadamente
         * 1 hora, então usamos uma margem de segurança.
         */
        if (
            self::$jwt !== null &&
            self::$jwtCreatedAt !== null &&
            ($now - self::$jwtCreatedAt) < 50 * 60
        ) {
            return self::$jwt;
        }

        $config = self::getConfig();

        $keyId = $config['APN_KEY_ID'];
        $teamId = $config['APN_TEAM_ID'];

        if (self::$privateKey === null) {
            $keyPath = storage_path('apn/AuthKey_F7QPSSB6C4.p8');

            if (! file_exists($keyPath)) {
                throw new \Exception(
                    "Chave privada APNs não encontrada: {$keyPath}"
                );
            }

            self::$privateKey = file_get_contents($keyPath);

            if (! self::$privateKey) {
                throw new \Exception(
                    'Não foi possível ler a chave privada APNs.'
                );
            }
        }

        $payload = [
            'iss' => $teamId,
            'iat' => $now,
        ];

        self::$jwt = JWT::encode(
            $payload,
            self::$privateKey,
            'ES256',
            $keyId
        );

        self::$jwtCreatedAt = $now;

        return self::$jwt;
    }

    /**
     * Retorna a URL base do ambiente.
     */
    private static function getBaseUrl(): string
    {
        $config = self::getConfig();

        return $config['APN_ENV'] === 'production'
            ? 'https://api.push.apple.com'
            : 'https://api.sandbox.push.apple.com';
    }

    /**
     * Prepara o payload do push.
     */
    private static function buildPayload(
        string $titulo,
        string $mensagem,
        array $data = []
    ): array {
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $titulo,
                    'body' => $mensagem,
                ],
                'sound' => 'default',
            ],
        ];

        if (! empty($data)) {
            $payload = array_merge($payload, $data);
        }

        return $payload;
    }

    /**
     * Verifica se um erro do APNs é temporário e pode ser tentado novamente.
     */
    private static function isRetryableResponse(
        int $status,
        string $reason = ''
    ): bool {
        /*
         * 429 = TooManyRequests
         * 500 = InternalServerError
         * 503 = ServiceUnavailable
         */
        return in_array($status, [429, 500, 503], true);
    }

    /**
     * Analisa o erro retornado pela Apple e limpa o token
     * quando ele não deve mais ser utilizado.
     */
    private static function handleApnError(
        string $token,
        int $status,
        array $responseBody
    ): void {
        $reason = $responseBody['reason'] ?? 'Unknown';

        /*
         * 410:
         * O device token não está mais ativo para o tópico.
         *
         * 400 BadDeviceToken:
         * Token inválido.
         */
        if (
            $status === 410 ||
            ($status === 400 && $reason === 'BadDeviceToken')
        ) {
            /*
             * A tabela de tokens depende do bundle:
             *
             * - br.com.dirigiragora        -> PushToken
             * - br.com.simulado.cnh.brasil -> SimPushToken
             */
            $tokenModel = self::$bundleID === 'br.com.simulado.cnh.brasil'
                ? SimPushToken::class
                : PushToken::class;

            $tokenModel::where('token', $token)->delete();

            Log::channel('email')->warning(
                'Token APNs removido (Inválido/Inativo)',
                [
                    'token' => $token,
                    'status' => $status,
                    'reason' => $reason,
                ]
            );

            return;
        }

        Log::channel('email')->error(
            'Erro na APNs',
            [
                'token' => $token,
                'status' => $status,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Registra o token do dispositivo.
     */
    public static function register(Request $req)
    {
        $pushToken = PushToken::firstOrNew([
            'token' => $req->token,
        ]);

        $pushToken->token = $req->token;
        $pushToken->owner_type = $req->aluno
            ? 'aluno'
            : 'instrutor';

        $pushToken->owner_id = $req->aluno
            ? $req->aluno->id
            : $req->instrutor->id;

        $pushToken->platform = $req->platform;
        $pushToken->last_seen_at = now();

        $pushToken->save();
    }

    /**
     * Envia uma notificação para um único token.
     *
     * Interface pública mantida para compatibilidade.
     */
    public static function sendNotificationToken(
        string $token,
        string $titulo,
        string $mensagem,
        array $data = []
    ) {
        Log::channel('email')->info(
            __METHOD__.' - '.$token
        );

        $result = self::sendNotificationTokens(
            [$token],
            $titulo,
            $mensagem,
            $data
        );

        return $result;
    }

    /**
     * Envia uma notificação para vários tokens.
     *
     * Interface pública mantida para compatibilidade.
     *
     * Os tokens são enviados em lotes controlados para evitar
     * abrir uma quantidade excessiva de requests simultâneos.
     */
    public static function sendNotificationTokens(
        array $tokens,
        string $titulo,
        string $mensagem,
        array $data = []
    ) {
        Log::channel('email')->info(
            __METHOD__.' - '.count($tokens).' tokens'
        );

        if (empty($tokens)) {
            return [
                'success' => 0,
                'failure' => 0,
            ];
        }

        try {
            /*
             * Remove tokens vazios e duplicados.
             *
             * Isso evita enviar duas vezes para o mesmo device
             * caso ele apareça repetido na entrada.
             */
            $tokens = array_values(
                array_unique(
                    array_filter(
                        $tokens,
                        static fn ($token) => is_string($token) && trim($token) !== ''
                    )
                )
            );

            if (empty($tokens)) {
                return [
                    'success' => 0,
                    'failure' => 0,
                ];
            }

            /*
             * O JWT é criado uma única vez.
             *
             * Todos os requests dessa execução reutilizam o mesmo JWT.
             */
            $jwt = self::generateJwt();

            $baseUrl = self::getBaseUrl();
            $bundleId = self::$bundleID;

            $payload = self::buildPayload(
                $titulo,
                $mensagem,
                $data
            );

            $successCount = 0;
            $failureCount = 0;

            /*
             * Processamos os tokens em lotes.
             *
             * Exemplo com 1.000 tokens:
             *
             * lote 1  -> 50
             * lote 2  -> 50
             * ...
             * lote 20 -> 50
             *
             * Dessa forma não tentamos disparar 1.000 requests
             * simultaneamente.
             */
            foreach (array_chunk($tokens, self::POOL_SIZE) as $batch) {
                $results = self::sendBatch(
                    $batch,
                    $jwt,
                    $baseUrl,
                    $bundleId,
                    $payload
                );

                $successCount += $results['success'];
                $failureCount += $results['failure'];
            }

            Log::channel('email')->info(
                'Push APNs multicast finalizado',
                [
                    'total' => count($tokens),
                    'success' => $successCount,
                    'failure' => $failureCount,
                ]
            );

            return [
                'success' => $successCount,
                'failure' => $failureCount,
            ];

        } catch (Throwable $e) {
            Log::channel('email')->error(
                'Erro ao enviar push multicast APNs',
                [
                    'error' => $e->getMessage(),
                    'total' => count($tokens),
                ]
            );

            return [
                'success' => 0,
                'failure' => count($tokens),
            ];
        }
    }

    /**
     * Envia um lote de tokens.
     */
    private static function sendBatch(
        array $tokens,
        string $jwt,
        string $baseUrl,
        string $bundleId,
        array $payload
    ): array {
        $pendingTokens = $tokens;

        /*
         * Gera um apns-id único por token para este lote.
         *
         * Em caso de retry (ex.: falha de conexão no meio do pool),
         * reutilizamos o MESMO apns-id + mesmo payload. Assim o APNs
         * reconhece que é a mesma notificação e evita entregar duplicada
         * para um device que já tinha recebido com sucesso.
         */
        $apnsIds = [];
        foreach ($tokens as $token) {
            $apnsIds[$token] = (string) Str::uuid();
        }

        $successCount = 0;
        $failureTokens = [];

        /*
         * Fazemos no máximo MAX_RETRIES + 1 rodadas.
         *
         * Somente erros temporários voltam para a fila.
         */
        for (
            $attempt = 0;
            $attempt <= self::MAX_RETRIES;
            $attempt++
        ) {
            if (empty($pendingTokens)) {
                break;
            }

            /*
             * Em uma tentativa posterior, damos um pequeno intervalo.
             */
            if ($attempt > 0) {
                usleep(
                    self::RETRY_DELAY_MS * 1000 * $attempt
                );
            }

            /*
             * Dispara o lote em paralelo.
             *
             * Importante: em falha de conexão/timeout (não resposta HTTP),
             * o Guzzle lança exceção dentro do Http::pool() — o array de
             * respostas nem chega a ser montado. Por isso o try/catch trata
             * o lote pendente como um todo e o manda novamente para retry.
             */
            try {
                $responses = Http::pool(
                    function (Pool $pool) use (
                        $pendingTokens,
                        $apnsIds,
                        $jwt,
                        $bundleId,
                        $baseUrl,
                        $payload
                    ) {
                        foreach ($pendingTokens as $index => $token) {
                            $pool->as((string) $index)
                                ->withHeaders([
                                    'Authorization' => 'bearer '.$jwt,
                                    'apns-id' => $apnsIds[$token],
                                    'apns-topic' => $bundleId,
                                    'apns-push-type' => 'alert',
                                ])
                                ->withOptions([
                                    'version' => 2.0,
                                    'connect_timeout' => 10,
                                    'timeout' => 30,
                                ])
                                ->post(
                                    $baseUrl.'/3/device/'.$token,
                                    $payload
                                );
                        }
                    }
                );
            } catch (Throwable $e) {
                /*
                 * Falha de conexão/timeout num dos requests.
                 *
                 * Como não sabemos qual token quebrou, reenviamos todo o
                 * lote pendente na próxima rodada. Se esgotarmos as
                 * tentativas, consideramos todos como falha.
                 */
                if ($attempt < self::MAX_RETRIES) {
                    Log::channel('email')->warning(
                        'Pool APNs falhou na conexão; lote entrará em retry',
                        [
                            'error' => $e->getMessage(),
                            'tokens' => count($pendingTokens),
                            'attempt' => $attempt + 1,
                        ]
                    );

                    usleep(self::RETRY_DELAY_MS * 1000);

                    continue;
                }

                Log::channel('email')->error(
                    'Pool APNs esgotou tentativas por falha de conexão',
                    [
                        'error' => $e->getMessage(),
                        'tokens' => count($pendingTokens),
                    ]
                );

                foreach ($pendingTokens as $token) {
                    $failureTokens[] = $token;
                }

                break;
            }

            $retryTokens = [];

            foreach ($pendingTokens as $index => $token) {
                $response = $responses[$index] ?? null;

                /*
                 * Segurança extra: se por qualquer motivo não houver
                 * resposta, trata como erro temporário e tenta de novo.
                 */
                if ($response === null) {
                    $retryTokens[] = $token;

                    continue;
                }

                /*
                 * Sucesso.
                 */
                if ($response->successful()) {
                    $successCount++;

                    continue;
                }

                $status = $response->status();
                $body = $response->json() ?? [];
                $reason = $body['reason'] ?? 'Unknown';

                /*
                 * Erro temporário:
                 * colocamos o token novamente na próxima rodada.
                 */
                if (
                    self::isRetryableResponse(
                        $status,
                        $reason
                    ) &&
                    $attempt < self::MAX_RETRIES
                ) {
                    $retryTokens[] = $token;

                    continue;
                }

                /*
                 * Erro definitivo.
                 */
                $failureTokens[] = $token;

                self::handleApnError(
                    $token,
                    $status,
                    $body
                );
            }

            $pendingTokens = $retryTokens;
        }

        return [
            'success' => $successCount,
            'failure' => count($failureTokens),
        ];
    }
}
