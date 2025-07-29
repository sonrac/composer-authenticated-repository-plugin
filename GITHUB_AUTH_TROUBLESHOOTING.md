# GitHub Authentication Troubleshooting Guide

## Problem
You're getting the following error when trying to download GitHub release assets:

```
Could not fetch https://api.github.com/repos/MacPaw/platform-shared-clients/releases/assets/275933687, please review your configured GitHub OAuth token or enter a new one to access private repos
```

## Root Cause
The issue occurs when Composer tries to access GitHub release assets but doesn't have proper authentication configured. This can happen with:

1. **Private repositories** - Require authentication even for public assets
2. **Rate limiting** - GitHub API has rate limits for unauthenticated requests
3. **Asset access permissions** - Some assets require specific permissions

## Solutions

### 1. Configure GitHub Token

#### Option A: Environment Variable (Recommended)
```bash
export GITHUB_TOKEN=your_github_token_here
```

#### Option B: Composer Configuration File
Create or edit `~/.composer/auth.json`:
```json
{
    "github-oauth": {
        "api.github.com": "your_github_token_here"
    }
}
```

#### Option C: Project-specific Configuration
Add to your `composer.json`:
```json
{
    "config": {
        "github-oauth": {
            "api.github.com": "your_github_token_here"
        }
    }
}
```

### 2. Create GitHub Token

#### For Public Repositories Only:
1. Go to https://github.com/settings/tokens/new?scopes=&description=Composer+Authentication
2. Give it a descriptive name (e.g., "Composer Authentication")
3. No scopes needed for public repos
4. Click "Generate token"
5. Copy the token and use it in your configuration

#### For Private Repositories:
1. Go to https://github.com/settings/tokens/new?scopes=repo&description=Composer+Authentication
2. Give it a descriptive name (e.g., "Composer Authentication")
3. Select the `repo` scope (gives read/write access to private repos)
4. Click "Generate token"
5. Copy the token and use it in your configuration

### 3. Test Your Configuration

Run the test script to verify your GitHub token works:

```bash
php test-github-auth.php
```

This will:
- Check if your token is properly configured
- Test access to the specific repository
- Test access to the specific asset
- Provide detailed error messages if authentication fails

### 4. Plugin-specific Configuration

If you're using this plugin, ensure your `composer.json` includes:

```json
{
    "extra": {
        "composer-authenticated-plugin": {
            "repositories": [
                {
                    "url": "https://api.github.com/repos/MacPaw/platform-shared-clients/contents/composer-repository.json"
                }
            ]
        }
    },
    "config": {
        "github-oauth": {
            "api.github.com": "your_github_token_here"
        }
    }
}
```

## Common Issues and Solutions

### Issue: "Not Found" Error
**Cause**: Repository doesn't exist or is private
**Solution**: Ensure you have access to the repository and your token has the correct permissions

### Issue: "Bad Credentials" Error
**Cause**: Invalid or expired token
**Solution**: Generate a new GitHub token and update your configuration

### Issue: "Rate Limit Exceeded" Error
**Cause**: Too many API requests
**Solution**: Use authentication (even for public repos) to get higher rate limits

### Issue: "Forbidden" Error
**Cause**: Token lacks required permissions
**Solution**: Ensure your token has the `repo` scope for private repositories

### Issue: Asset Download Fails with Redirect
**Cause**: GitHub release assets return redirect URLs that need to be followed
**Solution**: The plugin now automatically handles redirects. If you're still having issues:
1. Check that your token has the correct permissions
2. Verify the asset exists and is accessible
3. Run the redirect test script: `php test-redirect-handling.php`

### Issue: ZIP File Downloads Fail
**Cause**: ZIP downloads don't include authentication headers
**Solution**: The plugin now uses curl to download GitHub release archives directly:
1. Ensure your GitHub token is properly configured
2. The plugin automatically detects GitHub release download URLs
3. Uses curl with authentication headers and redirect support
4. Bypasses Composer's download manager to avoid async issues
5. Test with: `php test-curl-download.php`

### Issue: Async HTTP Request Error
**Cause**: "You must use the HttpDownloader instance which is part of a Composer\Loop instance"
**Solution**: This error occurs when the plugin interferes with Composer's async operations. The plugin has been updated to avoid this issue by:
1. Only handling repository-level authentication
2. Not interfering with the download manager's async operations
3. Using Composer's built-in authentication for package downloads

### Issue: Unzip Cannot Find Archive File
**Cause**: "unzip: cannot find or open /path/to/temp.zip"
**Solution**: This error occurs when the download process doesn't create the file properly. The plugin now:
1. Uses curl to download files directly with authentication
2. Ensures the file is created before returning the promise
3. Handles GitHub release downloads asynchronously
4. Creates proper mock response objects
5. Test with: `php test-async-download.php`

## Security Best Practices

1. **Use environment variables** instead of hardcoding tokens in files
2. **Use minimal scopes** - only grant the permissions you need
3. **Rotate tokens regularly** - GitHub tokens don't expire but should be rotated
4. **Use different tokens** for different environments (dev, staging, production)

## Debugging Steps

1. **Check token validity**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/user
   ```

2. **Check repository access**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/MacPaw/platform-shared-clients
   ```

3. **Check asset access**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/repos/MacPaw/platform-shared-clients/releases/assets/275933687
   ```

4. **Run the test script**:
   ```bash
   php test-github-auth.php
   ```

5. **Test redirect handling**:
   ```bash
   php test-redirect-handling.php
   ```

6. **Test ZIP download authentication**:
   ```bash
   php test-zip-download.php
   ```

7. **Test basic authentication**:
   ```bash
   php test-basic-auth.php
   ```

8. **Test curl-based download functionality**:
   ```bash
   php test-curl-download.php
   ```

9. **Test async download functionality**:
   ```bash
   php test-async-download.php
   ```

## Plugin Updates

The plugin has been updated to:
- Use correct GitHub API authentication format (`token` instead of `Bearer`)
- Provide better error messages for authentication failures
- Handle GitHub asset downloads more robustly
- **Automatically follow redirects** when downloading GitHub release assets
- Use HEAD requests to resolve redirect URLs when needed
- Support up to 5 redirect levels for complex asset URLs
- **Handle repository-level authentication** without interfering with Composer's async operations
- Work with Composer's built-in authentication for package downloads
- **Use curl for direct GitHub release archive downloads** with authentication and redirect support
- Bypass Composer's download manager for GitHub assets to avoid async issues
- **Handle async downloads properly** by creating files before returning promises
- Ensure downloaded files exist for Composer's unzip process

## Still Having Issues?

If you're still experiencing problems:

1. Check the GitHub repository permissions and visibility
2. Verify your token has the correct scopes
3. Test with the provided test script
4. Check Composer's debug output: `composer install -vvv`
5. Review the plugin's error handling in the logs 