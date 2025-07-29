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

    private ?string $owner;
    private ?string $repoName;
    private IOInterface $io;

    public function __construct(
        array $repoConfig,
        IOInterface $io,
        Config $config,
        HttpDownloader $httpDownloader,
        ?string $owner,
        ?string $repoName,
    ) {
        $this->authConfig = $repoConfig;
        $this->owner = $owner;
        $this->repoName = $repoName;
        $this->io = $io;
        
        // Create a custom HTTP downloader with authentication
        $authenticatedDownloader = $httpDownloader;

        // Convert to standard composer repository config
        $composerRepoConfig = [
            'type' => 'composer',
            'url' => $repoConfig['url'],
        ];
        
        parent::__construct($composerRepoConfig, $io, $config, $authenticatedDownloader);
    }
}
