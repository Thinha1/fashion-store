param([string]$Script = 'tests/Browser/admin-interface.cjs')

$ErrorActionPreference = 'Stop'
Push-Location (Resolve-Path (Join-Path $PSScriptRoot '../..'))
$hotPath = Join-Path (Get-Location) 'public/hot'
$backupPath = Join-Path (Get-Location) 'public/hot.qa-backup'
$hadHotFile = Test-Path -LiteralPath $hotPath
try {
    if (Test-Path -LiteralPath $backupPath) { throw 'public/hot.qa-backup already exists; restore it before running QA.' }
    if ($hadHotFile) { Move-Item -LiteralPath $hotPath -Destination $backupPath }
    try {
        docker run --rm --user 0 --network fashion-store_default -v "${PWD}:/work" -w /work -e PUPPETEER_MODULE=/home/mermaidcli/node_modules/puppeteer -e CHROMIUM_PATH=/usr/bin/chromium -e ADMIN_TEST_URL=http://nginx -e ADMIN_TEST_IMAGE_ORIGIN=http://minio:9000 --entrypoint node ghcr.io/mermaid-js/mermaid-cli/mermaid-cli:11.17.0 $Script
        $qaExit = $LASTEXITCODE
    } finally {
        if ($hadHotFile) { Move-Item -LiteralPath $backupPath -Destination $hotPath }
    }
} finally { Pop-Location }
exit $qaExit
