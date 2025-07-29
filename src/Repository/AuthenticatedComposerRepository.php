<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Repository\ComposerRepository;
use Composer\Util\HttpDownloader;

class AuthenticatedComposerRepository extends ComposerRepository
{
    private array $authConfig;

    private string $owner;
    private string $repoName;
    private IOInterface $io;

    public function __construct(
        array $repoConfig,
        IOInterface $io,
        Config $config,
        HttpDownloader $httpDownloader = null,
        string $owner,
        string $repoName,
    ) {
        $this->authConfig = $repoConfig;
        $this->owner = $owner;
        $this->repoName = $repoName;
        $this->io = $io;
        
        // Create a custom HTTP downloader with authentication
        $authenticatedDownloader = $this->createAuthenticatedDownloader($httpDownloader, $config);

        // Convert to standard composer repository config
        $composerRepoConfig = [
            'type' => 'composer',
            'url' => $repoConfig['url'],
        ];
        
        parent::__construct($composerRepoConfig, $io, $config, $authenticatedDownloader);
    }

    private function createAuthenticatedDownloader(HttpDownloader $httpDownloader, Config $config): HttpDownloader
    {
        // Get authentication credentials from Composer config
        $githubToken = $this->getGitHubToken($config);
        $httpBasicAuth = $this->getHttpBasicAuth($config);

        if ($githubToken === null && $httpBasicAuth === null) {
            $this->io->error('Authorization is empty');
        }

        // Create a custom HTTP downloader that adds authentication headers
        return new AuthenticatedHttpDownloader(
            $httpDownloader,
            $githubToken,
            $httpBasicAuth,
            [
                [
                    'owner' => $this->owner,
                    'name' => $this->repoName,
                ]
            ],
            $this->io,
        );
    }

    private function getGitHubToken(Config $config): ?string
    {
        // Check if GitHub token is configured for this repository URL
        $url = $this->authConfig['url'] ?? '';
        
        // Try to get GitHub token from Composer config
        $githubTokens = $config->get('github-oauth') ?? [];
        
        // Extract host from URL
        $host = parse_url($url, PHP_URL_HOST);
        
        // Return token for this host if available
        return $githubTokens[$host] ?? null;
    }

    private function getHttpBasicAuth(Config $config): ?array
    {
        // Check if HTTP basic auth is configured for this repository URL
        $url = $this->authConfig['url'] ?? '';
        
        // Try to get HTTP basic auth from Composer config
        $httpBasicAuth = $config->get('http-basic') ?? [];
        
        // Extract host from URL
        $host = parse_url($url, PHP_URL_HOST);
        
        // Return credentials for this host if available
        return $httpBasicAuth[$host] ?? null;
    }
} 
