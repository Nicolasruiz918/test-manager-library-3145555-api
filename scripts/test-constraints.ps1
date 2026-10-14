$ErrorActionPreference = 'Stop'
$repository = Split-Path -Parent $PSScriptRoot
$previousPassword = $env:TEST_DB_PASSWORD
$env:TEST_DB_PASSWORD = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
Push-Location $repository
try {
    docker compose -f compose.test.yaml up -d --wait db
    if ($LASTEXITCODE -ne 0) { throw 'Test database startup failed.' }
    docker compose -f compose.test.yaml run --rm tests php artisan library:migrate
    if ($LASTEXITCODE -ne 0) { throw 'Migration failed.' }
    $statements = @(
        @{
            Constraint = 'ck_book_total_positive'
            Sql = "INSERT INTO library.book (id,title,author,isbn,category_id,total_copies,available_copies,cover_key,deleted_at) VALUES ('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa','Invalid total','Test','0000000000000','11111111-1111-4111-8111-111111111111',0,0,NULL,NULL)"
        },
        @{
            Constraint = 'ck_user_role'
            Sql = 'INSERT INTO library."user" (id,username,password_hash,role) VALUES (''bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'',''invalid-role'',''test-only'',''owner'')'
        }
    )
    foreach ($check in $statements) {
        $result = docker compose -f compose.test.yaml exec -T db psql -U library_test -d manager_library_test -v ON_ERROR_STOP=1 -c $check.Sql 2>&1
        $code = $LASTEXITCODE
        if ($code -eq 0 -or (($result | Out-String) -notmatch $check.Constraint)) { throw "Expected rejection by $($check.Constraint) was not observed." }
        Write-Output "$($check.Constraint): psql rejected the invalid INSERT (exit $code)."
    }
}
finally {
    docker compose -f compose.test.yaml down --volumes
    $env:TEST_DB_PASSWORD = $previousPassword
    Pop-Location
}
