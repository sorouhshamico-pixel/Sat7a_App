# Run this in an elevated (Run as Administrator) PowerShell window.
# It merges the already-downloaded PostGIS 3.6.2 bundle into the PostgreSQL 17
# installation, then enables the extension on the tow_platform database.

$src = "$env:TEMP\postgis-extract\postgis-bundle-pg17-3.6.2x64"
$dst = "C:\Program Files\PostgreSQL\17"

Copy-Item -Path "$src\bin\*"       -Destination "$dst\bin"       -Recurse -Force
Copy-Item -Path "$src\lib\*"       -Destination "$dst\lib"       -Recurse -Force
Copy-Item -Path "$src\share\*"     -Destination "$dst\share"     -Recurse -Force
Copy-Item -Path "$src\gdal-data"   -Destination "$dst\gdal-data" -Recurse -Force

Write-Host "PostGIS files copied. Now creating the database and enabling the extension..."

$env:PGPASSWORD = "TowPlatformLocalDev2026"
$psql = "$dst\bin\psql.exe"

& $psql -U postgres -h 127.0.0.1 -c "CREATE DATABASE tow_platform;"
& $psql -U postgres -h 127.0.0.1 -d tow_platform -c "CREATE EXTENSION IF NOT EXISTS postgis;"
& $psql -U postgres -h 127.0.0.1 -d tow_platform -c "SELECT PostGIS_Full_Version();"

Write-Host "Done."
