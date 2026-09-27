<?php

namespace Tests\Unit;

use App\Domain\Profile\Exception\AvatarProcessingUnavailableException;
use App\Services\Profile\AvatarProcessingCapability;
use Tests\TestCase;

class AvatarProcessingCapabilityTest extends TestCase
{
    public function test_it_confirms_the_current_runtime_supports_all_avatar_codecs(): void
    {
        $capability = new AvatarProcessingCapability;

        $this->assertTrue($capability->isAvailable());
        $this->assertSame([], $capability->missingFunctions());
        $capability->assertAvailable();
        $this->addToAssertionCount(1);
    }

    public function test_it_reports_a_stable_unavailable_error_when_a_codec_is_missing(): void
    {
        $capability = new class extends AvatarProcessingCapability
        {
            protected function functionExists(string $function): bool
            {
                return $function !== 'imagecreatefromwebp';
            }
        };

        $this->assertFalse($capability->isAvailable());
        $this->assertSame(['imagecreatefromwebp'], $capability->missingFunctions());

        try {
            $capability->assertAvailable();
            $this->fail('The missing codec must prevent avatar processing.');
        } catch (AvatarProcessingUnavailableException $exception) {
            $this->assertSame(AvatarProcessingUnavailableException::ERROR_CODE, $exception::ERROR_CODE);
            $this->assertSame(['imagecreatefromwebp'], $exception->missingFunctions);
        }
    }
}
