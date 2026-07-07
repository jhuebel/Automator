<?php

namespace App\Jobs;

use App\Events\ClaudeStreamFailed;
use App\Events\ClaudeStreamFinished;
use App\Events\ClaudeTokenReceived;
use App\Models\AppSetting;
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class StreamClaudeCompletionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public string $requestId,
        public string $system,
        public string $user,
    ) {}

    public function handle(): void
    {
        $settings = AppSetting::current();

        if (blank($settings->anthropic_api_key)) {
            broadcast(new ClaudeStreamFailed($this->requestId, 'Anthropic API key is not configured.'));

            return;
        }

        $payload = [
            'model' => $settings->anthropic_model,
            'max_tokens' => 4096,
            'stream' => true,
            'system' => $this->system,
            'messages' => [['role' => 'user', 'content' => $this->user]],
        ];

        // Haiku models reject the effort parameter outright.
        if (! str_contains(strtolower($settings->anthropic_model), 'haiku')) {
            $payload['output_config'] = ['effort' => $settings->anthropic_effort];
        }

        try {
            $client = new Client;
            $response = $client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $settings->anthropic_api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => $payload,
                'stream' => true,
            ]);

            $body = $response->getBody();
            $buffer = '';

            while (! $body->eof()) {
                $buffer .= $body->read(1024);

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    $this->processLine(trim($line));
                }
            }
        } catch (Throwable $e) {
            Log::error('Claude streaming failed', ['request_id' => $this->requestId, 'error' => $e->getMessage()]);
            broadcast(new ClaudeStreamFailed($this->requestId, $e->getMessage()));

            return;
        }

        broadcast(new ClaudeStreamFinished($this->requestId));
    }

    private function processLine(string $line): void
    {
        if (! str_starts_with($line, 'data:')) {
            return;
        }

        $json = trim(substr($line, 5));

        if ($json === '' || $json === '[DONE]') {
            return;
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        if (($data['type'] ?? null) === 'content_block_delta' && isset($data['delta']['text'])) {
            broadcast(new ClaudeTokenReceived($this->requestId, $data['delta']['text']));
        }
    }
}
