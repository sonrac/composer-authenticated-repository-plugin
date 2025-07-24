<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository;

use Composer\IO\IOInterface;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Composer\Util\Url;
use Composer\Downloader\TransportException;
use Error;
use Throwable;

class AuthenticatedHttpDownloader extends HttpDownloader
{
    private HttpDownloader $originalDownloader;
    private ?string $githubToken;
    private ?array $httpBasicAuth;

    /**
     * @var array<int, array{url: string, owner: string, name: string}> $repositories
     */
    private array $repositories;

    /**
     * @param array<int, array{url: string, owner: string, name: string}> $repositories
     */
    public function __construct(
        HttpDownloader $originalDownloader,
        ?string $githubToken,
        ?array $httpBasicAuth,
        array $repositories,
    ) {
        $this->originalDownloader = $originalDownloader;
        $this->githubToken = $githubToken;
        $this->httpBasicAuth = $httpBasicAuth;
        $this->repositories = $repositories;
    }

    public function get($url, $options = [])
    {
        $options = $this->addAuthenticationHeaders($url, $options);

        if (!$this->isLinkSupported($url)) {
            return $this->originalDownloader->get($url, $options);
        }

        try {
            return $this->originalDownloader->get($url, $options);
        } catch (TransportException $exception) {
            if (!$exception instanceof TransportException || strpos($url, 'github.com') === false) {
                throw $exception;
            }

            // Attempt fallback via GitHub API
            $apiUrl = $this->getGitHubAssetApiUrl($url);

            if (!$apiUrl) {
                throw new \RuntimeException('Fallback GitHub asset URL not found.');
            }

            return $this->originalDownloader->get($apiUrl, $options);
        }
    }

    public function add($url, $options = []): void
    {
        $options = $this->addAuthenticationHeaders($url, $options);

        $this->originalDownloader->add($url, $options);
    }

    public function addCopy(string $url, string $to, array $options = [])
    {
        return $this->originalDownloader->addCopy($url, $to, $options);
    }

    public function getOptions()
    {
        return $this->originalDownloader->getOptions();
    }

    public function setOptions(array $options)
    {
        $this->originalDownloader->setOptions($options);
    }

    public function enableAsync(): void
    {
        $this->originalDownloader->enableAsync();
    }

    public function countActiveJobs(?int $index = null): int
    {
        return $this->originalDownloader->countActiveJobs($index);
    }

    public function copy($url, $to, $options = []): Response
    {
        $options = $this->addAuthenticationHeaders($url, $options);

        return $this->originalDownloader->copy($url, $to, $options);
    }

    public function wait($index = null): void
    {
        $this->originalDownloader->wait($index);
    }

    public function addAuthenticationHeaders(string $url, array $options): array
    {
        if ($this->isLinkSupported($url) === false) {
            return $options;
        }

        $headers = $options['http']['header'] ?? [];

        // Add GitHub token if available and URL matches GitHub
        if ($this->githubToken && $this->isGitHubUrl($url)) {
            $headers[] = 'Authorization: token ' . $this->githubToken;
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

        // Enable redirect following for all requests
        if (!isset($options['http']['follow_location'])) {
            $options['http']['follow_location'] = true;
        }
        if (!isset($options['http']['max_redirects'])) {
            $options['http']['max_redirects'] = 5;
        }

        return $options;
    }

    private function isGitHubUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host === 'api.github.com' || $host === 'github.com' || str_ends_with($host, '.github.com');
    }

    private function getGitHubAssetApiUrl(string $browserUrl): ?string
    {
        $url = parse_url($browserUrl);
        $parts = explode('/', str_replace('/releases/download', '', $url['path']));

        if (count($parts) !== 5) {
            return null;
        }

        $owner = $parts[1];
        $repo = $parts[2];
        $tag = $parts[3];
        $filename = $parts[4];

        $releaseApiUrl = "https://api.github.com/repos/{$owner}/{$repo}/releases/tags/{$tag}";

        $headers = [
            "Authorization: token {$this->githubToken}",
            "User-Agent: Composer"
        ];

        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ]
        ]);

        $json = file_get_contents($releaseApiUrl, false, $context);
        if (!$json) {
            // Check for HTTP errors
            $httpResponseHeader = $http_response_header ?? [];
            $statusLine = $httpResponseHeader[0] ?? '';
            if (strpos($statusLine, '401') !== false || strpos($statusLine, '403') !== false) {
                throw new \RuntimeException("GitHub API authentication failed. Please check your GitHub token configuration.");
            }
            return null;
        }

        $data = json_decode($json, true);
        if (!isset($data['assets'])) {
            return null;
        }

        foreach ($data['assets'] as $asset) {
            if ($asset['name'] === $filename) {
                // Get the direct download URL by following redirects
                return $this->getAssetDownloadUrl($owner, $repo, $asset['id']);
            }
        }

        return null;
    }

    private function isLinkSupported(string $url): bool
    {
        $url = parse_url($url);
        $parts = explode('/', str_replace('/releases/download', '', $url['path']));

        if (count($parts) !== 5) {
            return false;
        }

        foreach ($this->repositories as $repository) {
            if (
                strtolower($repository['owner']) === strtolower($parts[1]) &&
                strtolower($repository['name']) === strtolower($parts[2])
            ) {
                return true;
            }
        }

        return false;
    }

    private function getAssetDownloadUrl(string $owner, string $repo, int $assetId): string
    {
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/releases/assets/{$assetId}";

        $headers = [
            "Authorization: token {$this->githubToken}",
            "User-Agent: Composer",
            "Accept: application/octet-stream"
        ];

        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'follow_location' => true, // Enable redirect following
                'max_redirects' => 5
            ]
        ]);

        // Make a HEAD request to get the redirect URL
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'follow_location' => true,
                'max_redirects' => 5
            ]
        ]);

        $headers = get_headers($apiUrl, true, $context);

        if ($headers && isset($headers['Location'])) {
            // Return the final redirect URL
            $location = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
            return $location;
        }

        // Fallback to the original API URL if no redirect is found
        return $apiUrl;
    }
} 
