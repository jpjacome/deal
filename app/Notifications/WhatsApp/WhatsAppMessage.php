<?php

namespace App\Notifications\WhatsApp;

use InvalidArgumentException;

/**
 * A WhatsApp template message: template name, body variables and quick-reply button payloads.
 */
class WhatsAppMessage
{
    /**
     * @param  list<string>  $parameters  Values for {{1}}, {{2}}… in order.
     * @param  list<string>  $buttonPayloads  Payload returned by Meta when each quick-reply button is tapped.
     */
    public function __construct(
        public string $template,
        public array $parameters = [],
        public array $buttonPayloads = [],
        public ?int $bookingId = null,
    ) {
        if (! array_key_exists($template, Templates::ALL)) {
            throw new InvalidArgumentException("Unknown WhatsApp template [{$template}].");
        }
    }

    /**
     * Cloud API request body.
     *
     * @return array<string, mixed>
     */
    public function toPayload(string $to): array
    {
        $components = [];

        if ($this->parameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn (string $text) => [
                    'type' => 'text',
                    // Meta rejects newlines/tabs and long runs of spaces inside variables.
                    'text' => trim(preg_replace('/\s+/', ' ', $text)) ?: '-',
                ], $this->parameters),
            ];
        }

        foreach ($this->buttonPayloads as $index => $payload) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'quick_reply',
                'index' => (string) $index,
                'parameters' => [['type' => 'payload', 'payload' => $payload]],
            ];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to' => ltrim($to, '+'),
            'type' => 'template',
            'template' => [
                'name' => $this->template,
                'language' => ['code' => config('services.whatsapp.language')],
                'components' => $components,
            ],
        ];
    }
}
