<#
Pengujian keamanan otomatis LENTERA Phase 3 (STEP 3G).
Jalankan dengan Windows PowerShell 5.1 (powershell.exe), BUKAN pwsh 7.
PERINGATAN: skrip ini mengirim POST ke data milik guru lain. Jika otorisasi aplikasi rusak,
data itu benar-benar berubah atau terhapus. Pakai HANYA data uji khusus.
#>
param(
    [string]$BaseUrl = 'http://localhost:8080',
    [Parameter(Mandatory = $true)][string]$GuruUser,
    [Parameter(Mandatory = $true)][string]$GuruPass,
    [Parameter(Mandatory = $true)][string]$SiswaUser,
    [Parameter(Mandatory = $true)][string]$SiswaPass,
    [int]$OwnCourseId = 0,          # course milik GuruUser (untuk uji XSS)
    [int]$OtherCourseId = 0,        # course milik guru LAIN
    [int]$OtherAssignmentId = 0,    # tugas di course guru lain
    [int]$OtherSubmissionId = 0,    # pengumpulan pada tugas guru lain
    [int]$DraftAssignmentId = 0,    # tugas DRAFT di course yang diikuti SiswaUser
    [int]$NotEnrolledCourseId = 0   # course yang TIDAK diikuti SiswaUser
)

if ($PSVersionTable.PSVersion.Major -ge 7) {
    throw 'Jalankan dengan Windows PowerShell 5.1 (powershell.exe), bukan PowerShell 7.'
}

$BaseUrl = $BaseUrl.TrimEnd('/')
$script:pass = 0
$script:fail = 0
$script:skip = 0

function Req([string]$Method, [string]$Url, $Session, $Body = $null) {
    $p = @{ Uri = $Url; Method = $Method; WebSession = $Session; UseBasicParsing = $true; MaximumRedirection = 0; ErrorAction = 'Stop' }
    if ($null -ne $Body) { $p.Body = $Body }

    try {
        $r = Invoke-WebRequest @p
        return [pscustomobject]@{ Status = [int]$r.StatusCode; Body = [string]$r.Content; Location = [string]$r.Headers['Location'] }
    } catch {
        $resp = $_.Exception.Response
        if ($null -eq $resp) { throw }

        $body = ''
        try { $sr = New-Object System.IO.StreamReader($resp.GetResponseStream()); $body = $sr.ReadToEnd(); $sr.Close() } catch {}

        $loc = ''
        try { $loc = [string]$resp.Headers['Location'] } catch {}

        return [pscustomobject]@{ Status = [int]$resp.StatusCode; Body = $body; Location = $loc }
    }
}

function Get-CsrfFrom([string]$Html) {
    if ($Html -match 'name="(csrf_[^"]+)"\s+value="([^"]+)"') {
        return @{ Name = $Matches[1]; Value = $Matches[2] }
    }

    return $null
}

function New-Login([string]$User, [string]$Pass, [string]$Role) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Req 'GET' "$BaseUrl/login" $s
    $t = Get-CsrfFrom $r.Body
    if ($null -eq $t) { throw 'Token CSRF tidak ditemukan di halaman login.' }

    $body = @{ username = $User; password = $Pass }
    $body[$t.Name] = $t.Value

    $r = Req 'POST' "$BaseUrl/login" $s $body
    if ($r.Status -ne 302 -or $r.Location -notmatch "$Role/dashboard") {
        throw "Login $Role gagal (status $($r.Status), location '$($r.Location)'). Periksa username/password dan nama field form login (diasumsikan 'username' dan 'password')."
    }

    return $s
}

# POST dengan token CSRF segar (diambil dari dashboard role yang sama).
function Post-Auth($Session, [string]$Role, [string]$Path, [hashtable]$Fields = @{}) {
    $page = Req 'GET' "$BaseUrl/$Role/dashboard" $Session
    $t = Get-CsrfFrom $page.Body
    if ($null -eq $t) { throw "Token CSRF tidak ditemukan di $Role/dashboard." }

    $body = @{}
    foreach ($k in $Fields.Keys) { $body[$k] = $Fields[$k] }
    $body[$t.Name] = $t.Value

    return Req 'POST' "$BaseUrl/$Path" $Session $body
}

function Check([string]$Name, [bool]$Ok, [string]$Detail = '') {
    if ($Ok) {
        $script:pass++
        Write-Host "  [LOLOS]  $Name" -ForegroundColor Green
    } else {
        $script:fail++
        Write-Host "  [GAGAL]  $Name  $Detail" -ForegroundColor Red
    }
}

function Skip([string]$Name, [string]$Why) {
    $script:skip++
    Write-Host "  [LEWAT]  $Name ($Why)" -ForegroundColor DarkYellow
}

function Expect404([string]$Name, $Resp) {
    Check $Name ($Resp.Status -eq 404) "(status $($Resp.Status), harusnya 404)"
}

function ExpectNot200([string]$Name, $Resp) {
    Check $Name ($Resp.Status -ne 200) "(status $($Resp.Status), harusnya bukan 200)"
}

Write-Host "`nLENTERA - uji keamanan Phase 3 ($BaseUrl)" -ForegroundColor Yellow

