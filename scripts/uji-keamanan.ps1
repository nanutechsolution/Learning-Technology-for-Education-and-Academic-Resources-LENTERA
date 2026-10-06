<#
 Uji keamanan dan fungsional LENTERA Phase 2 (SEMENTARA).
 Prasyarat:
   1) php spark lentera:test-data setup
   2) php spark serve  (jendela terminal lain)
 Jalankan dari root project:
   powershell -ExecutionPolicy Bypass -File .\scripts\uji-keamanan.ps1
#>
param(
    [string]$BaseUrl = 'http://localhost:8080',
    [string]$Project = (Get-Location).Path
)

$ErrorActionPreference = 'Continue'
$ProgressPreference    = 'SilentlyContinue'
$Password              = 'Lentera@123'
$script:Pass           = 0
$script:Fail           = 0

# ---------------------------------------------------------------- util

function Check([bool]$Ok, [string]$Name, [string]$Detail = '') {
    if ($Ok) {
        $script:Pass++
        Write-Host "OK    - $Name" -ForegroundColor Green
    } else {
        $script:Fail++
        Write-Host "GAGAL - $Name $Detail" -ForegroundColor Red
    }
}

function Section([string]$Title) {
    Write-Host ''
    Write-Host "== $Title ==" -ForegroundColor Cyan
}

function Invoke-Req {
    param([string]$Method, [string]$Path, $Session, $Body = $null, [string]$ContentType = '')

    $url    = $BaseUrl.TrimEnd('/') + '/' + $Path.TrimStart('/')
    $params = @{ Uri = $url; Method = $Method; WebSession = $Session; UseBasicParsing = $true; ErrorAction = 'Stop' }

    if ($null -ne $Body)   { $params['Body'] = $Body }
    if ($ContentType -ne '') { $params['ContentType'] = $ContentType }

    try {
        $r = Invoke-WebRequest @params
        return [pscustomobject]@{ Status = [int]$r.StatusCode; Body = [string]$r.Content; Length = [int64]$r.RawContentLength }
    } catch {
        $resp = $_.Exception.Response
        if ($null -eq $resp) { throw }

        $body = ''
        if ($_.ErrorDetails -and $_.ErrorDetails.Message) { $body = [string]$_.ErrorDetails.Message }

        return [pscustomobject]@{ Status = [int]$resp.StatusCode; Body = $body; Length = 0 }
    }
}

function Get-Csrf($Session, [string]$Path) {
    $r = Invoke-Req 'GET' $Path $Session
    if ($r.Body -match 'name="(csrf_[^"]+)"[^>]*?value="([^"]+)"') {
        return [pscustomobject]@{ Name = $Matches[1]; Value = $Matches[2]; Page = $r }
    }
    throw "Token CSRF tidak ditemukan di '$Path' (status $($r.Status))."
}

function New-Login([string]$Username, [string]$Area) {
    $s    = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $t    = Get-Csrf $s 'login'
    $body = @{ username = $Username; password = $Password }
    $body[$t.Name] = $t.Value
    $null = Invoke-Req 'POST' 'login' $s $body

    $dash = Invoke-Req 'GET' "$Area/dashboard" $s
    if ($dash.Status -ne 200 -or $dash.Body -notmatch 'Keluar') {
        Write-Host "Login gagal untuk '$Username'. Pastikan akun ada (jalankan setup), password Lentera@123, dan field form login bernama username/password." -ForegroundColor Red
        exit 1
    }

    return $s
}

function Post-Form($Session, [string]$TokenPage, [string]$Path, [hashtable]$Fields = @{}) {
    $t = Get-Csrf $Session $TokenPage
    $f = @{} + $Fields
    $f[$t.Name] = $t.Value
    return Invoke-Req 'POST' $Path $Session $f
}

