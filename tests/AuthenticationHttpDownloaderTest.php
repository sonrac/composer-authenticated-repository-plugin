<?php

declare(strict_types=1);

namespace Sonrac\ComposerAuthenticatedRepositoryPlugin\Tests;

use Composer\IO\IOInterface;
use Composer\Util\HttpDownloader;
use PHPUnit\Framework\TestCase;
use Sonrac\ComposerAuthenticatedRepositoryPlugin\Repository\AuthenticatedHttpDownloader;

final class AuthenticationHttpDownloaderTest extends TestCase
{
    public function testIsLinkSupported(): void
    {
        $downloader = new AuthenticatedHttpDownloader(
            $this->createMock(HttpDownloader::class),
            'github token',
            [],
            [
                [
                    'owner' => 'some-org',
                    'name' => 'some-repo',
                    'https://github.com/some-org/some-repo/releases/download/composer-repository/composer-repository.json'
                ]
            ],
            $this->createMock(IOInterface::class),
        );

        $this->assertFalse(
            $downloader->isLinkSupported(
                'https://api.github.com/repos/some-org/some-repo/releases/assets/275933430'
            ),
        );

        $this->assertTrue(
            $downloader->isLinkSupported(
                'https://github.com/some-org/some-repo/releases/download/composer-repository/composer-repository.json'
            ),
        );

        $this->assertTrue(
            $downloader->isNeedAuthHeaders(
                'https://api.github.com/repos/some-org/some-repo/releases/assets/275933430'
            ),
        );

        $this->assertTrue(
            $downloader->isNeedAuthHeaders(
                'https://github.com/some-org/some-repo/releases/download/composer-repository/composer-repository.json'
            ),
        );
    }

    public function testWithEmptyConfig(): void
    {
        $downloader = new AuthenticatedHttpDownloader(
            $this->createMock(HttpDownloader::class),
            'github token',
            [],
            [
            ],
            $this->createMock(IOInterface::class),
        );

        $this->assertFalse(
            $downloader->isLinkSupported(
                'https://api.github.com/repos/some-org/some-repo/releases/assets/123'
            ),
        );

        $this->assertFalse(
            $downloader->isLinkSupported(
                'https://github.com/some-org/some-repo/releases/download/'.
                'composer-repository/composer-repository.json'
            ),
        );

        $this->assertFalse(
            $downloader->isNeedAuthHeaders(
                'https://api.github.com/repos/some-org/some-repo/releases/assets/123'
            ),
        );

        $this->assertFalse(
            $downloader->isNeedAuthHeaders(
                'https://github.com/some-org/some-repo/releases/download/'.
                'composer-repository/composer-repository.json'
            ),
        );
    }
}
