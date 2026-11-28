param([ValidateSet('mysql','mariadb')][string]$Motor='mysql')
$ErrorActionPreference = 'Stop'
$mysql = if ($Motor -eq 'mysql') { 'C:/wamp64/bin/mysql/mysql8.4.7' } else { 'C:/wamp64/bin/mariadb/mariadb11.4.9' }
$daemon = if ($Motor -eq 'mysql') { 'mysqld.exe' } else { 'mariadbd.exe' }
$scratch = Join-Path ([IO.Path]::GetTempPath()) ('at-resena-motor-v2-' + [guid]::NewGuid().ToString('N'))
$null = New-Item -ItemType Directory -Path $scratch
$scratch = (Resolve-Path -LiteralPath $scratch).Path
$server = $null
$result = 1
try {
    $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,0)
    $listener.Start(); $port = $listener.LocalEndpoint.Port; $listener.Stop()
    $common = @('--no-defaults',"--basedir=$mysql","--datadir=$scratch/data","--log-error=$scratch/mysql.log")
    Write-Output "Fixture aislada: $scratch"
    if ($Motor -eq 'mysql') { & "$mysql/bin/$daemon" @common --initialize-insecure }
    else { & "$mysql/bin/mariadb-install-db.exe" "--datadir=$scratch/data" "--port=$port" --silent }
    if ($LASTEXITCODE -ne 0) { throw 'Inicialización falló.' }
    $argsMotor = $common + @('--bind-address=127.0.0.1',"--port=$port",'--skip-log-bin','--innodb-buffer-pool-size=32M')
    if ($Motor -eq 'mysql') { $argsMotor += '--mysqlx=OFF' }
    $server = Start-Process -FilePath "$mysql/bin/$daemon" -ArgumentList $argsMotor -WindowStyle Hidden -PassThru
    $ready = $false
    for ($n=0; $n -lt 120; $n++) {
        $client = [Net.Sockets.TcpClient]::new()
        try { $client.Connect('127.0.0.1',$port); $ready=$true } catch {} finally { $client.Dispose() }
        if ($ready -or $server.HasExited) { break }
        Start-Sleep -Milliseconds 250
    }
    if (!$ready) { throw 'El motor fixture no inició.' }
    $env:AT_RG_MOTOR_FIXTURE='1'; $env:AT_RG_MOTOR_PORT=[string]$port
    try {
        $ErrorActionPreference = 'Continue'
        & C:/wamp64/bin/php/php8.5.0/php.exe -d xdebug.mode=off tests/plan-trabajo/resena-migracion-test.php 2>&1 | ForEach-Object { $_.ToString() }
        $result = $LASTEXITCODE
    } finally { $ErrorActionPreference = 'Stop' }
} catch {
    Write-Output $_.Exception.Message
    if (Test-Path -LiteralPath "$scratch/mysql.log") { Select-String -LiteralPath "$scratch/mysql.log" -Pattern '\[ERROR\]' | ForEach-Object { $_.Line } }
} finally {
    if ($server -and !$server.HasExited) {
        & 'C:/wamp64/bin/mysql/mysql8.4.7/bin/mysqladmin.exe' '--no-defaults' '--host=127.0.0.1' "--port=$port" '--user=root' shutdown
        $server.WaitForExit(20000) | Out-Null
    }
    Remove-Item Env:AT_RG_MOTOR_FIXTURE,Env:AT_RG_MOTOR_PORT -ErrorAction SilentlyContinue
    # Solo el directorio creado en esta ejecución, con el servidor detenido.
    $resolved = [IO.Path]::GetFullPath($scratch)
    if ([IO.Path]::GetDirectoryName($resolved) -eq [IO.Path]::GetTempPath().TrimEnd('\') -and [IO.Path]::GetFileName($resolved).StartsWith('at-resena-motor-v2-') -and (!$server -or $server.HasExited)) {
        Remove-Item -LiteralPath $resolved -Recurse -Force
        Write-Output 'Fixture retirada; servidor detenido.'
    }
}
exit $result
