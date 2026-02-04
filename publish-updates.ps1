# Publish Updates to Staging (Sandbox)
# This script commits all local changes and pushes them to the 'staging' branch.
# This triggers the automatic deployment to the Sandbox environment via GitHub Actions.

$branch = git rev-parse --abbrev-ref HEAD

if ($branch -ne "staging") {
    Write-Host "Error: You are currently on branch '$branch'." -ForegroundColor Red
    Write-Host "Please switch to 'staging' before publishing updates." -ForegroundColor Yellow
    exit 1
}

# Check for changes
$status = git status --porcelain
if ([string]::IsNullOrWhiteSpace($status)) {
    Write-Host "No changes to publish." -ForegroundColor Yellow
    exit 0
}

# Show status
git status

# Ask for commit message
$commitMessage = Read-Host "Enter commit message (e.g., 'Fix typo in header')"
if ([string]::IsNullOrWhiteSpace($commitMessage)) {
    Write-Host "Commit message is required." -ForegroundColor Red
    exit 1
}

# Add, Commit, Push
Write-Host "Adding files..." -ForegroundColor Cyan
git add .

Write-Host "Committing..." -ForegroundColor Cyan
git commit -m "$commitMessage"

Write-Host "Pushing to GitHub (Staging)..." -ForegroundColor Cyan
git push origin staging

if ($LASTEXITCODE -eq 0) {
    Write-Host "Success! Changes pushed to GitHub." -ForegroundColor Green
    Write-Host "The 'Deploy to Staging' action should start automatically." -ForegroundColor Green
    Write-Host "Check status here: https://github.com/dwes123/FODS/actions" -ForegroundColor Cyan
} else {
    Write-Host "Error pushing to GitHub." -ForegroundColor Red
}
