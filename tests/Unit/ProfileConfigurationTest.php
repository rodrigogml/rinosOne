<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProfileConfigurationTest extends TestCase
{
    public function test_avatar_processing_defaults_match_the_profile_contract(): void
    {
        $avatar = config('profile.avatar');

        $this->assertSame(['image/jpeg', 'image/png', 'image/webp'], $avatar['allowedMimeTypes']);
        $this->assertSame(10 * 1024 * 1024, $avatar['maximumSizeBytes']);
        $this->assertSame(400, $avatar['minimumDimensionPixels']);
        $this->assertSame(400, $avatar['outputDimensionPixels']);
    }

    public function test_avatar_limits_can_be_adjusted_by_the_deployed_configuration(): void
    {
        config()->set('profile.avatar.maximumSizeBytes', 5 * 1024 * 1024);
        config()->set('profile.avatar.minimumDimensionPixels', 600);
        config()->set('profile.avatar.outputDimensionPixels', 600);

        $this->assertSame(5 * 1024 * 1024, config('profile.avatar.maximumSizeBytes'));
        $this->assertSame(600, config('profile.avatar.minimumDimensionPixels'));
        $this->assertSame(600, config('profile.avatar.outputDimensionPixels'));
    }
}
