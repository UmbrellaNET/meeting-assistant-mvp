<?php
namespace App\Services\MeetingProviders;
use InvalidArgumentException;
class MeetingProviderRegistry { public function __construct(private readonly array $providers){} public function for(string $provider): MeetingProvider { $instance=$this->providers[$provider]??null; if(!$instance instanceof MeetingProvider){throw new InvalidArgumentException("Unsupported provider: {$provider}");} return $instance; } }
