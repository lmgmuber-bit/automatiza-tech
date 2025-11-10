param(
    [Parameter(Mandatory=$true)][string]$Host,
    [Parameter(Mandatory=$true)][string]$Username,
    [Parameter(Mandatory=$true)][string]$Password,
    [string]$RemotePath = "/public_html/wp-content/themes/automatiza-tech",
    [string]$LocalPath = "c:\wamp64\www\automatiza-tech\wp-content\themes\automatiza-tech",
    [int]$Port = 21,
    [bool]$EnableSsl = $true,
    [bool]$Passive = $true
)

function New-FtpRequest {
    param(
        [string]$Uri,
        [string]$Method
    )
    $req = [System.Net.FtpWebRequest]::Create($Uri)
    $req.Credentials = New-Object System.Net.NetworkCredential($Username, $Password)
    $req.Method = $Method
    $req.EnableSsl = $EnableSsl
    $req.UseBinary = $true
    $req.KeepAlive = $false
    $req.UsePassive = $Passive
    return $req
}

function Ensure-RemoteDir {
    param([string]$RemoteDir)
    try {
        $req = New-FtpRequest -Uri $RemoteDir -Method [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        $resp = $req.GetResponse(); $resp.Close()
    } catch {
        # Ignore if exists
    }
}

function Upload-File {
    param([string]$LocalFile, [string]$RemoteFile)
    $bufferSize = 8192
    $req = New-FtpRequest -Uri $RemoteFile -Method [System.Net.WebRequestMethods+Ftp]::UploadFile
    $fileStream = [System.IO.File]::OpenRead($LocalFile)
    $reqStream = $req.GetRequestStream()
    try {
        $buffer = New-Object byte[] $bufferSize
        while(($read = $fileStream.Read($buffer,0,$buffer.Length)) -gt 0){
            $reqStream.Write($buffer,0,$read)
        }
    }
    finally {
        $reqStream.Close(); $fileStream.Close()
    }
    $resp = $req.GetResponse(); $resp.Close()
}

function Upload-Directory {
    param([string]$LocalDir, [string]$RemoteDir)
    $exclude = @('.git','node_modules','dist','.DS_Store','Thumbs.db')
    Ensure-RemoteDir -RemoteDir $RemoteDir
    Get-ChildItem -LiteralPath $LocalDir -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($LocalDir.Length).TrimStart('\\')
        if($exclude | Where-Object { $rel -like "*$_*" }){ return }
        $remoteSubDir = [System.IO.Path]::GetDirectoryName($rel).Replace('\','/')
        $targetDir = if([string]::IsNullOrEmpty($remoteSubDir)){ $RemoteDir } else { "$RemoteDir/$remoteSubDir" }
        Ensure-RemoteDir -RemoteDir $targetDir
        $remoteFile = "$targetDir/" + [System.IO.Path]::GetFileName($_.Name)
        Write-Host "Uploading" $rel
        Upload-File -LocalFile $_.FullName -RemoteFile $remoteFile
    }
}

$scheme = if($EnableSsl){ 'ftp' } else { 'ftp' }
$baseUri = "$scheme://$Host:$Port"
# Normalize remote path
if ($RemotePath.StartsWith('/')) { $RemotePath = $RemotePath.TrimStart('/') }
$remoteUri = "$baseUri/$RemotePath"

Write-Host "Connecting to $Host (FTPS=$EnableSsl, Passive=$Passive)"
Upload-Directory -LocalDir $LocalPath -RemoteDir $remoteUri
Write-Host "Deployment completed to $remoteUri"
