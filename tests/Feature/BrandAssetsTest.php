<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandAssetsTest extends TestCase
{
    public function test_public_brand_assets_and_web_manifest_are_available(): void
    {
        $publicPath = public_path();

        $this->assertFileExists($publicPath.'/assets/brand/logo-768.png');
        $this->assertFileExists($publicPath.'/assets/brand/icon-32.png');
        $this->assertFileExists($publicPath.'/assets/brand/icon-180.png');
        $this->assertFileExists($publicPath.'/assets/brand/icon-192.png');
        $this->assertFileExists($publicPath.'/assets/brand/icon-512.png');
        $this->assertFileExists($publicPath.'/favicon.ico');

        $logoDimensions = getimagesize($publicPath.'/assets/brand/logo-768.png');
        $icon192Dimensions = getimagesize($publicPath.'/assets/brand/icon-192.png');
        $icon512Dimensions = getimagesize($publicPath.'/assets/brand/icon-512.png');

        $this->assertSame(768, $logoDimensions[0]);
        $this->assertSame(380, $logoDimensions[1]);
        $this->assertSame(192, $icon192Dimensions[0]);
        $this->assertSame(192, $icon192Dimensions[1]);
        $this->assertSame(512, $icon512Dimensions[0]);
        $this->assertSame(512, $icon512Dimensions[1]);

        $manifest = json_decode(file_get_contents($publicPath.'/manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Rinos One', $manifest['name']);
        $this->assertSame('/assets/brand/icon-192.png', $manifest['icons'][0]['src']);
        $this->assertSame('/assets/brand/icon-512.png', $manifest['icons'][1]['src']);
    }
}