$guru  = New-Login $GuruUser  $GuruPass  'guru'
$siswa = New-Login $SiswaUser $SiswaPass 'siswa'
$anon  = New-Object Microsoft.PowerShell.Commands.WebRequestSession

# ---------------------------------------------------------------
Write-Host "`n1. Tanpa login" -ForegroundColor Cyan
foreach ($p in @('guru/dashboard', 'guru/courses', 'siswa/dashboard', 'siswa/assignments/1', 'siswa/assignments/1/download', 'guru/assignments/1/download', 'guru/submissions/1/download')) {
    ExpectNot200 "GET /$p tanpa login ditolak" (Req 'GET' "$BaseUrl/$p" $anon)
}

# ---------------------------------------------------------------
Write-Host "`n2. Lintas role" -ForegroundColor Cyan
ExpectNot200 'Guru membuka dashboard siswa' (Req 'GET' "$BaseUrl/siswa/dashboard" $guru)
ExpectNot200 'Siswa membuka dashboard guru' (Req 'GET' "$BaseUrl/guru/dashboard" $siswa)
ExpectNot200 'Siswa membuka daftar course guru' (Req 'GET' "$BaseUrl/guru/courses" $siswa)

if ($OtherSubmissionId -gt 0) {
    $r = Post-Auth $siswa 'siswa' "guru/submissions/$OtherSubmissionId/grade" @{ score = '100'; feedback = 'uji 3G' }
    Check 'Siswa tidak dapat menilai pengumpulan' ($r.Status -in 302, 401, 403, 404 -and $r.Location -notmatch 'guru/submissions') "(status $($r.Status), location '$($r.Location)')"
} else { Skip 'Siswa mencoba menilai pengumpulan' 'OtherSubmissionId tidak diisi' }

# ---------------------------------------------------------------
Write-Host "`n3. Guru mengakses milik guru lain" -ForegroundColor Cyan
if ($OtherAssignmentId -gt 0) {
    Expect404 'GET detail tugas guru lain'    (Req 'GET' "$BaseUrl/guru/assignments/$OtherAssignmentId" $guru)
    Expect404 'GET edit tugas guru lain'      (Req 'GET' "$BaseUrl/guru/assignments/$OtherAssignmentId/edit" $guru)
    Expect404 'GET download lampiran guru lain' (Req 'GET' "$BaseUrl/guru/assignments/$OtherAssignmentId/download" $guru)
    Expect404 'POST update tugas guru lain'   (Post-Auth $guru 'guru' "guru/assignments/$OtherAssignmentId/update" @{ title = 'DIUBAH-3G'; description = 'x'; is_published = '1'; due_at = '' })
    Expect404 'POST toggle-publish tugas guru lain' (Post-Auth $guru 'guru' "guru/assignments/$OtherAssignmentId/toggle-publish")
    Expect404 'POST hapus tugas guru lain'    (Post-Auth $guru 'guru' "guru/assignments/$OtherAssignmentId/delete")
} else { Skip 'Uji tugas guru lain' 'OtherAssignmentId tidak diisi' }

if ($OtherCourseId -gt 0) {
    Expect404 'GET daftar tugas course guru lain' (Req 'GET' "$BaseUrl/guru/courses/$OtherCourseId/assignments" $guru)
    Expect404 'GET form tambah tugas di course guru lain' (Req 'GET' "$BaseUrl/guru/courses/$OtherCourseId/assignments/create" $guru)
    Expect404 'POST tambah tugas di course guru lain' (Post-Auth $guru 'guru' "guru/courses/$OtherCourseId/assignments" @{ course_id = "$OtherCourseId"; title = 'SUSUPAN-3G'; description = ''; is_published = '0'; due_at = '' })
} else { Skip 'Uji course guru lain' 'OtherCourseId tidak diisi' }

if ($OtherSubmissionId -gt 0) {
    Expect404 'GET pengumpulan pada tugas guru lain' (Req 'GET' "$BaseUrl/guru/submissions/$OtherSubmissionId" $guru)
    Expect404 'GET download file jawaban guru lain'  (Req 'GET' "$BaseUrl/guru/submissions/$OtherSubmissionId/download" $guru)
    Expect404 'POST nilai pengumpulan guru lain'     (Post-Auth $guru 'guru' "guru/submissions/$OtherSubmissionId/grade" @{ score = '100'; feedback = 'uji 3G' })
} else { Skip 'Uji pengumpulan guru lain' 'OtherSubmissionId tidak diisi' }

# ---------------------------------------------------------------
Write-Host "`n4. Siswa: draft dan course yang tidak diikuti" -ForegroundColor Cyan
if ($DraftAssignmentId -gt 0) {
    Expect404 'GET tugas draft'           (Req 'GET' "$BaseUrl/siswa/assignments/$DraftAssignmentId" $siswa)
    Expect404 'GET download lampiran draft' (Req 'GET' "$BaseUrl/siswa/assignments/$DraftAssignmentId/download" $siswa)
    Expect404 'POST kumpul jawaban ke tugas draft' (Post-Auth $siswa 'siswa' "siswa/assignments/$DraftAssignmentId/submit" @{ answer_text = 'uji 3G' })
} else { Skip 'Uji tugas draft' 'DraftAssignmentId tidak diisi' }