function New-Multipart([hashtable]$Fields, [string]$FileField, [string]$FileName, [byte[]]$FileBytes, [string]$FileMime) {
    $boundary = '----LenteraUji' + [guid]::NewGuid().ToString('N')
    $enc      = New-Object System.Text.UTF8Encoding($false)
    $ms       = New-Object System.IO.MemoryStream
    $write    = { param([string]$text) $b = $enc.GetBytes($text); $ms.Write($b, 0, $b.Length) }

    foreach ($k in $Fields.Keys) {
        & $write ("--$boundary`r`nContent-Disposition: form-data; name=`"$k`"`r`n`r`n$($Fields[$k])`r`n")
    }

    if ($FileName -ne '') {
        & $write ("--$boundary`r`nContent-Disposition: form-data; name=`"$FileField`"; filename=`"$FileName`"`r`nContent-Type: $FileMime`r`n`r`n")
        $ms.Write($FileBytes, 0, $FileBytes.Length)
        & $write "`r`n"
    }

    & $write "--$boundary--`r`n"

    return [pscustomobject]@{ Body = $ms.ToArray(); ContentType = "multipart/form-data; boundary=$boundary" }
}

function Send-Material($Session, [string]$FormPage, [string]$Action, [hashtable]$Fields, [string]$FileName = '', [byte[]]$Bytes = $null, [string]$Mime = 'application/pdf') {
    $t = Get-Csrf $Session $FormPage
    $f = @{} + $Fields
    $f[$t.Name] = $t.Value

    $mp = New-Multipart $f 'material_file' $FileName $Bytes $Mime
    return Invoke-Req 'POST' $Action $Session $mp.Body $mp.ContentType
}

function Spark([string[]]$Cmd) {
    Push-Location $Project
    try { return ((& php spark @Cmd 2>&1) -join "`n") } finally { Pop-Location }
}

function Get-Id([string]$Title) {
    $o = Spark @('lentera:test-data', 'id', $Title)
    if ($o -match 'ID=(\d+)') { return [int]$Matches[1] }
    return $null
}

function Get-File([int]$Id) {
    $o = Spark @('lentera:test-data', 'info', "$Id")
    if ($o -match 'FILE=(\S+)' -and $Matches[1] -ne 'NONE') { return $Matches[1] }
    return $null
}

function Test-FileOnDisk([string]$Name) {
    $o = Spark @('lentera:test-data', 'exists', $Name)
    return ($o -match 'EXISTS=1')
}

function Test-NoPhpError($r) {
    return ($r.Body -notmatch 'ErrorException|TypeError|Fatal error|Undefined (variable|array)')
}

# ---------------------------------------------------------------- persiapan

$idFile = Join-Path $Project 'writable\test-ids.json'
if (-not (Test-Path $idFile)) {
    Write-Host 'writable\test-ids.json belum ada. Jalankan dulu: php spark lentera:test-data setup' -ForegroundColor Red
    exit 1
}
$ids = Get-Content $idFile -Raw | ConvertFrom-Json

try {
    $null = Invoke-WebRequest -Uri ($BaseUrl.TrimEnd('/') + '/login') -UseBasicParsing -ErrorAction Stop
} catch {
    Write-Host "Server tidak dapat dijangkau di $BaseUrl. Jalankan: php spark serve" -ForegroundColor Red
    exit 1
}

$tAPub   = '[UJI] A Publish (file)'
$tBPub   = '[UJI] B Publish (file)'
$tADraft = '[UJI] A Draft'

$pdfOk    = [System.Text.Encoding]::ASCII.GetBytes("%PDF-1.4`n1 0 obj`n<< /Type /Catalog >>`nendobj`ntrailer`n<< /Root 1 0 R >>`n%%EOF`n")
$pdfOk2   = [System.Text.Encoding]::ASCII.GetBytes("%PDF-1.4`n1 0 obj`n<< /Type /Catalog /Versi 2 >>`nendobj`ntrailer`n<< /Root 1 0 R >>`n%%EOF`n")
$phpCode  = [System.Text.Encoding]::ASCII.GetBytes('<?php echo "x"; ?>')
$htmlCode = [System.Text.Encoding]::ASCII.GetBytes('<html><script>alert(1)</script></html>')
$exeBytes = [System.Text.Encoding]::ASCII.GetBytes('MZ' + ('A' * 200))

Write-Host 'Login semua akun uji...'
$anon   = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$admin  = New-Login 'admin' 'admin'
$guruA  = New-Login 'guru.demo' 'guru'
$guruB  = New-Login 'guru.b' 'guru'
$siswaA = New-Login 'siswa.demo' 'siswa'
$siswaB = New-Login 'siswa.b' 'siswa'

# ---------------------------------------------------------------- smoke

