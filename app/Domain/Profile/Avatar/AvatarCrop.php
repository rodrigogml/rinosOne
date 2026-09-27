<?php

namespace App\Domain\Profile\Avatar;

class AvatarCrop
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly float $size,
    ) {}
}
