
param(
    [switch]$Check
)

$ErrorActionPreference = 'Stop'

# LeoBot PHP Header Safety Fixer
# Removes BOM and leading whitespace before <?php.
# Preserves all other bytes in the file.

$projectRoot = Split-Path -Parent $PSScriptRoot

$folders = @(
    'app'
    'bootstrap'
    'config'
    'database'
    'public'
    'routes'
    'tests'
)

$scanned = 0
$fixed = 0
$problems = 0

$phpHeader = [byte[]]@(60, 63, 112, 104, 112)

Write-Host ''
Write-Host 'LeoBot PHP Header Safety Check'
Write-Host '--------------------------------'

foreach ($folder in $folders) {

    $folderPath = Join-Path $projectRoot $folder

    if (-not (Test-Path -LiteralPath $folderPath)) {
        continue
    }

    $files = Get-ChildItem `
        -LiteralPath $folderPath `
        -Filter '*.php' `
        -File `
        -Recurse

    foreach ($file in $files) {

        # Blade templates are not regular PHP source files.
        if ($file.Name.EndsWith('.blade.php')) {
            continue
        }

        $scanned++

        $bytes = [System.IO.File]::ReadAllBytes(
            $file.FullName
        )

        $offset = 0

        # Skip UTF-8 BOM if present.
        if (
            $bytes.Length -ge 3 -and
            $bytes[0] -eq 239 -and
            $bytes[1] -eq 187 -and
            $bytes[2] -eq 191
        ) {
            $offset = 3
        }

        # Skip only whitespace before PHP opening tag.
        while ($offset -lt $bytes.Length) {

            $currentByte = $bytes[$offset]

            if (
                $currentByte -eq 32 -or
                $currentByte -eq 9 -or
                $currentByte -eq 10 -or
                $currentByte -eq 13
            ) {
                $offset++
            }
            else {
                break
            }
        }

        $validHeader = $true

        if (($bytes.Length - $offset) -lt 5) {
            $validHeader = $false
        }
        else {
            for ($i = 0; $i -lt 5; $i++) {

                if (
                    $bytes[$offset + $i] -ne
                    $phpHeader[$i]
                ) {
                    $validHeader = $false
                    break
                }
            }
        }

        # Unexpected content: never modify automatically.
        if (-not $validHeader) {

            $problems++

            Write-Host "INVALID HEADER: $($file.FullName)"

            continue
        }

        # A correct file requires no changes.
        if ($offset -eq 0) {
            continue
        }

        if ($Check) {

            $problems++

            Write-Host "NEEDS FIX: $($file.FullName)"

            continue
        }

        # Copy all bytes after unwanted leading bytes.
        # This avoids rewriting or re-encoding PHP code.
        $newLength = $bytes.Length - $offset

        $cleanBytes = New-Object byte[] $newLength

        [System.Array]::Copy(
            $bytes,
            $offset,
            $cleanBytes,
            0,
            $newLength
        )

        [System.IO.File]::WriteAllBytes(
            $file.FullName,
            $cleanBytes
        )

        $fixed++

        Write-Host "FIXED: $($file.FullName)"
    }
}

Write-Host ''
Write-Host "PHP FILES SCANNED: $scanned"
Write-Host "PHP FILES FIXED: $fixed"
Write-Host "PROBLEMS FOUND: $problems"

if ($problems -gt 0) {

    Write-Host 'PHP Header Safety Check FAILED.'

    exit 1
}

Write-Host 'PHP Header Safety Check PASSED.'

exit 0
