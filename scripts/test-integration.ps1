$ErrorActionPreference = 'Stop'
$repository = Split-Path -Parent $PSScriptRoot
$previousPassword = $env:TEST_DB_PASSWORD
$env:TEST_DB_PASSWORD = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
$testExitCode = 1
Push-Location $repository
try {
    docker compose -f compose.test.yaml up --abort-on-container-exit --exit-code-from tests
    $testExitCode = $LASTEXITCODE
}
finally {
    # This named Compose project contains only disposable integration containers.
    docker compose -f compose.test.yaml down
    $env:TEST_DB_PASSWORD = $previousPassword
    Pop-Location
}
exit $testExitCode
