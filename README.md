# Composer Authenticated Repository Plugin

A Composer plugin that provides automatic authentication support for GitHub repositories and HTTP basic auth when downloading packages. The plugin intercepts file downloads and adds authentication headers as needed.

## Features

- **Pre-Download Hook**: Intercepts file downloads using Composer's `PreFileDownloadEvent`
- **GitHub Token Support**: Automatic GitHub OAuth token injection for GitHub URLs
- **HTTP Basic Auth**: Support for HTTP basic authentication
- **Repository Configuration**: Configurable repository matching for authentication
- **Debug Logging**: Comprehensive debug output for troubleshooting
- **Composer Config Integration**: Uses existing Composer authentication configuration
- **Transparent Operation**: Works with existing Composer workflows

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

### 2. Configure Repository Matching

Add configuration to composer `extra` section to specify which repositories should receive authentication:

```json
{
  "extra": {
    "composer-authenticated-plugin": {
      "repositories": [
        {
          "owner": "MacPaw",
          "name": "platform-shared-clients",
          "url": "https://github.com/MacPaw/platform-shared-clients"
        },
        {
          "owner": "my-org",
          "name": "private-packages",
          "url": "https://github.com/my-org/private-packages"
        }
      ]
    }
  }
}
```

**Important**: The `owner` and `name` fields are required and must match the GitHub repository owner and name exactly.

## How It Works

### 1. Plugin Activation

The plugin activates during Composer initialization and:
- Reads authentication credentials from Composer configuration
- Creates an authenticated HTTP downloader
- Registers custom repository types
- Subscribes to the `PreFileDownloadEvent`

### 2. Download Interception

When Composer attempts to download a file, the plugin:

```php
public function onDownload(PreFileDownloadEvent $preFileDownloadEvent): void
{
    $processedUrl = $preFileDownloadEvent->getProcessedUrl();
    
    // Check if URL needs authentication
    if ($this->httpDownloader->isNeedAuthHeaders($processedUrl)) {
        // Add authentication headers
        $transportOptions = $this->httpDownloader->addAuthenticationHeaders($processedUrl, $options);
        $preFileDownloadEvent->setTransportOptions($transportOptions);
    }
}
```

### 3. Repository Matching

The plugin matches URLs against configured repositories:

```php
public function isNeedAuthHeaders(string $url): bool
{
    // Parse URL to extract owner/repo from GitHub URLs
    // Match against configured repositories
    // Return true if authentication is needed
}
```

### 4. Authentication Header Injection

For matching URLs, the plugin adds appropriate headers:

```php
public function addAuthenticationHeaders(string $url, array $options): array
{
    $headers = $options['http']['header'] ?? [];

    // Add GitHub token for GitHub URLs
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

## Environment Variables

For security, use environment variables:

```bash
# Set environment variables
export GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
export ARTIFACT_USERNAME=your-username
export ARTIFACT_PASSWORD=your-password

# Install packages
composer update
```

Or use Composer's environment variable substitution:

```json
{
    "config": {
        "github-oauth": {
            "api.github.com": "%env(GITHUB_TOKEN)%"
        },
        "http-basic": {
            "artifacts.company.com": {
                "username": "%env(ARTIFACT_USERNAME)%",
                "password": "%env(ARTIFACT_PASSWORD)%"
            }
        }
    }
}
```

## Debug Mode

Enable debug logging to see what the plugin is doing:

```bash
composer install -vvv
```

The plugin will output debug information including:
- URLs being processed
- Whether authentication headers are needed
- Repository matching results
- Authentication header details

## Security Considerations

- **Token Security**: Never commit authentication tokens to version control
- **Environment Variables**: Use environment variables for sensitive configuration
- **Token Scopes**: Use minimal required scopes for GitHub tokens
- **HTTPS Only**: All requests use HTTPS for security
- **Repository Matching**: Only URLs matching configured repositories receive authentication

## Troubleshooting

### Common Issues

1. **401 Authentication Errors**: 
   - Verify your tokens/credentials are correctly configured
   - Check that the repository is configured in the plugin's `extra` section
   - Ensure the `owner` and `name` match the GitHub repository exactly

2. **Repository Not Found**: 
   - Ensure the repository URL is accessible with your credentials
   - Verify the repository is listed in the plugin configuration

3. **Package Not Found**: 
   - Check that the package is listed in the repository manifest
   - Verify download URLs are accessible with authentication

4. **Permission Denied**: 
   - Verify your GitHub token has the required scopes
   - Check repository visibility and access permissions

### Debug Steps

1. **Check authentication configuration**:
   ```bash
   composer config github-oauth
   composer config http-basic
   ```

2. **Enable verbose output**:
   ```bash
   composer install -vvv
   ```

3. **Test repository access**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/owner/repo
   ```

4. **Verify plugin configuration**:
   Check that your `composer.json` has the correct `extra.composer-authenticated-plugin.repositories` configuration.

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
│   ├── Plugin.php                          # Main plugin class with PreFileDownloadEvent
│   └── Repository/
│       ├── AuthenticatedComposerRepository.php # Repository wrapper
│       └── AuthenticatedHttpDownloader.php     # HTTP downloader with auth
├── tests/
│   └── AuthenticatedRepositoryTest.php     # Unit tests
├── example/
│   └── composer.json                       # Example configuration
```

## License

MIT License - see LICENSE file for details.

## Support

For issues and questions, please create an issue in the GitHub repository.

## Current State and Recent Updates

### Implementation Overview

The plugin currently implements authentication support through:

1. **PreFileDownloadEvent Hook**: Intercepts all file downloads and adds authentication headers as needed
2. **Repository Matching**: Only adds authentication for URLs matching configured repositories
3. **Debug Logging**: Comprehensive debug output for troubleshooting
4. **GitHub Token Support**: Automatic token injection for GitHub URLs
5. **HTTP Basic Auth**: Support for HTTP basic authentication

### Key Features

- **Security**: Repository-specific authentication prevents token exposure
- **Compatibility**: Works with existing Composer workflows
- **Debugging**: Extensive debug logging for troubleshooting
- **Flexibility**: Supports both GitHub tokens and HTTP basic auth

### Recent Changes

- **Repository Configuration**: Now requires explicit repository configuration in `extra.composer-authenticated-plugin.repositories`
- **Debug Logging**: Added comprehensive debug output for troubleshooting
- **URL Matching**: Improved repository matching logic with case-insensitive comparison
- **Documentation**: Updated all README files to reflect current implementation

### Known Limitations

- Only supports GitHub repositories and HTTP basic auth
- Requires explicit repository configuration
- Debug logging only available with `-vvv` flag

### Future Enhancements

- Support for additional authentication methods (OAuth 2.0, API keys)
- Caching and rate limiting support
- Enhanced error handling and retry logic
- Performance monitoring and metrics 
