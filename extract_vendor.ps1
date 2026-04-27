
$cacheRoot = "C:\xampp\htdocs\teenpatti\core\.composer\cache\files"
$vendorRoot = "C:\xampp\htdocs\teenpatti\core\vendor"

if (!(Test-Path $vendorRoot)) {
    New-Item -ItemType Directory -Path $vendorRoot
}

$zips = Get-ChildItem -Path $cacheRoot -Recurse -Filter "*.zip"
Write-Host "Found $($zips.Count) zips"

foreach ($zip in $zips) {
    Write-Host "Processing $($zip.FullName)"
    # Get package name from path
    # Path is .../cache/files/vendor/package/hash.zip
    $parts = $zip.DirectoryName.Split([System.IO.Path]::DirectorySeparatorChar)
    $packageName = $parts[-1]
    $packageVendor = $parts[-2]
    $fullPackageName = "$packageVendor/$packageName"
    
    $destPath = Join-Path $vendorRoot $fullPackageName
    
    Write-Host "Extracting $fullPackageName to $destPath"
    
    if (!(Test-Path $destPath)) {
        New-Item -ItemType Directory -Path $destPath -Force
    }
    
    $tempDir = Join-Path $env:TEMP ([System.IO.Path]::GetRandomFileName())
    New-Item -ItemType Directory -Path $tempDir
    
    try {
        Expand-Archive -Path $zip.FullName -DestinationPath $tempDir -Force
        
        # Zip usually contains one directory with the content
        $contentDir = Get-ChildItem -Path $tempDir -Directory | Select-Object -First 1
        if ($contentDir) {
            Copy-Item -Path "$($contentDir.FullName)\*" -Destination $destPath -Recurse -Force
        } else {
            Copy-Item -Path "$tempDir\*" -Destination $destPath -Recurse -Force
        }
    } catch {
        Write-Error "Failed to extract $($zip.FullName): $_"
    } finally {
        Remove-Item -Path $tempDir -Recurse -Force
    }
}
