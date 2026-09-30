<?php

namespace Tests\Unit;

use App\Services\AiPricing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiPricingTest extends TestCase
{
    public static function prices(): array
    {
        return [
            'responses with cache' => ['openai', 'gpt-4.1-2025-04-14', ['input_tokens' => 1000, 'output_tokens' => 200, 'input_tokens_details' => ['cached_tokens' => 400]], [], 0.003, 'estimated'],
            'chat extraction' => ['openai', 'gpt-4.1-mini', ['prompt_tokens' => 1000, 'completion_tokens' => 100], [], 0.00056, 'estimated'],
            'summary' => ['openai', 'gpt-4o-mini', ['prompt_tokens' => 1000, 'completion_tokens' => 100], [], 0.00021, 'estimated'],
            'web search' => ['openai', 'gpt-4.1', ['input_tokens' => 1000, 'output_tokens' => 200], ['web_search_calls' => 2, 'search_tool' => 'web_search_preview'], 0.0536, 'estimated'],
            'tts characters not tokens' => ['openai', 'tts-1', [], ['characters' => 5], 0.000075, 'estimated'],
            'stt duration not bytes' => ['openai', 'whisper-1', [], ['duration_seconds' => 90], 0.009, 'estimated'],
            'image tokens not flat price' => ['openai', 'gpt-image-1', ['input_tokens' => 150, 'output_tokens' => 1000, 'input_tokens_details' => ['text_tokens' => 100, 'image_tokens' => 50]], [], 0.041, 'estimated'],
            'legacy gpt4' => ['openai', 'gpt-4-0613', ['prompt_tokens' => 100, 'completion_tokens' => 100], [], 0.009, 'estimated'],
            'legacy tutor' => ['openai', 'gpt-3.5-turbo-0125', ['prompt_tokens' => 100, 'completion_tokens' => 100], [], 0.0002, 'estimated'],
            'unknown' => ['openai', 'future-model', [], [], null, 'unknown_tariff'],
            'provider matters' => ['grok', 'gpt-4.1', ['input_tokens' => 100, 'output_tokens' => 100], [], null, 'unknown_tariff'],
            'missing usage' => ['openai', 'gpt-4.1', [], [], null, 'missing_usage'],
            'invalid cache' => ['openai', 'gpt-4.1', ['input_tokens' => 100, 'output_tokens' => 2, 'input_tokens_details' => ['cached_tokens' => 101]], [], null, 'missing_usage'],
            'missing duration' => ['openai', 'whisper-1', [], [], null, 'missing_duration'],
            'negative duration' => ['openai', 'whisper-1', [], ['duration_seconds' => -1], null, 'missing_duration'],
            'missing chars' => ['openai', 'tts-1', [], [], null, 'missing_usage'],
            'missing image breakdown' => ['openai', 'gpt-image-1', ['input_tokens' => 100, 'output_tokens' => 1000], [], null, 'missing_image_usage'],
            'unsupported tool' => ['openai', 'gpt-4.1', ['input_tokens' => 10, 'output_tokens' => 2], ['unsupported_tools' => true], null, 'unknown_tool_tariff'],
            'nonstandard tier' => ['openai', 'gpt-4.1', ['input_tokens' => 10, 'output_tokens' => 2], ['service_tier' => 'priority'], null, 'unknown_service_tier'],
            'real zero not missing' => ['openai', 'gpt-4.1', ['input_tokens' => 0, 'output_tokens' => 0], [], 0.0, 'estimated'],
        ];
    }

    #[DataProvider('prices')]
    public function test_estimates_only_when_tariff_and_measurements_are_known($provider, $model, $usage, $measures, $cost, $status): void
    {
        $result = app(AiPricing::class)->estimate($provider, $model, $usage, $measures);
        $this->assertSame($cost, $result['estimated_cost']);
        $this->assertSame($status, $result['cost_status']);
        $this->assertSame($status === 'unknown_tariff' ? null : config('ai.pricing_date'), $result['pricing_date']);
    }
}
