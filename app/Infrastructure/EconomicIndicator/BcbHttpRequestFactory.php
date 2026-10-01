<?php

namespace App\Infrastructure\EconomicIndicator;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;

/**
 * Builds BCB HTTP requests with the platform's timeout, retry and certificate-validation policy.
 */
final class BcbHttpRequestFactory
{
    public function create(HttpFactory $http): PendingRequest
    {
        $request = $http->acceptJson()->timeout(config('economic-indicators.bcb.timeout_seconds'))->retry(2, 250);
        $options = $this->options();

        return $options === [] ? $request : $request->withOptions($options);
    }

    /** @return array<string, mixed> */
    public function options(): array
    {
        $bundle = config('economic-indicators.bcb.ca_bundle');
        $options = config('economic-indicators.bcb.force_ipv4') ? ['force_ip_resolve' => 'v4'] : [];

        if (is_string($bundle) && $bundle !== '') {
            $options['verify'] = $bundle;
        } elseif (config('economic-indicators.bcb.use_native_ca', PHP_OS_FAMILY === 'Windows')
            && defined('CURLOPT_SSL_OPTIONS')
            && defined('CURLSSLOPT_NATIVE_CA')) {
            $options['curl'] = [constant('CURLOPT_SSL_OPTIONS') => constant('CURLSSLOPT_NATIVE_CA')];
        }

        return $options;
    }
}
