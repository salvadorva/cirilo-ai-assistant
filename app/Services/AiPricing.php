<?php

namespace App\Services;

class AiPricing
{
    public function estimate(string $provider, string $model, array $usage, array $measures = []): array
    {
        $model = config('ai.aliases')[$model] ?? $model;
        $price = config('ai.prices')[$provider][$model] ?? null;
        $unknown = fn (string $reason) => ['estimated_cost' => null, 'cost_status' => $reason, 'pricing_date' => $price ? config('ai.pricing_date') : null];
        if (! $price) {
            return $unknown('unknown_tariff');
        }
        if (! in_array($measures['service_tier'] ?? 'default', ['default', 'auto', 'standard'], true)) {
            return $unknown('unknown_service_tier');
        }
        if (isset($price['characters'])) {
            if (! is_numeric($measures['characters'] ?? null) || $measures['characters'] < 0) {
                return $unknown('missing_usage');
            }
            $cost = $measures['characters'] * $price['characters'] / 1_000_000;
        } elseif (isset($price['minute'])) {
            if (! is_numeric($measures['duration_seconds'] ?? null) || $measures['duration_seconds'] < 0) {
                return $unknown('missing_duration');
            }
            $cost = $measures['duration_seconds'] / 60 * $price['minute'];
        } else {
            $input = $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null;
            $output = $usage['output_tokens'] ?? $usage['completion_tokens'] ?? null;
            $details = $usage['input_tokens_details'] ?? $usage['prompt_tokens_details'] ?? [];
            $cached = $details['cached_tokens'] ?? 0;
            if (! is_numeric($input) || ! is_numeric($output) || ! is_numeric($cached) || $input < 0 || $output < 0 || $cached < 0 || $cached > $input) {
                return $unknown('missing_usage');
            }
            if (isset($price['image_input'])) {
                if (! is_numeric($details['text_tokens'] ?? null) || ! is_numeric($details['image_tokens'] ?? null)
                    || $details['text_tokens'] < 0 || $details['image_tokens'] < 0
                    || $input != $details['text_tokens'] + $details['image_tokens'] || $cached > 0) {
                    return $unknown('missing_image_usage');
                }
                $cost = ($details['text_tokens'] * $price['input'] + $details['image_tokens'] * $price['image_input'] + $output * $price['output']) / 1_000_000;
            } else {
                if ($cached > 0 && ! isset($price['cached'])) {
                    return $unknown('unknown_cached_tariff');
                }
                $cost = (($input - $cached) * $price['input'] + $cached * ($price['cached'] ?? 0) + $output * $price['output']) / 1_000_000;
            }
        }
        if (! empty($measures['unsupported_tools'])) {
            return $unknown('unknown_tool_tariff');
        }
        $searches = $measures['web_search_calls'] ?? 0;
        if ($searches) {
            if (($measures['search_tool'] ?? null) !== 'web_search_preview' || ! in_array($model, ['gpt-4.1', 'gpt-4.1-mini', 'gpt-4o', 'gpt-4o-mini'], true)) {
                return $unknown('unknown_tool_tariff');
            }
            $cost += $searches * config('ai.web_search_preview_per_call');
        }

        return ['estimated_cost' => round($cost, 8), 'cost_status' => 'estimated', 'pricing_date' => config('ai.pricing_date')];
    }
}
