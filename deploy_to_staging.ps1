# Deployment script for Front Office Dynasty Sports
# Target: WordPress.com Staging

$username = "staging-9cb1-frontofficedynastysports.wordpress.com"
$host_addr = "sftp.wp.com"
$remote_theme_path = "htdocs/wp-content/themes/twentytwentytwo-child"

# List of files/folders to sync
$items_to_sync = @(
    "functions.php",
    "style.css",
    "Inc",
    "JS",
    "acf-json"
)

Write-Host "Starting deployment to staging..." -ForegroundColor Cyan

foreach ($item in $items_to_sync) {
    if (Test-Path $item) {
        Write-Host "Syncing $item..." -ForegroundColor Yellow
        # Use scp -r to recursively copy
        # We use -o StrictHostKeyChecking=no to avoid prompts for new hosts
        scp -r -o StrictHostKeyChecking=no "$item" "${username}@${host_addr}:${remote_theme_path}/"
    } else {
        Write-Host "Warning: $item not found locally. Skipping." -ForegroundColor Red
    }
}

Write-Host "Deployment complete! Check your staging site at: https://staging-9cb1-frontofficedynastysports.wpcomstaging.com/" -ForegroundColor Green