Section 'Smoke test: halaman Phase 1 dan Phase 2 terbuka normal'

foreach ($p in 'admin/dashboard', 'admin/academic-years', 'admin/teachers', 'admin/students', 'admin/classes', 'admin/subjects', 'admin/courses') {
    $r = Invoke-Req 'GET' $p $admin
    Check ($r.Status -eq 200 -and (Test-NoPhpError $r)) "Admin GET $p" "(status $($r.Status))"
}

foreach ($p in 'guru/dashboard', 'guru/courses', "guru/courses/$($ids.courseA)/materials", "guru/courses/$($ids.courseA)/materials/create", "guru/materials/$($ids.matAPub)", "guru/materials/$($ids.matAPub)/edit", "guru/materials/$($ids.matAPub)/download") {
    $r = Invoke-Req 'GET' $p $guruA
    Check ($r.Status -eq 200 -and (Test-NoPhpError $r)) "Guru A GET $p" "(status $($r.Status))"
}

foreach ($p in 'siswa/dashboard', 'siswa/courses', "siswa/courses/$($ids.courseA)", "siswa/materials/$($ids.matAPub)", "siswa/materials/$($ids.matAPub)/download") {
    $r = Invoke-Req 'GET' $p $siswaA
    Check ($r.Status -eq 200 -and (Test-NoPhpError $r)) "Siswa A GET $p" "(status $($r.Status))"
}

$r = Invoke-Req 'GET' "guru/materials/$($ids.matBPub)" $guruB
Check ($r.Status -eq 200) 'Guru B membuka materinya sendiri' "(status $($r.Status))"
$r = Invoke-Req 'GET' "siswa/materials/$($ids.matBPub)/download" $siswaB
Check ($r.Status -eq 200 -and $r.Length -gt 0) 'Siswa B mengunduh file course-nya sendiri' "(status $($r.Status))"

$r = Invoke-Req 'GET' "siswa/courses/$($ids.courseA)" $siswaA
Check ($r.Body.Contains($tAPub) -and -not $r.Body.Contains($tADraft)) 'Siswa A melihat materi published dan tidak melihat draft di daftar course'

$r = Invoke-Req 'GET' "guru/materials/$($ids.matAPub)" $guruA
Check ($r.Body -match 'Sudah dibuka\s+[1-9]\d*\s+siswa') 'Statistik guru: ada siswa yang sudah membuka materi (view tercatat)'

# ---------------------------------------------------------------- S1-S3

Section 'S1-S3: Guru A tidak bisa membuka / edit / hapus materi Guru B'

$r = Invoke-Req 'GET' "guru/materials/$($ids.matBPub)" $guruA
Check ($r.Status -eq 404) 'S1 Guru A buka materi Guru B -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "guru/materials/$($ids.matBPub)/edit" $guruA
Check ($r.Status -eq 404) 'S2 Guru A buka form edit materi Guru B -> 404' "(status $($r.Status))"

$r = Post-Form $guruA 'guru/dashboard' "guru/materials/$($ids.matBPub)/update" @{ course_id = $ids.courseB; title = '[UJI] Diubah paksa'; content = 'x'; is_published = '1' }
Check ($r.Status -eq 404) 'S2 Guru A POST update materi Guru B -> 404' "(status $($r.Status))"

$r = Post-Form $guruA 'guru/dashboard' "guru/materials/$($ids.matBPub)/delete"
Check ($r.Status -eq 404) 'S3 Guru A POST delete materi Guru B -> 404' "(status $($r.Status))"

$r = Post-Form $guruA 'guru/dashboard' "guru/materials/$($ids.matBPub)/toggle-publish"
Check ($r.Status -eq 404) 'S3 Guru A POST toggle-publish materi Guru B -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "guru/materials/$($ids.matBPub)/download" $guruA
Check ($r.Status -eq 404) 'S3 Guru A download file materi Guru B -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "guru/courses/$($ids.courseB)/materials" $guruA
Check ($r.Status -eq 404) 'S3 Guru A buka daftar materi course Guru B -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "guru/courses/$($ids.courseB)/materials/create" $guruA
Check ($r.Status -eq 404) 'S3 Guru A buka form tambah materi di course Guru B -> 404' "(status $($r.Status))"

