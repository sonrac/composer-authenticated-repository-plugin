# Troubleshooting Guide

## Error: Repository Type Not Recognized

If you see an error like:
```
"./composer.json" does not match the expected JSON schema:
- repositories[1].type : Does not have a value in the enumeration ["composer"]
```

This means the plugin isn't properly registered. Here's how to fix it:

## Solution Steps

### 1. Verify Plugin Installation

First, make sure the plugin is properly installed:

```bash
# Check if plugin is installed
composer show sonrac/composer-authenticated-repository-plugin

# If not installed, install it
composer require sonrac/composer-authenticated-repository-plugin
```

### 2. Check Plugin Configuration

Ensure the plugin is allowed in your `composer.json`:

```json
{
    "config": {
        "allow-plugins": {
            "sonrac/composer-authenticated-repository-plugin": true
        }
    }
}
```

### 3. Clear Composer Cache

Clear Composer's cache to ensure the plugin is loaded:

```bash
composer clear-cache
```

### 4. Verify Plugin Loading

Check if the plugin is being loaded by running:

```bash
composer install --verbose
```

You should see output indicating the plugin is being activated.

### 5. Test Repository Configuration

Try a minimal test configuration:

```json
{
    "repositories": [
        {
            "type": "composer-authenticated",
            "url": "https://example.com/composer-repository.json"
        }
    ]
}
```

### 6. Alternative: Manual Installation

If the plugin isn't working, you can manually install it:

1. **Clone the plugin to your project:**
```bash
git clone <plugin-repo> vendor/sonrac/composer-authenticated-repository-plugin
```

2. **Add to composer.json:**
```json
{
    "repositories": [
        {
            "type": "path",
            "url": "vendor/sonrac/composer-authenticated-repository-plugin"
        }
    ],
    "require": {
        "sonrac/composer-authenticated-repository-plugin": "*"
    }
}
```

3. **Install:**
```bash
composer update
```

## Debug Steps

### Enable Debug Mode

Run Composer with debug output:

```bash
composer install --verbose --debug
```

### Check Plugin Files

Verify the plugin files exist:

```bash
ls -la vendor/sonrac/composer-authenticated-repository-plugin/src/
```

### Test Plugin Activation

Create a simple test script:

```php
<?php
require_once 'vendor/autoload.php';

use Sonrac\ComposerAuthenticatedRepositoryPlugin\Plugin;

$plugin = new Plugin();
echo "Plugin class loaded successfully\n";
```

## Common Issues

### 1. Plugin Not Found

**Error:** `Could not find package sonrac/composer-authenticated-repository-plugin`

**Solution:** The plugin needs to be published to Packagist or installed from a local path.

### 2. Permission Issues

**Error:** `Permission denied` when installing

**Solution:** Check file permissions and ensure you have write access to the vendor directory.

### 3. Autoload Issues

**Error:** `Class not found` errors

**Solution:** Regenerate autoload files:

```bash
composer dump-autoload
```

### 4. Version Conflicts

**Error:** Version constraint conflicts

**Solution:** Check Composer version compatibility and update if needed:

```bash
composer self-update
```

## Alternative Solutions

### Option 1: Use Standard Composer Repository with Auth

If the plugin continues to have issues, you can use the standard Composer repository with authentication:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://your-repo.com/composer-repository.json"
        }
    ],
    "config": {
        "github-oauth": {
            "your-repo.com": "YOUR_TOKEN"
        }
    }
}
```

### Option 2: Use Git Repository

For GitHub repositories, you can use the git repository type:

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/your-org/your-repo.git"
        }
    ]
}
```

### Option 3: Use Path Repository

For local development:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "path/to/your/package"
        }
    ]
}
```

## Getting Help

If you're still experiencing issues:

1. **Check the logs:** Look for detailed error messages in the Composer output
2. **Verify your setup:** Ensure all prerequisites are met
3. **Test with a minimal configuration:** Start with a simple setup and add complexity
4. **Check for conflicts:** Ensure no other plugins or configurations are interfering

## Plugin Status

The plugin should work with:
- Composer 2.x
- PHP 8.0+
- Standard Composer repository formats

If you continue to have issues, please:
1. Check the plugin's GitHub repository for known issues
2. Verify your Composer and PHP versions
3. Try the alternative solutions above 
