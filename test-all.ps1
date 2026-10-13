# LeoBot - Disposable Database Test Runner
$ErrorActionPreference = "Stop"

$php = "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"
$psql = "C:\Program Files\PostgreSQL\18\bin\psql.exe"
$config = "phpunit.financial-disposable.xml"
$dbName = "leobot_financial_disposable"

function Stop-Safely($message) {
    throw "SAFETY STOP: $message"
}

if (-not (Test-Path $php)) {
    Stop-Safely "PHP executable missing"
}

if (-not (Test-Path $psql)) {
    Stop-Safely "PostgreSQL executable missing"
}

if (-not (Test-Path $config)) {
    Stop-Safely "Disposable PHPUnit configuration missing"
}

if (-not (Test-Path "vendor/bin/phpunit")) {
    Stop-Safely "PHPUnit missing"
}

if (Test-Path "bootstrap/cache/config.php") {
    Stop-Safely "Laravel config cache exists"
}

# Validate PHPUnit configuration
[xml]$xml = Get-Content $config

$expected = @{
    APP_ENV       = "testing"
    DB_CONNECTION = "pgsql"
    DB_HOST       = "127.0.0.1"
    DB_PORT       = "5433"
    DB_DATABASE   = $dbName
    DB_URL        = ""
}

foreach ($key in $expected.Keys) {
    $items = @(
        $xml.phpunit.php.env |
        Where-Object { $_.name -eq $key }
    )

    if ($items.Count -ne 1) {
        Stop-Safely "$key missing or duplicated"
    }

    if (
        $items[0].value -cne $expected[$key] -or
        $items[0].force -ne "true"
    ) {
        Stop-Safely "$key configuration invalid"
    }
}

# Verify actual PostgreSQL database identity
$identity = & $psql `
    -X `
    -h 127.0.0.1 `
    -p 5433 `
    -U postgres `
    -d $dbName `
    -At `
    -v ON_ERROR_STOP=1 `
    -c "SELECT current_database() || ':' || inet_server_port();"

if (
    $LASTEXITCODE -ne 0 -or
    ($identity -join "").Trim() -ne "${dbName}:5433"
) {
    Stop-Safely "Actual PostgreSQL database identity mismatch"
}

Write-Host "`nDATABASE SAFETY CHECK PASSED"

# Discover test files
$allTests = @(
    Get-ChildItem "tests" -Recurse -Filter "*Test.php" -File |
    ForEach-Object {
        $_.FullName
    }
)

$truncation = @(
    $allTests | Where-Object {
        Select-String -Path $_ -Pattern 'use DatabaseTruncation;' -Quiet
    }
)

$refresh = @(
    $allTests | Where-Object {
        Select-String -Path $_ -Pattern 'use RefreshDatabase;' -Quiet
    }
)

$other = @(
    $allTests | Where-Object {
        $_ -notin $truncation -and $_ -notin $refresh
    }
)

if (
    $allTests.Count -ne 27 -or
    $truncation.Count -ne 3 -or
    $refresh.Count -ne 20 -or
    $other.Count -ne 4
) {
    Stop-Safely "Unexpected test file counts. Review before running."
}

Write-Host "Test files: $($allTests.Count)"
Write-Host "Truncation: $($truncation.Count)"
Write-Host "Refresh: $($refresh.Count)"
Write-Host "Other: $($other.Count)"

# Keep process environment isolated
$keys = @(
    "APP_ENV",
    "DB_CONNECTION",
    "DB_HOST",
    "DB_PORT",
    "DB_DATABASE",
    "DB_URL"
)

$oldEnv = @{}

foreach ($key in $keys) {
    $oldEnv[$key] = [Environment]::GetEnvironmentVariable(
        $key, "Process"
    )
}

try {
    foreach ($key in $expected.Keys) {
        [Environment]::SetEnvironmentVariable(
            $key, $expected[$key], "Process"
        )
    }

    function Run-TestGroup($name, $files) {
        Write-Host "`n=== $name ==="

        & $php "vendor/bin/phpunit" `
            "--configuration=$config" `
            @files

        if ($LASTEXITCODE -ne 0) {
            throw "$name FAILED: exit code $LASTEXITCODE"
        }

        Write-Host "$name PASSED"
    }

    Run-TestGroup "DATABASE TRUNCATION TESTS" $truncation
    Run-TestGroup "REFRESH DATABASE TESTS" $refresh
    Run-TestGroup "OTHER TESTS" $other

    Write-Host "`n================================"
    Write-Host "ALL THREE TEST GROUPS PASSED"
    Write-Host "================================"
}
finally {
    foreach ($key in $keys) {
        [Environment]::SetEnvironmentVariable(
            $key, $oldEnv[$key], "Process"
        )
    }

    Write-Host "Environment restored"
}
