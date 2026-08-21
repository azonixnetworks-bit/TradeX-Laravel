param(
    [string]$ProjectRoot = "."
)

$ErrorActionPreference = "Stop"

$ProjectRoot = (Resolve-Path $ProjectRoot).Path
$RelativePath = "resources/views/admin/education/modules/index.blade.php"
$TargetPath = Join-Path $ProjectRoot $RelativePath
$BackupPath = "$TargetPath.admin03-hardening-backup"

if (-not (Test-Path $TargetPath)) {
    throw "Module index not found: $TargetPath"
}

Copy-Item $TargetPath $BackupPath -Force

$content = [IO.File]::ReadAllText($TargetPath)

# Earlier balance package contained an unnecessarily compressed replacement.
# If detected, restore the committed frozen file from HEAD before adding
# only the required Manage Lessons navigation.
if ($content.Contains("Module Management (Navigation Integrated)")) {
    Push-Location $ProjectRoot
    try {
        $gitContent = & git show "HEAD:$RelativePath" 2>$null

        if ($LASTEXITCODE -ne 0 -or -not $gitContent) {
            throw "Could not restore the frozen Module index from Git HEAD. Backup kept at: $BackupPath"
        }

        $content = ($gitContent -join "`n") + "`n"
    }
    finally {
        Pop-Location
    }
}

if (-not $content.Contains("Manage Lessons")) {
    $marker = @'
                    <div class="module-actions">

                        @if($permissions['update'] ?? false)
'@

    $insert = @'
                    <div class="module-actions">

                        @if($permissions['view'] ?? false)

                            <a
                                href="{{ route(
                                    'admin.education.courses.modules.lessons.index',
                                    [
                                        'course' => $course,
                                        'module' => $module,
                                    ]
                                ) }}"
                                class="module-action-link"
                            >
                                Manage Lessons
                            </a>

                        @endif


                        @if($permissions['update'] ?? false)
'@

    if (-not $content.Contains($marker)) {
        throw "Expected Module action marker was not found. No write performed. Backup: $BackupPath"
    }

    $content = $content.Replace($marker, $insert)
}

[IO.File]::WriteAllText(
    $TargetPath,
    $content,
    [Text.UTF8Encoding]::new($false)
)

Write-Host "Module index hardening complete."
Write-Host "Target : $TargetPath"
Write-Host "Backup : $BackupPath"