if ($NotEnrolledCourseId -gt 0) {
    Expect404 'GET daftar tugas course yang tidak diikuti' (Req 'GET' "$BaseUrl/siswa/courses/$NotEnrolledCourseId/assignments" $siswa)
} else { Skip 'Uji course tidak diikuti' 'NotEnrolledCourseId tidak diisi' }

if ($OtherAssignmentId -gt 0) {
    Expect404 'Siswa membuka tugas di course yang tidak diikutinya' (Req 'GET' "$BaseUrl/siswa/assignments/$OtherAssignmentId" $siswa)
}

# ---------------------------------------------------------------
Write-Host "`n5. ID acak dan tidak ada" -ForegroundColor Cyan
Expect404 'Guru: tugas 999999'      (Req 'GET' "$BaseUrl/guru/assignments/999999" $guru)
Expect404 'Guru: pengumpulan 999999' (Req 'GET' "$BaseUrl/guru/submissions/999999" $guru)
Expect404 'Guru: course 999999'     (Req 'GET' "$BaseUrl/guru/courses/999999/assignments" $guru)
Expect404 'Siswa: tugas 999999'     (Req 'GET' "$BaseUrl/siswa/assignments/999999" $siswa)
Expect404 'Siswa: download 999999'  (Req 'GET' "$BaseUrl/siswa/assignments/999999/download" $siswa)
Expect404 'Siswa: file jawaban 999999' (Req 'GET' "$BaseUrl/siswa/assignments/999999/submission/download" $siswa)

# ---------------------------------------------------------------
Write-Host "`n6. CSRF" -ForegroundColor Cyan
$r = Req 'POST' "$BaseUrl/guru/assignments/999999/toggle-publish" $guru @{ x = '1' }
Check 'POST guru tanpa token CSRF ditolak (4xx, bukan 404 karena rute)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"
$r = Req 'POST' "$BaseUrl/siswa/assignments/999999/submit" $siswa @{ answer_text = 'x' }
Check 'POST siswa tanpa token CSRF ditolak (4xx)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"
$r = Req 'POST' "$BaseUrl/guru/assignments/999999/delete" $guru @{ csrf_test_name = 'tokenpalsu123' }
Check 'POST dengan token CSRF palsu ditolak (4xx)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"

# ---------------------------------------------------------------
Write-Host "`n7. XSS (data uji dibuat lalu dihapus)" -ForegroundColor Cyan
if ($OwnCourseId -gt 0) {
    $payloadTitle = '<script>alert(1)</script>3GXSS'
    $payloadDesc  = '<img src=x onerror=alert(1)> 3GXSS'
    $newId = $null

    try {
        $r = Post-Auth $guru 'guru' "guru/courses/$OwnCourseId/assignments" @{ course_id = "$OwnCourseId"; title = $payloadTitle; description = $payloadDesc; is_published = '0'; due_at = '' }
        Check 'Tugas berpayload XSS berhasil dibuat sebagai data uji' ($r.Status -eq 302) "(status $($r.Status))"

        $list = Req 'GET' "$BaseUrl/guru/courses/$OwnCourseId/assignments" $guru
        Check 'Daftar tugas tidak memuat tag script mentah' ($list.Body -notmatch '<script>alert\(1\)</script>3GXSS')
        Check 'Daftar tugas memuat versi ter-escape' ($list.Body -match '&lt;script&gt;alert\(1\)&lt;/script&gt;3GXSS')

        if ($list.Body -match 'guru/assignments/(\d+)"[^>]*>\s*&lt;script&gt;alert\(1\)') { $newId = [int]$Matches[1] }

        if ($null -ne $newId) {
            $show = Req 'GET' "$BaseUrl/guru/assignments/$newId" $guru
            Check 'Detail tugas: judul ter-escape' ($show.Body -notmatch '<script>alert\(1\)</script>3GXSS')
            Check 'Detail tugas: deskripsi ter-escape (tidak ada tag img mentah)' ($show.Body -notmatch '<img src=x onerror')
        } else {
            Skip 'Detail tugas XSS' 'ID tugas uji tidak ditemukan di daftar'
        }
    } finally {
        if ($null -ne $newId) {
            $d = Post-Auth $guru 'guru' "guru/assignments/$newId/delete"
            Write-Host "  (data uji XSS id $newId dihapus, status $($d.Status))" -ForegroundColor DarkGray
        } else {
            Write-Host '  (PERIKSA: tugas berjudul "3GXSS" mungkin tertinggal; hapus manual)' -ForegroundColor DarkYellow
        }
    }
} else { Skip 'Uji XSS' 'OwnCourseId tidak diisi' }

# ---------------------------------------------------------------
Write-Host ''
$color = if ($script:fail -eq 0) { 'Green' } else { 'Red' }
Write-Host "Hasil: $($script:pass) lolos, $($script:fail) gagal, $($script:skip) dilewati." -ForegroundColor $color

if ($script:fail -gt 0) { exit 1 }