$r = Post-Form $guruA 'guru/dashboard' "guru/courses/$($ids.courseB)/materials" @{ course_id = $ids.courseB; title = '[UJI] Seludup'; content = 'x'; is_published = '0' }
Check ($r.Status -eq 404 -and $null -eq (Get-Id '[UJI] Seludup')) 'S3 Guru A POST tambah materi ke course Guru B -> 404 dan tidak tersimpan' "(status $($r.Status))"

$r = Post-Form $guruA 'guru/dashboard' "guru/courses/$($ids.courseA)/materials" @{ course_id = $ids.courseB; title = '[UJI] Seludup 2'; content = 'x'; is_published = '0' }
Check ($r.Status -eq 404 -and $null -eq (Get-Id '[UJI] Seludup 2')) 'S3 Guru A memalsukan course_id ke course Guru B -> 404 dan tidak tersimpan' "(status $($r.Status))"

$r = Invoke-Req 'GET' "guru/materials/$($ids.matBPub)" $guruB
Check ($r.Status -eq 200 -and $r.Body.Contains($tBPub)) 'S3 Materi Guru B tetap utuh setelah semua percobaan Guru A'

$r = Invoke-Req 'GET' "guru/materials/$($ids.matAPub)" $guruB
Check ($r.Status -eq 404) 'Simetris: Guru B buka materi Guru A -> 404' "(status $($r.Status))"

# ---------------------------------------------------------------- S4-S6

Section 'S4-S6: Siswa hanya melihat materi published pada course yang diikuti'

$r = Invoke-Req 'GET' "siswa/materials/$($ids.matADraft)" $siswaA
Check ($r.Status -eq 404) 'S4 Siswa buka materi draft -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "siswa/materials/$($ids.matADraft)/download" $siswaA
Check ($r.Status -eq 404) 'S4 Siswa download materi draft -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "siswa/materials/$($ids.matBPub)" $siswaA
Check ($r.Status -eq 404) 'S5 Siswa A buka materi course yang tidak diikuti -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "siswa/courses/$($ids.courseB)" $siswaA
Check ($r.Status -eq 404) 'S5 Siswa A buka course yang tidak diikuti -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "siswa/materials/$($ids.matBPub)/download" $siswaA
Check ($r.Status -eq 404) 'S6 Siswa A download file course yang tidak diikuti -> 404' "(status $($r.Status))"

$r = Invoke-Req 'GET' "siswa/materials/$($ids.matAPub)/download" $siswaA
Check ($r.Status -eq 200 -and $r.Length -gt 0) 'S6 (kontrol) Siswa A download file course yang diikuti -> 200' "(status $($r.Status))"

$fileB = Get-File ([int]$ids.matBPub)
Check ($null -ne $fileB) 'File materi B tercatat di DB'
if ($null -ne $fileB) {
    $r = Invoke-Req 'GET' "uploads/materials/$fileB" $anon
    Check ($r.Status -eq 404) 'S6 Tidak ada link langsung: /uploads/materials/<file> -> 404' "(status $($r.Status))"
    $r = Invoke-Req 'GET' "writable/uploads/materials/$fileB" $anon
    Check ($r.Status -eq 404) 'S6 Tidak ada link langsung: /writable/uploads/materials/<file> -> 404' "(status $($r.Status))"
}

# ---------------------------------------------------------------- S7

Section 'S7: Belum login tidak bisa mengakses'

$anonPaths = @(
    "guru/materials/$($ids.matAPub)", "guru/materials/$($ids.matAPub)/download",
    "siswa/materials/$($ids.matAPub)", "siswa/materials/$($ids.matAPub)/download",
    'guru/courses', 'siswa/courses', "guru/courses/$($ids.courseA)/materials", "siswa/courses/$($ids.courseA)",
    'guru/dashboard', 'siswa/dashboard'
)
foreach ($p in $anonPaths) {
    $r = Invoke-Req 'GET' $p $anon
    Check (($r.Body -match 'type="password"') -and (-not $r.Body.Contains($tAPub))) "S7 Anonim GET $p -> halaman login"
}

# ---------------------------------------------------------------- S8-S9

Section 'S8-S9: Upload file berbahaya ditolak'

