# Composer JSON Validation Solutions

## The Problem

Composer's built-in JSON schema validation doesn't recognize custom repository types like `composer-authenticated`. This causes validation errors when running `composer validate`.

## Solutions

### Solution 1: Use Standard Composer Repository (Recommended)

Instead of using `composer-authenticated`, use the standard `composer` repository type with authentication:

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
            "your-repo.com": "YOUR_GITHUB_TOKEN"
        },
        "http-basic": {
            "your-repo.com": {
                "username": "your-username",
                "password": "your-password"
            }
        }
    }
}
```

**Pros:**
- ✅ Works with standard Composer validation
- ✅ No custom plugin needed
- ✅ Fully supported by Composer
- ✅ Authentication works automatically

**Cons:**
- ❌ Requires separate authentication configuration

### Solution 2: Skip Validation

Use the `--no-validate` flag to skip validation:

```bash
composer install --no-validate
composer update --no-validate
```

**Pros:**
- ✅ Quick fix
- ✅ Works immediately

**Cons:**
- ❌ Skips all validation, not just repository types
- ❌ May miss other validation errors

### Solution 3: Use Custom Validation Script

Use the provided validation script that accepts our repository type:

```bash
# Install the plugin first
composer require sonrac/composer-authenticated-repository-plugin

# Use custom validation
php vendor/sonrac/composer-authenticated-repository-plugin/validate-composer.php
```

**Pros:**
- ✅ Validates our custom repository type
- ✅ Still validates everything else
- ✅ Provides helpful error messages

**Cons:**
- ❌ Requires additional step
- ❌ Not integrated with Composer commands

### Solution 4: Use Different Repository Types

For specific use cases, use alternative repository types:

#### For GitHub Repositories:
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

#### For Local Development:
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

#### For Artifacts:
```json
{
    "repositories": [
        {
            "type": "artifact",
            "url": "path/to/artifacts"
        }
    ]
}
```

## Recommended Approach

### For Production Use:

**Use Solution 1** - Standard Composer repository with authentication:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://artifacts.company.com/composer-repository.json"
        }
    ],
    "config": {
        "http-basic": {
            "artifacts.company.com": {
                "username": "%env(ARTIFACT_USERNAME)%",
                "password": "%env(ARTIFACT_PASSWORD)%"
            }
        }
    }
}
```

### For Development/Testing:

**Use Solution 2** - Skip validation during development:

```bash
composer install --no-validate
```

### For Custom Validation:

**Use Solution 3** - Custom validation script:

```bash
# Add to your CI/CD pipeline
php vendor/sonrac/composer-authenticated-repository-plugin/validate-composer.php
```

## Environment Variables

For security, use environment variables:

```bash
# Set environment variables
export ARTIFACT_USERNAME=your-username
export ARTIFACT_PASSWORD=your-password
export GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Or use .env file
echo "ARTIFACT_USERNAME=your-username" >> .env
echo "ARTIFACT_PASSWORD=your-password" >> .env
echo "GITHUB_TOKEN=ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" >> .env
```

## CI/CD Integration

### GitHub Actions Example:

```yaml
name: Validate Composer
on: [push, pull_request]

jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - run: composer install --no-validate
      - run: php vendor/sonrac/composer-authenticated-repository-plugin/validate-composer.php
```

### GitLab CI Example:

```yaml
validate:
  image: php:8.1-cli
  script:
    - curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    - composer install --no-validate
    - php vendor/sonrac/composer-authenticated-repository-plugin/validate-composer.php
```

## Troubleshooting

### Common Issues:

1. **Plugin not found:**
   ```bash
   composer require sonrac/composer-authenticated-repository-plugin
   ```

2. **Authentication errors:**
   ```bash
   composer config http-basic.your-repo.com username password
   ```

3. **Validation still failing:**
   ```bash
   composer install --no-validate
   ```

### Debug Commands:

```bash
# Check Composer configuration
composer config --list

# Check authentication
composer config http-basic
composer config github-oauth

# Test repository access
curl -u username:password https://your-repo.com/composer-repository.json
```

## Conclusion

The best approach depends on your specific needs:

- **For production:** Use standard Composer repositories with authentication
- **For development:** Skip validation or use custom validation script
- **For CI/CD:** Use custom validation script in your pipeline

The plugin still provides value by extending Composer's functionality, but the validation issue is a limitation of Composer's schema system that we work around with these solutions. 
