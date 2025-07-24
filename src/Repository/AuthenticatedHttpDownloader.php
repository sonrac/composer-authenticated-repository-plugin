<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository;

use Composer\Util\HttpDownloader;
use Composer\Util\Url;

class AuthenticatedHttpDownloader extends HttpDownloader
{
    private HttpDownloader $originalDownloader;
    private ?string $githubToken;
    private ?array $httpBasicAuth;
    private array $authConfig;

    public function __construct(
        HttpDownloader $originalDownloader,
        ?string $githubToken,
        ?array $httpBasicAuth,
        array $authConfig
    ) {
        $this->originalDownloader = $originalDownloader;
        $this->githubToken = $githubToken;
        $this->httpBasicAuth = $httpBasicAuth;
        $this->authConfig = $authConfig;
    }

    public function get($url, $options = [])
    {
        $options = $this->addAuthenticationHeaders($url, $options);
        var_dump($options);

        return $this->originalDownloader->get($url, $options);
    }

    public function add($url, $options = []): void
    {
        $options = $this->addAuthenticationHeaders($url, $options);
        var_dump($options);

        $this->originalDownloader->add($url, $options);
    }

    public function copy($url, $to, $options = [])
    {
        $options = $this->addAuthenticationHeaders($url, $options);
        var_dump($options);

        return $this->originalDownloader->copy($url, $to, $options);
    }

    public function addAuthenticationHeaders(string $url, array $options): array
    {
        $headers = $options['http']['header'] ?? [];
        var_dump($this->githubToken);

        // Add GitHub token if available and URL matches GitHub
        if ($this->githubToken && $this->isGitHubUrl($url)) {
            $headers[] = 'Authorization: Bearer ' . $this->githubToken;
        }

        // Add HTTP basic auth if available
        if ($this->httpBasicAuth) {
            $username = $this->httpBasicAuth[0] ?? '';
            $password = $this->httpBasicAuth[1] ?? '';
            if ($username && $password) {
                $auth = base64_encode($username . ':' . $password);
                $headers[] = 'Authorization: Basic ' . $auth;
            }
        }

        $options['http']['header'] = $headers;
        return $options;
    }

    private function isGitHubUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        return $host === 'api.github.com' || $host === 'github.com' || str_ends_with($host, '.github.com');
    }
} 
