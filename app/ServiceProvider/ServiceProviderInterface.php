<?php

declare(strict_types=1);

namespace App\ServiceProvider;

interface ServiceProviderInterface
{
    function register(): void;
}
