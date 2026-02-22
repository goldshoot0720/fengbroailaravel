$total = 0
$byExt = @{}
Get-ChildItem -Path 'd:\qodercode\laravel20260205' -Recurse -Include '*.php','*.js','*.css','*.sql' | Where-Object { $_.FullName -notmatch 'uploads' } | ForEach-Object {
    $lines = (Get-Content $_.FullName -ErrorAction SilentlyContinue | Measure-Object -Line).Lines
    $ext = $_.Extension
    if (-not $byExt[$ext]) { $byExt[$ext] = 0 }
    $byExt[$ext] += $lines
    $total += $lines
}
Write-Host "=== 各副檔名統計 ==="
$byExt.GetEnumerator() | Sort-Object Name | ForEach-Object { Write-Host "$($_.Key): $($_.Value)" }
Write-Host ""
Write-Host "Total: $total lines"
