<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /**
   * Cria uma nova instância do job.
   */
  public function __construct(
    public string $url,
    public array $data,
  ) {}

  /**
   * Executa o job.
   */
  public function handle(): void
  {
    try {
      $response = Http::timeout(10)->post($this->url, $this->data);

      if (!$response->ok()) {
        Log::error('Telegram sendMessage error', $response->json());
      }
    } catch (\Throwable $th) {
      Log::error('Telegram sendMessage exception', ['message' => $th->getMessage()]);
    }
  }
}
