$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$total = 0
$byExt = @{}

Get-ChildItem -Path $root -Recurse -Include '*.php','*.js','*.css','*.sql' | Where-Object {
    $_.FullName -notmatch '\\(uploads|vendor|node_modules|\.git|\.playwright-mcp)\\'
} | ForEach-Object {
    $lines = (Get-Content $_.FullName -ErrorAction SilentlyContinue | Measure-Object -Line).Lines
    $ext = $_.Extension
    if (-not $byExt[$ext]) { $byExt[$ext] = 0 }
    $byExt[$ext] += $lines
    $total += $lines
}

Write-Host "=== 程式碼行數統計 ==="
$byExt.GetEnumerator() | Sort-Object Name | ForEach-Object { Write-Host "$($_.Key): $($_.Value)" }
Write-Host ""
Write-Host "Total: $total lines"
