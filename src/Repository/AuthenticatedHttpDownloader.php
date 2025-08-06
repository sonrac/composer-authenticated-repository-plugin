<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository;

use Composer\Downloader\TransportException;
use Composer\IO\IOInterface;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class AuthenticatedHttpDownloader extends HttpDownloader
{
    private HttpDownloader $originalDownloader;
    private ?string $githubToken;
    private ?array $httpBasicAuth;
    private IOInterface $io;

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
        IOInterface $io,
    ) {
        $this->originalDownloader = $originalDownloader;
        $this->githubToken = $githubToken;
        $this->httpBasicAuth = $httpBasicAuth;
        $this->repositories = $repositories;
        $this->io = $io;
    }

    public function get($url, $options = []): Response
    {
        $options = $this->addAuthenticationHeaders($url, $options);

        if ($this->isNeedGetReleaseUrl($url) === false) {
            return $this->originalDownloader->get($url, $options);
        }

        try {
            return $this->originalDownloader->get($url, $options);
        } catch (TransportException $exception) {
            if (!str_contains($url, 'github.com')) {
                throw $exception;
            }

            // Attempt fallback via GitHub API
            $apiUrl = $this->getGitHubAssetApiUrl($url);

            if (!$apiUrl) {
                throw new \RuntimeException('Fallback GitHub asset URL not found from asset url ' . $url);
            }

            return $this->originalDownloader->get($apiUrl, $options);
        }
    }

    /**
     * @return PromiseInterface<Response>
     */
    public function add($url, $options = []): PromiseInterface
    {
        $options = $this->addAuthenticationHeaders($url, $options);

        return $this->originalDownloader->add($url, $options);
    }

    /**
     * @return PromiseInterface<Response>
     */
    public function addCopy(string $url, string $to, array $options = []): PromiseInterface
    {
        // Check if this is a GitHub release download URL that we should handle
        if (($this->isNeedGetReleaseUrl($url) && $this->isGitHubReleaseDownload($url))) {
            // For non-GitHub URLs, use the original downloader
            $options = $this->addAuthenticationHeaders($url, $options);

            if ($this->isNeedGetReleaseUrl($url)) {
                $options['http']['header'][] = 'Accept: application/octet-stream';

                $this->io->warning(
                    sprintf('Fallback Download %s from dist with authorization headers', $url),
                );
            }

            $this->io->warning(
                sprintf('Download %s from dist with plugin composer-authenticated-repository-plugin', $url),
            );

            // For GitHub releases, download directly and return a resolved promise
            return $this->downloadGitHubReleaseAsync($url, $to, $options);
        }

        $this->io->info(
            sprintf('Fallback Download %s', $url),
        );

        return $this->originalDownloader->addCopy($url, $to, $options);
    }

    public function getOptions(): mixed
    {
        return $this->originalDownloader->getOptions();
    }

    public function setOptions(array $options): void
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

    public function addAuthenticationHeaders(string $url, array $options, ?string $acceptType = null): array
    {
        $this->io->debug(sprintf('Adding auth headers for URL: %s', $url));

        if ($this->isNeedAuthHeaders($url) === false) {
            $this->io->debug('URL does not need auth headers');
            return $options;
        }

        $headers = $options['http']['header'] ?? [];

        // Add GitHub token if available and URL matches GitHub
        if ($this->githubToken && $this->isGitHubUrl($url)) {
            $headers[] = 'Authorization: token ' . $this->githubToken;
            $this->io->debug('Added GitHub token authorization header');
        } else {
            $this->io->debug(sprintf('GitHub token available: %s, is GitHub URL: %s', 
                $this->githubToken ? 'YES' : 'NO', 
                $this->isGitHubUrl($url) ? 'YES' : 'NO'));
        }

        if ($acceptType !== null) {
            $headers[] = 'Accept: ' . $acceptType;
            $this->io->debug(sprintf('Added Accept header: %s', $acceptType));
        }

        // Add HTTP basic auth if available
        if ($this->httpBasicAuth) {
            $username = $this->httpBasicAuth[0] ?? '';
            $password = $this->httpBasicAuth[1] ?? '';
            if ($username && $password) {
                $auth = base64_encode($username . ':' . $password);
                $headers[] = 'Authorization: Basic ' . $auth;
                $this->io->debug('Added HTTP Basic auth header');
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

        $this->io->debug(sprintf('Final headers: %s', json_encode($headers)));

        return $options;
    }

    public function isGitHubUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host === 'api.github.com' || $host === 'github.com' || str_ends_with($host, '.github.com');
    }

    public function getGitHubAssetApiUrl(string $browserUrl): ?string
    {
        $url = parse_url($browserUrl);
        $parts = explode(
            '/',
            str_replace(
                [
                    '/releases/download',
                    '/repos',
                ],
                '',
                $url['path'],
            ),
        );

        $parts = array_filter($parts, static function (string $value): bool {
            return strlen($value) > 0;
        });

        if (count($parts) < 4) {
            $this->io->debug(sprintf('Parts is invalid %s %s', json_encode($parts), $url['path']));

            return null;
        }

        $owner = $parts[1];
        $repo = $parts[2];
        $tag = $parts[3];
        $filename = $parts[4];

        $releaseApiUrl = "https://api.github.com/repos/{$owner}/{$repo}/releases/tags/{$tag}";

        $headers = [
            "Authorization: token {$this->githubToken}",
            "User-Agent: Composer",
        ];

        $context = stream_context_create([
            'http' => [
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
            ],
        ]);

        $json = file_get_contents($releaseApiUrl, false, $context);
        if (!$json) {
            $this->io->error('Invalid response from github');
            // Check for HTTP errors
            $httpResponseHeader = $http_response_header ?? [];
            $statusLine = $httpResponseHeader[0] ?? '';
            if (strpos($statusLine, '401') !== false || strpos($statusLine, '403') !== false) {
                throw new \RuntimeException(
                    "GitHub API authentication failed. Please check your GitHub token configuration.",
                );
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

    public function isNeedGetReleaseUrl(string $url, array $replacePatterns = ['/releases/download']): bool
    {
        if (count($this->repositories) === 0) {
            $this->io->info('Empty repositories list. Skip');

            return false;
        }

        $urlParts = parse_url($url);
        $this->io->debug(sprintf('URL parts: %s', json_encode($urlParts)));

        $parts = explode(
            '/',
            str_replace(
                $replacePatterns,
                '',
                $urlParts['path'],
            ),
        );

        $this->io->debug(sprintf('Parts after replacement: %s', json_encode($parts)));

        if (count($parts) < 5) {
            $this->io->info(sprintf('Invalid parts %s for url %s', json_encode($parts), $url));

            return false;
        }

        $this->io->debug(sprintf('Looking for owner: %s, name: %s for url %s', $parts[1], $parts[2], $url));

        foreach ($this->repositories as $repository) {
            $this->io->debug(sprintf('Checking against repository: %s/%s', $repository['owner'], $repository['name']));

            if (
                strtolower($repository['owner']) === strtolower($parts[1]) &&
                strtolower($repository['name']) === strtolower($parts[2])
            ) {
                $this->io->debug('Repository matched!');
                return true;
            }
        }

        $this->io->debug('Not matched with config repository ' . $url);
        return false;
    }

    public function isNeedAuthHeaders(string $url): bool
    {
        return $this->isNeedGetReleaseUrl(
            $url,
            [
                '/releases/download',
                '/repos',
            ],
        );
    }

    public function getAssetDownloadUrl(string $owner, string $repo, int $assetId): string
    {
        $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/releases/assets/{$assetId}";

        $headers = [
            "Authorization: token {$this->githubToken}",
            "User-Agent: Composer",
            "Accept: application/octet-stream",
        ];

        // Make a HEAD request to get the redirect URL
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'follow_location' => true,
                'max_redirects' => 5,
            ],
        ]);

        $headers = get_headers($apiUrl, true, $context);

        if ($headers && isset($headers['Location'])) {
            // Return the final redirect URL
            return is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
        }

        // Fallback to the original API URL if no redirect is found
        return $apiUrl;
    }

    /**
     * Check if the URL is a GitHub release download URL
     */
    public function isGitHubReleaseDownload(string $url): bool
    {
        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host']) || !isset($parsedUrl['path'])) {
            return false;
        }

        // Check if it's a GitHub URL
        if (!$this->isGitHubUrl($url)) {
            return false;
        }

        // Check if it's a release download URL pattern
        $path = $parsedUrl['path'];
        return strpos($path, '/releases/download/') !== false ||
            strpos($path, '/releases/assets/') !== false;
    }

    /**
     * Get the final download URL for a GitHub asset
     */
    public function getAssetDownloadUrlWithCurl(string $apiUrl): string
    {
        if (!$this->githubToken) {
            return $apiUrl;
        }

        $headers = [
            "Authorization: token {$this->githubToken}",
            "User-Agent: Composer",
            "Accept: application/octet-stream",
        ];

        // Use curl to get the redirect URL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_NOBODY => true, // HEAD request
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($httpCode === 200 && $effectiveUrl !== $apiUrl) {
            return $effectiveUrl;
        }

        return $apiUrl;
    }

    /**
     * Download GitHub release archive asynchronously using React PHP promises
     */
    public function downloadGitHubReleaseAsync(string $url, string $to, array $options = []): PromiseInterface
    {
        $this->io->debug(sprintf('Starting async GitHub release download: %s to %s', $url, $to));

        return new Promise(function (callable $resolve, callable $reject) use ($url, $to, $options) {
            try {
                // If it's a browser URL, convert to API URL first
                if (strpos($url, '/releases/download/') !== false) {
                    $apiUrl = $this->getGitHubAssetApiUrl($url);
                    if ($apiUrl) {
                        $url = $apiUrl;
                    }
                }

                // Get the final download URL with redirects
                $finalUrl = $this->getAssetDownloadUrlWithCurl($url);
                
                $this->io->debug(sprintf('Final download URL: %s', $finalUrl));

                // Create directory if it doesn't exist
                $dir = dirname($to);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // Prepare headers
                $headers = [];
                
                // Add GitHub token if available
                if ($this->githubToken && $this->isGitHubUrl($finalUrl)) {
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

                // Add User-Agent
                $headers[] = 'User-Agent: Composer';

                // Use curl multi handle for async download
                $mh = curl_multi_init();
                $ch = curl_init();
                
                curl_setopt_array($ch, [
                    CURLOPT_URL => $finalUrl,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_TIMEOUT => 300, // 5 minutes timeout
                    CURLOPT_NOSIGNAL => true, // Prevent blocking
                ]);

                curl_multi_add_handle($mh, $ch);

                // Async download loop
                $active = null;
                do {
                    $mrc = curl_multi_exec($mh, $active);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);

                while ($active && $mrc == CURLM_OK) {
                    if (curl_multi_select($mh) != -1) {
                        do {
                            $mrc = curl_multi_exec($mh, $active);
                        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
                    }
                }

                // Get the result
                $success = curl_multi_getcontent($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

                curl_multi_remove_handle($mh, $ch);
                curl_multi_close($mh);
                curl_close($ch);

                if (!$success || $httpCode !== 200) {
                    // Clean up the file if download failed
                    if (file_exists($to)) {
                        unlink($to);
                    }
                    
                    $errorMessage = "Failed to download file from {$finalUrl}. HTTP Code: {$httpCode}";
                    if ($error) {
                        $errorMessage .= ". Error: {$error}";
                    }
                    
                    $this->io->error($errorMessage);
                    $reject(new \RuntimeException($errorMessage));
                    return;
                }

                // Save the downloaded content to file
                if (strlen($success) !== 0) {
                    $this->io->debug(
                        sprintf('Downloaded %s content size is %d', $finalUrl, strlen($success)),
                    );

                    file_put_contents($to, $success);
                    
                    $this->io->debug(
                        sprintf('Downloaded %s archive saved to %s', $finalUrl, $to),
                    );
                }

                if (!is_file($to)) {
                    $errorMessage = "Failed to create file at {$to}";
                    $this->io->error($errorMessage);
                    $reject(new \RuntimeException($errorMessage));
                    return;
                }

                $this->io->debug(sprintf('File content length %d', strlen(file_get_contents($to))));
                
                // Create a response object
                $response = new Response(['url' => $effectiveUrl], 200, [], file_get_contents($to));

                $this->io->debug(sprintf('Async download completed successfully: %s', $finalUrl));
                
                // Resolve the promise
                $resolve($response);
                
            } catch (\Exception $e) {
                $this->io->error(sprintf('Async download failed for %s: %s', $url, $e->getMessage()));
                $reject($e);
            }
        });
    }
} 
