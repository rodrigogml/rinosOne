<?php

namespace Tests\Feature;

use Tests\TestCase;

class VisualPreferencesBootstrapTest extends TestCase
{
    public function test_document_bootstrap_applies_only_safe_visual_preferences_before_vite_mounts(): void
    {
        $template = file_get_contents(resource_path('views/application.blade.php'));
        $bootstrapPosition = strpos($template, "const key = 'rinos-one.visual-preferences.v1';");
        $vitePosition = strpos($template, "@vite(['resources/css/app.css', 'resources/js/app.ts'])");

        $this->assertNotFalse($bootstrapPosition);
        $this->assertNotFalse($vitePosition);
        $this->assertLessThan($vitePosition, $bootstrapPosition);
        $this->assertStringContainsString('root.dataset.theme = preferences.theme;', $template);
        $this->assertStringContainsString('root.dataset.fontScale = preferences.fontScale;', $template);
        $this->assertStringContainsString('root.dataset.spacingScale = preferences.spacingScale;', $template);
        $this->assertStringContainsString('root.dataset.componentScale = preferences.componentScale;', $template);
        $this->assertStringContainsString('root.lang = preferences.locale;', $template);
        $this->assertStringNotContainsString('preferences.password', $template);
        $this->assertStringNotContainsString('preferences.token', $template);
        $this->assertStringNotContainsString('preferences.challengeId', $template);
    }
}