$formA   = "guru/courses/$($ids.courseA)/materials/create"
$postA   = "guru/courses/$($ids.courseA)/materials"
$baseFld = { param($title) @{ course_id = "$($ids.courseA)"; title = $title; description = ''; content = 'uji'; video_url = ''; is_published = '0' } }

$rejects = @(
    @{ T = '[UJI] Tolak 1'; File = 'shell.php';      Bytes = $phpCode;  Mime = 'application/x-php';           Msg = 'Tipe file tidak diizinkan' },
    @{ T = '[UJI] Tolak 2'; File = 'backdoor.phtml'; Bytes = $phpCode;  Mime = 'application/x-php';           Msg = 'Tipe file tidak diizinkan' },
    @{ T = '[UJI] Tolak 3'; File = 'tool.exe';       Bytes = $exeBytes; Mime = 'application/x-msdownload';    Msg = 'Tipe file tidak diizinkan' },
    @{ T = '[UJI] Tolak 4'; File = 'evil.html';      Bytes = $htmlCode; Mime = 'text/html';                   Msg = 'Tipe file tidak diizinkan' },
    @{ T = '[UJI] Tolak 5'; File = 'evil.js';        Bytes = $htmlCode; Mime = 'application/javascript';      Msg = 'Tipe file tidak diizinkan' },
    @{ T = '[UJI] Tolak 6'; File = 'shell.pdf.php';  Bytes = $phpCode;  Mime = 'application/pdf';             Msg = 'Tipe file tidak diizinkan' },
    @{ T = '[UJI] Tolak 7'; File = 'shell.pdf';      Bytes = $phpCode;  Mime = 'application/pdf';             Msg = 'Isi file tidak sesuai' },
    @{ T = '[UJI] Tolak 8'; File = 'shell.php.pdf';  Bytes = $phpCode;  Mime = 'application/pdf';             Msg = 'Isi file tidak sesuai' },
    @{ T = '[UJI] Tolak 9'; File = 'evil.pdf';       Bytes = $htmlCode; Mime = 'application/pdf';             Msg = 'Isi file tidak sesuai' },
    @{ T = '[UJI] Tolak 10'; File = 'palsu.docx';    Bytes = $phpCode;  Mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'; Msg = 'Isi file tidak sesuai' }
)

foreach ($c in $rejects) {
    $r = Send-Material $guruA $formA $postA (& $baseFld $c.T) $c.File $c.Bytes $c.Mime
    $msgOk = $r.Body -match [regex]::Escape($c.Msg)
    $saved = Get-Id $c.T
    Check ($msgOk -and $null -eq $saved) "S8/S9 Tolak $($c.File) (pesan: $($c.Msg)) dan tidak tersimpan" "(status $($r.Status))"
}

$big = New-Object byte[] (11 * 1024 * 1024)
$r   = Send-Material $guruA $formA $postA (& $baseFld '[UJI] Tolak Besar') 'besar.pdf' $big 'application/pdf'
Check (($r.Body -match 'maksimal 10 MB') -and $null -eq (Get-Id '[UJI] Tolak Besar')) 'Tolak file 11 MB dan tidak tersimpan' "(status $($r.Status); jika gagal, cek upload_max_filesize=10M dan post_max_size=12M di php.ini)"

$phpOnDisk = @(Get-ChildItem (Join-Path $Project 'writable\uploads\materials') -ErrorAction SilentlyContinue | Where-Object { $_.Name -match '\.(php|phtml|phar|exe|html|js)' })
Check ($phpOnDisk.Count -eq 0) 'S8 Tidak ada file php/phtml/exe/html/js di writable/uploads/materials'

# ---------------------------------------------------------------- upload valid + siklus file

Section 'Upload valid dan siklus file (simpan, ganti, hapus)'

$r = Send-Material $guruA $formA $postA (& $baseFld '[UJI] Lifecycle') 'Modul Uji.pdf' $pdfOk 'application/pdf'
$lifeId = Get-Id '[UJI] Lifecycle'
Check ($null -ne $lifeId) 'PDF valid diterima dan materi tersimpan' "(status $($r.Status))"

if ($null -ne $lifeId) {
    $file1 = Get-File $lifeId
    Check (($null -ne $file1) -and ($file1 -match '^[0-9a-f]{32}\.pdf$')) "Nama file di disk acak ($file1)"
    Check ($null -ne $file1 -and (Test-FileOnDisk $file1)) 'File ada di writable/uploads/materials'

    $r = Invoke-Req 'GET' "guru/materials/$lifeId" $guruA
    Check ($r.Body.Contains('Modul Uji.pdf')) 'Nama asli file ditampilkan di detail materi'

    $r = Invoke-Req 'GET' "guru/materials/$lifeId/download" $guruA
    Check ($r.Status -eq 200 -and $r.Length -gt 0) 'Guru pemilik dapat mengunduh file'

    $editForm = "guru/materials/$lifeId/edit"
    $upd      = "guru/materials/$lifeId/update"
    $fld      = & $baseFld '[UJI] Lifecycle'

    $r = Send-Material $guruA $editForm $upd $fld 'Pengganti.pdf' $pdfOk2 'application/pdf'
    $file2 = Get-File $lifeId
    Check (($null -ne $file2) -and ($file2 -ne $file1)) 'Ganti file: nama file baru berbeda' "(status $($r.Status))"
    Check ($null -ne $file1 -and -not (Test-FileOnDisk $file1)) 'Ganti file: file lama dihapus dari disk'
    Check ($null -ne $file2 -and (Test-FileOnDisk $file2)) 'Ganti file: file baru ada di disk'

    $r = Send-Material $guruA $editForm $upd $fld
    $file3 = Get-File $lifeId
    Check ($file3 -eq $file2 -and (Test-FileOnDisk $file2)) 'Edit tanpa file baru: file lama dipertahankan' "(status $($r.Status))"

    $r = Post-Form $guruA 'guru/dashboard' "guru/materials/$lifeId/delete"
    Check ($null -eq (Get-Id '[UJI] Lifecycle')) 'Hapus materi: record hilang' "(status $($r.Status))"
    Check ($null -ne $file2 -and -not (Test-FileOnDisk $file2)) 'Hapus materi: file ikut terhapus dari disk'
}

$fileDel = Get-File ([int]$ids.matDel)
Check ($null -ne $fileDel -and (Test-FileOnDisk $fileDel)) 'Sebelum hapus course: file materi course uji ada di disk'
$r = Post-Form $admin 'admin/dashboard' "admin/courses/$($ids.courseDel)/delete"
Check ($null -eq (Get-Id '[UJI] Hapus Course (file)')) 'Admin hapus course: record materi ikut terhapus (CASCADE)' "(status $($r.Status))"
Check ($null -ne $fileDel -and -not (Test-FileOnDisk $fileDel)) 'Admin hapus course: file materi dibersihkan dari disk'

# ---------------------------------------------------------------- S10

Section 'S10: CSRF aktif di semua POST (tanpa token harus ditolak)'

$noToken = @(
    "guru/materials/$($ids.matAPub)/delete",
    "guru/materials/$($ids.matAPub)/toggle-publish",
    "guru/materials/$($ids.matAPub)/update",
    "guru/courses/$($ids.courseA)/materials"
)
foreach ($p in $noToken) {
    $r = Invoke-Req 'POST' $p $guruA @{ title = '[UJI] Tanpa Token'; course_id = "$($ids.courseA)"; content = 'x' }
    Check ($r.Status -ge 400) "S10 Guru POST tanpa token $p ditolak" "(status $($r.Status))"
}

$r = Invoke-Req 'POST' 'logout' $guruA @{ x = '1' }
Check ($r.Status -ge 400) 'S10 POST logout tanpa token ditolak' "(status $($r.Status))"
$r = Invoke-Req 'POST' 'login' $anon @{ username = 'guru.demo'; password = $Password }
Check ($r.Status -ge 400) 'S10 POST login tanpa token ditolak' "(status $($r.Status))"
$r = Invoke-Req 'POST' "admin/courses/$($ids.courseA)/delete" $admin @{ x = '1' }
Check ($r.Status -ge 400) 'S10 Admin POST hapus course tanpa token ditolak' "(status $($r.Status))"

$r = Invoke-Req 'GET' "guru/materials/$($ids.matAPub)" $guruA
Check ($r.Status -eq 200 -and $r.Body.Contains($tAPub) -and $null -eq (Get-Id '[UJI] Tanpa Token')) 'S10 Materi tetap utuh dan tidak ada data baru dari POST tanpa token'

# ---------------------------------------------------------------- S11

Section 'S11: Semua output pengguna di-escape (XSS)'

foreach ($case in @(
    @{ Name = 'Guru detail materi'; Path = "guru/materials/$($ids.matXss)"; Session = $guruA },
    @{ Name = 'Guru daftar materi'; Path = "guru/courses/$($ids.courseA)/materials"; Session = $guruA },
    @{ Name = 'Siswa detail materi'; Path = "siswa/materials/$($ids.matXss)"; Session = $siswaA },
    @{ Name = 'Siswa detail course'; Path = "siswa/courses/$($ids.courseA)"; Session = $siswaA }
)) {
    $r = Invoke-Req 'GET' $case.Path $case.Session
    $safe = ($r.Status -eq 200) `
        -and (-not $r.Body.Contains('<script>alert(1)</script>')) `
        -and (-not $r.Body.Contains('<img src=x')) `
        -and (-not $r.Body.Contains('<b>tebal</b>')) `
        -and ($r.Body -match '&lt;script&gt;alert\(1\)')
    Check $safe "S11 $($case.Name): tag HTML tampil sebagai teks" "(status $($r.Status))"
}

# ---------------------------------------------------------------- S12

Section 'S12: ID acak / tidak valid -> 404, bukan error PHP'

$badGet = @('guru/materials/999999', 'guru/materials/999999/edit', 'guru/materials/999999/download', 'guru/materials/0', 'guru/materials/abc', 'guru/courses/999999/materials', 'guru/courses/abc/materials')
foreach ($p in $badGet) {
    $r = Invoke-Req 'GET' $p $guruA
    Check ($r.Status -eq 404 -and (Test-NoPhpError $r)) "S12 Guru GET $p -> 404" "(status $($r.Status))"
}

$badGetS = @('siswa/materials/999999', 'siswa/materials/999999/download', 'siswa/materials/0', 'siswa/materials/abc', 'siswa/courses/999999', 'siswa/courses/abc')
foreach ($p in $badGetS) {
    $r = Invoke-Req 'GET' $p $siswaA
    Check ($r.Status -eq 404 -and (Test-NoPhpError $r)) "S12 Siswa GET $p -> 404" "(status $($r.Status))"
}

foreach ($p in 'guru/materials/999999/update', 'guru/materials/999999/delete', 'guru/materials/999999/toggle-publish') {
    $r = Post-Form $guruA 'guru/dashboard' $p @{ title = 'x'; course_id = "$($ids.courseA)"; content = 'x' }
    Check ($r.Status -eq 404 -and (Test-NoPhpError $r)) "S12 Guru POST $p -> 404" "(status $($r.Status))"
}

# ---------------------------------------------------------------- lintas peran

Section 'Tambahan: pemisahan peran'

$r = Invoke-Req 'GET' "guru/materials/$($ids.matAPub)" $siswaA
Check ((-not $r.Body.Contains($tAPub)) -and ($r.Body -match 'Dashboard Siswa')) 'Siswa membuka URL guru -> dialihkan ke dashboard siswa, tanpa isi materi'
$r = Invoke-Req 'GET' "siswa/materials/$($ids.matAPub)" $guruA
Check ((-not $r.Body.Contains($tAPub)) -and ($r.Body -match 'Dashboard Guru')) 'Guru membuka URL siswa -> dialihkan ke dashboard guru, tanpa isi materi'
$r = Invoke-Req 'GET' "guru/materials/$($ids.matAPub)" $admin
Check (-not $r.Body.Contains($tAPub)) 'Admin membuka URL guru -> tidak melihat isi materi (opsional admin tidak diaktifkan)'

# ---------------------------------------------------------------- verifikasi akhir

Section 'Verifikasi disk dan database'
$v = Spark @('lentera:test-data', 'verify')
Write-Host $v
Check ($v -notmatch 'GAGAL') 'verify: duplikat view, file terlarang, file yatim, file hilang, nama acak'

Write-Host ''
Write-Host ("HASIL: {0} lulus, {1} gagal" -f $script:Pass, $script:Fail) -ForegroundColor $(if ($script:Fail -eq 0) { 'Green' } else { 'Red' })
if ($script:Fail -gt 0) { exit 1 }