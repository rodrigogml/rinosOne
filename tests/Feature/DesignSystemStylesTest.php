<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignSystemStylesTest extends TestCase
{
    public function test_design_system_layers_and_theme_contract_are_available(): void
    {
        $stylesPath = resource_path('css/design-system');
        $tokens = file_get_contents($stylesPath.'/tokens.css');
        $themes = file_get_contents($stylesPath.'/themes.css');
        $base = file_get_contents($stylesPath.'/base.css');
        $components = file_get_contents($stylesPath.'/components.css');
        $entrypoint = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString("@import './design-system/tokens.css';", $entrypoint);
        $this->assertStringContainsString("@import './design-system/themes.css';", $entrypoint);
        $this->assertStringContainsString("@import './design-system/base.css';", $entrypoint);
        $this->assertStringContainsString("@import './design-system/components.css';", $entrypoint);
        $this->assertStringContainsString('--palette-industrial-ruby-light-action: #aa2643;', $tokens);
        $this->assertStringContainsString('--palette-executive-coral-dark-action:', $tokens);
        $this->assertSame(20, preg_match_all('/--palette-[a-z-]+-(?:light|dark)-action:/', $tokens));
        $this->assertStringContainsString('--color-canvas-background:', $themes);
        $this->assertStringContainsString('--font-size-md: calc(1rem * var(--font-scale));', $tokens);
        $this->assertStringContainsString('--space-4: calc(1rem * var(--spacing-scale));', $tokens);
        $this->assertStringContainsString('--control-height-md: max(2.75rem, calc(2.75rem * var(--component-scale)));', $tokens);
        $this->assertStringContainsString(":root[data-theme='light']", $themes);
        $this->assertStringContainsString(":root[data-theme='dark']", $themes);
        $this->assertStringContainsString('--color-focus-ring:', $themes);
        $this->assertStringContainsString('--shadow-card:', $themes);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $base);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\\b/i', $themes);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\\b/i', $base);
        $this->assertStringContainsString('--component-card-background: var(--color-surface);', $components);
        $this->assertStringContainsString('--component-button-primary-background: var(--color-action-primary);', $components);
        $this->assertStringContainsString('.app-shell--centered .app-shell__content { display: grid; justify-items: center; }', $components);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\\b/i', $components);
    }

    public function test_each_preference_scale_has_independent_supported_values(): void
    {
        $tokens = file_get_contents(resource_path('css/design-system/tokens.css'));

        $this->assertStringContainsString(":root[data-font-scale='compact']", $tokens);
        $this->assertStringContainsString(":root[data-font-scale='comfortable']", $tokens);
        $this->assertStringContainsString(":root[data-spacing-scale='compact']", $tokens);
        $this->assertStringContainsString(":root[data-spacing-scale='comfortable']", $tokens);
        $this->assertStringContainsString(":root[data-component-scale='compact']", $tokens);
        $this->assertStringContainsString(":root[data-component-scale='comfortable']", $tokens);
    }

    public function test_primary_and_text_tokens_meet_the_minimum_contrast_for_their_documented_surfaces(): void
    {
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio('#aa2643', '#ffffff'));
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio('#0f172a', '#ffffff'));
        $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio('#f8fafc', '#0b1220'));
    }

    private function contrastRatio(string $first, string $second): float
    {
        $firstLuminance = $this->relativeLuminance($first);
        $secondLuminance = $this->relativeLuminance($second);

        return (max($firstLuminance, $secondLuminance) + 0.05) / (min($firstLuminance, $secondLuminance) + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = sscanf($hex, '#%02x%02x%02x');
        $linearChannels = array_map(
            static function (int $channel): float {
                $value = $channel / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            $channels,
        );

        return ($linearChannels[0] * 0.2126) + ($linearChannels[1] * 0.7152) + ($linearChannels[2] * 0.0722);
    }
}
