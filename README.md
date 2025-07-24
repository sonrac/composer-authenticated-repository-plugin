# Composer Authenticated Repository Plugin

A Composer plugin that adds support for a new repository type `composer-authenticated` which extends the standard Composer repository behavior with automatic authentication support for GitHub tokens and HTTP basic auth.

## Features

- **Custom Repository Type**: New `composer-authenticated` repository type
- **GitHub Token Support**: Automatic GitHub OAuth token injection
- **HTTP Basic Auth**: Support for HTTP basic authentication
- **Composer Config Integration**: Uses existing Composer authentication configuration
- **Transparent Operation**: Works exactly like standard Composer repositories but with auth

## Installation

### Option 1: Install as a Composer Plugin

```bash
composer require sonrac/composer-authenticated-repository-plugin
```

### Option 2: Manual Installation

1. Clone this repository to your project
2. Add the plugin to your `composer.json`:

```json
{
    "require": {
        "sonrac/composer-authenticated-repository-plugin": "*"
    },
    "config": {
        "allow-plugins": {
            "sonrac/composer-authenticated-repository-plugin": true
        }
    }
}
```

## Configuration

### 1. Configure Authentication

First, configure your authentication credentials in Composer:

#### GitHub Token Authentication

```bash
# Configure GitHub token for specific host
composer config github-oauth.api.github.com YOUR_GITHUB_TOKEN

# Or for a custom GitHub Enterprise host
composer config github-oauth.github.yourcompany.com YOUR_GITHUB_TOKEN
```

#### HTTP Basic Authentication

```bash
# Configure HTTP basic auth for specific host
composer config http-basic.your-repo.com username password

# Or for a custom repository host
composer config http-basic.artifacts.company.com username password
```

### 2. Use the Authenticated Repository

Add configuration to composer `extra` section

```json
{
  "extra": {
    "composer-authenticated-plugin": {
      "repositories": [
        {
          "owner": "my-org",
          "name": "private-packages",
          "url": "https://github.com/my-org/private-packages/releases/composer-repository/composer-repository.json"
        },
        {
          "owner": "my-org",
          "name": "private-packages-new",
          "url": "https://artifacts.mycompany.com/composer-repository.json"
        }
      ]
    }
  }
}
```

## How It Works

### 1. Plugin Registration

The plugin registers a new repository type `composer-authenticated` using Composer's activation composer plugin mechanism for initialization

### 2. Authentication Injection

The `AuthenticatedComposerRepository` wraps the standard Composer repository and injects authentication:

```php
private function createAuthenticatedDownloader(HttpDownloader $httpDownloader, Config $config): HttpDownloader
{
    $githubToken = $this->getGitHubToken($config);
    $httpBasicAuth = $this->getHttpBasicAuth($config);
    
    return new AuthenticatedHttpDownloader(
        $httpDownloader,
        $githubToken,
        $httpBasicAuth,
        $this->authConfig
    );
}
```

### 3. HTTP Header Injection

The `AuthenticatedHttpDownloader` adds authentication headers to all requests:

```php
public function addAuthenticationHeaders(string $url, array $options): array
{
    $headers = $options['http']['header'] ?? [];

    // Add GitHub token
    if ($this->githubToken && $this->isGitHubUrl($url)) {
        $headers[] = 'Authorization: token ' . $this->githubToken;
    }

    // Add HTTP basic auth
    if ($this->httpBasicAuth) {
        $auth = base64_encode($username . ':' . $password);
        $headers[] = 'Authorization: Basic ' . $auth;
    }

    $options['http']['header'] = $headers;
    return $options;
}
```

### Environment Variables

For security, use environment variables:

```bash
# Set environment variables
export GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
export ARTIFACT_USERNAME=your-username
export ARTIFACT_PASSWORD=your-password

# Install packages
composer update
```

## Repository Manifest Format

The authenticated repository expects the same format as standard Composer repositories:

```json
{
    "packages": {
        "my-org/private-package": {
            "1.0.0": {
                "name": "my-org/private-package",
                "version": "1.0.0",
                "description": "Private package",
                "type": "library",
                "dist": {
                    "type": "zip",
                    "url": "https://api.github.com/repos/my-org/private-package/zipball/1.0.0"
                },
                "require": {
                    "php": ">=8.0"
                }
            }
        }
    }
}
```

## Security Considerations

- **Token Security**: Never commit authentication tokens to version control
- **Environment Variables**: Use environment variables for sensitive configuration
- **Token Scopes**: Use minimal required scopes for GitHub tokens
- **HTTPS Only**: All requests use HTTPS for security

## Troubleshooting

### Common Issues

1. **Authentication Errors**: Verify your tokens/credentials are correctly configured
2. **Repository Not Found**: Ensure the repository URL is accessible with your credentials
3. **Package Not Found**: Check that the package is listed in the repository manifest
4. **Permission Denied**: Verify your GitHub token has the required scopes

### Debug Mode

Enable Composer debug mode to see detailed information:

```bash
composer install --verbose
```

### Check Authentication

Verify your authentication configuration:

```bash
# Check GitHub token configuration
composer config github-oauth

# Check HTTP basic auth configuration
composer config http-basic
```

## Development

### Building the Plugin

```bash
cd composer-authenticated-repository-plugin
composer install
```

### Running Tests

```bash
composer test
```

### Plugin Structure

```
composer-authenticated-repository-plugin/
├── composer.json                           # Plugin package definition
├── src/
│   ├── Plugin.php                          # Main plugin class
│   └── Repository/
│       ├── AuthenticatedComposerRepository.php # Authenticated repository
│       └── AuthenticatedHttpDownloader.php     # HTTP downloader with auth
├── tests/
│   └── AuthenticatedRepositoryTest.php     # Unit tests
```

## License

MIT License - see LICENSE file for details.

## Support

For issues and questions, please create an issue in the GitHub repository. 
