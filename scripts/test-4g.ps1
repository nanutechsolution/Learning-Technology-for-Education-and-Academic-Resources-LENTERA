<#
Pengujian keamanan otomatis LENTERA Phase 4 (STEP 4G): quiz.
Jalankan dengan Windows PowerShell 5.1 (powershell.exe), BUKAN pwsh 7.
Waktu jalan sekitar 90 detik (ada uji batas waktu yang menunggu 75 detik).

PERINGATAN:
- Skrip membuat beberapa quiz uji di course OwnCourseId (milik GuruUser, SiswaUser harus terdaftar di sana)
  dan menghapusnya di akhir. SiswaUser akan membuat attempt pada quiz uji tersebut.
- Jika OtherQuizId, OtherQuestionId, OtherCourseId diisi, skrip mengirim POST ke data milik guru LAIN.
  Jika otorisasi aplikasi rusak, data itu benar-benar berubah atau terhapus. Pakai HANYA data uji khusus.
- Jam komputer yang menjalankan skrip harus sama dengan jam server (dipakai untuk jadwal quiz uji).
#>
param(
    [string]$BaseUrl = 'http://localhost:8080',
    [Parameter(Mandatory = $true)][string]$GuruUser,
    [Parameter(Mandatory = $true)][string]$GuruPass,
    [Parameter(Mandatory = $true)][string]$SiswaUser,
    [Parameter(Mandatory = $true)][string]$SiswaPass,
    [Parameter(Mandatory = $true)][int]$OwnCourseId,  # course milik GuruUser, SiswaUser terdaftar
    [int]$OtherCourseId = 0,        # course milik guru LAIN
    [int]$OtherQuizId = 0,          # quiz di course guru lain
    [int]$OtherQuestionId = 0,      # soal pada quiz guru lain
    [int]$OtherAttemptId = 0,       # attempt pada quiz guru lain
    [int]$NotEnrolledCourseId = 0,  # course yang TIDAK diikuti SiswaUser
    [int]$NotEnrolledQuizId = 0     # quiz PUBLISHED di course yang TIDAK diikuti SiswaUser
)

if ($PSVersionTable.PSVersion.Major -ge 7) {
    throw 'Jalankan dengan Windows PowerShell 5.1 (powershell.exe), bukan PowerShell 7.'
}

$BaseUrl = $BaseUrl.TrimEnd('/')
$script:pass = 0
$script:fail = 0
$script:skip = 0
$script:created = @()
# CodeIgniter 4 membalas redirect setelah POST dengan 303 (HTTP/1.1); GET memakai 302. Keduanya sah.
$script:Redir = @(302, 303)

function Req([string]$Method, [string]$Url, $Session, $Body = $null) {
    $p = @{ Uri = $Url; Method = $Method; WebSession = $Session; UseBasicParsing = $true; MaximumRedirection = 0; ErrorAction = 'Stop' }
    if ($null -ne $Body) { $p.Body = $Body }

    try {
        $r = Invoke-WebRequest @p
        return [pscustomobject]@{ Status = [int]$r.StatusCode; Body = [string]$r.Content; Location = [string]$r.Headers['Location'] }
    }
    catch {
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
    if (($r.Status -notin $script:Redir) -or $r.Location -notmatch "$Role/dashboard") {
        throw "Login $Role gagal (status $($r.Status), location '$($r.Location)'). Periksa username/password."
    }

    return $s
}

# POST dengan token CSRF segar. Token diambil dari halaman {role}/courses (bukan dashboard),
# karena dashboard siswa menyelesaikan otomatis attempt yang kedaluwarsa dan akan mengganggu uji batas waktu.
function Post-Auth($Session, [string]$Role, [string]$Path, [hashtable]$Fields = @{}) {
    $page = Req 'GET' "$BaseUrl/$Role/courses" $Session
    $t = Get-CsrfFrom $page.Body
    if ($null -eq $t) { throw "Token CSRF tidak ditemukan di $Role/courses." }

    $body = @{}
    foreach ($k in $Fields.Keys) { $body[$k] = $Fields[$k] }
    $body[$t.Name] = $t.Value

    return Req 'POST' "$BaseUrl/$Path" $Session $body
}

function Check([string]$Name, [bool]$Ok, [string]$Detail = '') {
    if ($Ok) {
        $script:pass++
        Write-Host "  [LOLOS]  $Name" -ForegroundColor Green
    }
    else {
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

# Redirect yang TIDAK menuju halaman pengerjaan.
function ExpectNoTake([string]$Name, $Resp) {
    Check $Name (($Resp.Status -in $script:Redir) -and $Resp.Location -notmatch '/take') "(status $($Resp.Status), location '$($Resp.Location)')"
}

function Get-QuizId($Resp) {
    if ($Resp.Location -match 'guru/quizzes/(\d+)') { return [int]$Matches[1] }

    return 0
}

# Peta [id soal] -> daftar id opsi dari halaman pengerjaan, berurutan.
function Get-Radios([string]$Html) {
    $h = [ordered]@{}
    foreach ($m in [regex]::Matches($Html, 'name="answers\[(\d+)\]"\s+id="[^"]*"\s+value="(\d+)"')) {
        $q = $m.Groups[1].Value
        if (-not $h.Contains($q)) { $h[$q] = @() }
        $h[$q] += $m.Groups[2].Value
    }

    return $h
}

# Buat quiz uji (draft) lengkap dengan soal; opsi A benar. Opsional dipublish.
function New-TestQuiz([string]$Title, [hashtable]$Extra, [array]$Questions, [bool]$Publish) {
    $f = @{ title = $Title; description = ''; duration_minutes = ''; start_at = ''; end_at = '' }
    foreach ($k in $Extra.Keys) { $f[$k] = $Extra[$k] }

    $r = Post-Auth $guru 'guru' "guru/courses/$OwnCourseId/quizzes" $f
    $id = Get-QuizId $r
    if ($id -le 0) {
        throw "Gagal membuat quiz uji '$Title' (status $($r.Status), location '$($r.Location)'). Periksa OwnCourseId dan akun guru."
    }
    $script:created += $id

    foreach ($q in $Questions) {
        $fields = @{ question_text = $q.Text; points = "$($q.Points)"; order_number = ''; 'options[0]' = 'Benar'; 'options[1]' = $q.Wrong; correct = '0' }
        $rq = Post-Auth $guru 'guru' "guru/quizzes/$id/questions" $fields
        if ($rq.Status -notin $script:Redir) { throw "Gagal menambah soal pada quiz uji $id (status $($rq.Status))." }
    }

    if ($Publish) {
        $rp = Post-Auth $guru 'guru' "guru/quizzes/$id/toggle-publish"
        if ($rp.Status -notin $script:Redir) { throw "Gagal mempublish quiz uji $id (status $($rp.Status))." }
    }

    return $id
}

$inv = [Globalization.CultureInfo]::InvariantCulture
$fmtT = 'yyyy-MM-dd\THH:mm'

Write-Host "`nLENTERA - uji keamanan Phase 4 ($BaseUrl)" -ForegroundColor Yellow

$guru = New-Login $GuruUser  $GuruPass  'guru'
$siswa = New-Login $SiswaUser $SiswaPass 'siswa'
$anon = New-Object Microsoft.PowerShell.Commands.WebRequestSession

try {
    # ---------------------------------------------------------------
    Write-Host "`n0. Menyiapkan data uji" -ForegroundColor Cyan

    $xssTitle = '<script>alert(1)</script>4GXSS'
    $xssQ1 = '<img src=x onerror=alert(1)> 4GXSS'
    $xssOpt = '<script>alert(2)</script>4GXSS-OPT'

    $q1 = New-TestQuiz -Title $xssTitle -Extra @{} -Publish $true -Questions @(
        @{ Text = $xssQ1; Points = 10; Wrong = 'Salah' },
        @{ Text = 'Soal dua 4GXSS'; Points = 10; Wrong = $xssOpt }
    )
    $draft = New-TestQuiz -Title 'UJI-4G-DRAFT' -Extra @{} -Publish $false -Questions @(
        @{ Text = 'Soal draft'; Points = 10; Wrong = 'Salah' }
    )
    $upcoming = New-TestQuiz -Title 'UJI-4G-BELUM-MULAI' -Publish $true `
        -Extra @{ start_at = (Get-Date).AddDays(2).ToString($fmtT, $inv) } `
        -Questions @(@{ Text = 'Soal belum mulai'; Points = 10; Wrong = 'Salah' })
    $closed = New-TestQuiz -Title 'UJI-4G-DITUTUP' -Publish $true `
        -Extra @{ start_at = (Get-Date).AddDays(-3).ToString($fmtT, $inv); end_at = (Get-Date).AddDays(-2).ToString($fmtT, $inv) } `
        -Questions @(@{ Text = 'Soal ditutup'; Points = 10; Wrong = 'Salah' })
    $timed = New-TestQuiz -Title 'UJI-4G-WAKTU' -Publish $true `
        -Extra @{ duration_minutes = '1' } `
        -Questions @(@{ Text = 'Soal waktu'; Points = 10; Wrong = 'Salah' })

    Write-Host "  quiz uji dibuat: $($script:created -join ', ')" -ForegroundColor DarkGray

    $probe = Req 'GET' "$BaseUrl/siswa/quizzes/$q1" $siswa
    if ($probe.Status -ne 200) {
        throw "SiswaUser tidak dapat membuka quiz uji (status $($probe.Status)). Pastikan SiswaUser terdaftar di course $OwnCourseId."
    }

    # Mulai quiz berdurasi sekarang; jam berjalan.
    $rs = Post-Auth $siswa 'siswa' "siswa/quizzes/$timed/start"
    Check 'Quiz berdurasi dapat dimulai' (($rs.Status -in $script:Redir) -and $rs.Location -match '/take') "(status $($rs.Status), location '$($rs.Location)')"
    $clock = [Diagnostics.Stopwatch]::StartNew()

    $tt = Req 'GET' "$BaseUrl/siswa/quizzes/$timed/take" $siswa
    Check 'Halaman pengerjaan quiz berdurasi memuat timer' ($tt.Status -eq 200 -and $tt.Body -match 'quizTimerText') "(status $($tt.Status))"
    $timedRadios = Get-Radios $tt.Body

    # ---------------------------------------------------------------
    Write-Host "`n1. Tanpa login" -ForegroundColor Cyan
    foreach ($p in @("siswa/quizzes/$q1", "siswa/quizzes/$q1/take", "siswa/courses/$OwnCourseId/quizzes", "guru/quizzes/$q1", "guru/quizzes/$q1/results", 'guru/attempts/1', "guru/courses/$OwnCourseId/quizzes")) {
        ExpectNot200 "GET /$p tanpa login ditolak" (Req 'GET' "$BaseUrl/$p" $anon)
    }

    # ---------------------------------------------------------------
    Write-Host "`n2. Lintas role" -ForegroundColor Cyan
    ExpectNot200 'Siswa membuka detail quiz guru'      (Req 'GET' "$BaseUrl/guru/quizzes/$q1" $siswa)
    ExpectNot200 'Siswa membuka hasil quiz guru'       (Req 'GET' "$BaseUrl/guru/quizzes/$q1/results" $siswa)
    ExpectNot200 'Siswa membuka daftar quiz guru'      (Req 'GET' "$BaseUrl/guru/courses/$OwnCourseId/quizzes" $siswa)
    ExpectNot200 'Siswa membuka form tambah soal'      (Req 'GET' "$BaseUrl/guru/quizzes/$q1/questions/create" $siswa)
    ExpectNot200 'Siswa membuka detail attempt guru'   (Req 'GET' "$BaseUrl/guru/attempts/1" $siswa)
    ExpectNot200 'Guru membuka halaman pengerjaan siswa' (Req 'GET' "$BaseUrl/siswa/quizzes/$q1/take" $guru)
    ExpectNot200 'Guru membuka detail quiz siswa'      (Req 'GET' "$BaseUrl/siswa/quizzes/$q1" $guru)

    $r = Post-Auth $siswa 'siswa' "guru/quizzes/$draft/toggle-publish"
    Check 'Siswa tidak dapat mempublish quiz' ($r.Status -ne 200 -and $r.Location -notmatch 'guru/quizzes') "(status $($r.Status), location '$($r.Location)')"
    $r = Post-Auth $siswa 'siswa' "guru/quizzes/$draft/delete"
    Check 'Siswa tidak dapat menghapus quiz' ($r.Status -ne 200 -and $r.Location -notmatch 'guru/courses') "(status $($r.Status), location '$($r.Location)')"
    $chk = Req 'GET' "$BaseUrl/guru/quizzes/$draft" $guru
    Check 'Quiz draft uji masih ada setelah percobaan siswa' ($chk.Status -eq 200) "(status $($chk.Status))"

    # ---------------------------------------------------------------
    Write-Host "`n3. Guru mengakses milik guru lain" -ForegroundColor Cyan
    if ($OtherQuizId -gt 0) {
        Expect404 'GET detail quiz guru lain'   (Req 'GET' "$BaseUrl/guru/quizzes/$OtherQuizId" $guru)
        Expect404 'GET edit quiz guru lain'     (Req 'GET' "$BaseUrl/guru/quizzes/$OtherQuizId/edit" $guru)
        Expect404 'GET hasil quiz guru lain'    (Req 'GET' "$BaseUrl/guru/quizzes/$OtherQuizId/results" $guru)
        Expect404 'GET form tambah soal di quiz guru lain' (Req 'GET' "$BaseUrl/guru/quizzes/$OtherQuizId/questions/create" $guru)
        Expect404 'POST update quiz guru lain'  (Post-Auth $guru 'guru' "guru/quizzes/$OtherQuizId/update" @{ title = 'DIUBAH-4G'; description = 'x'; duration_minutes = ''; start_at = ''; end_at = '' })
        Expect404 'POST toggle-publish quiz guru lain' (Post-Auth $guru 'guru' "guru/quizzes/$OtherQuizId/toggle-publish")
        Expect404 'POST hapus quiz guru lain'   (Post-Auth $guru 'guru' "guru/quizzes/$OtherQuizId/delete")
        Expect404 'POST tambah soal di quiz guru lain' (Post-Auth $guru 'guru' "guru/quizzes/$OtherQuizId/questions" @{ question_text = 'SUSUPAN-4G'; points = '10'; order_number = ''; 'options[0]' = 'a'; 'options[1]' = 'b'; correct = '0' })
    }
    else { Skip 'Uji quiz guru lain' 'OtherQuizId tidak diisi' }

    if ($OtherQuestionId -gt 0) {
        Expect404 'GET edit soal guru lain'  (Req 'GET' "$BaseUrl/guru/questions/$OtherQuestionId/edit" $guru)
        Expect404 'POST update soal guru lain' (Post-Auth $guru 'guru' "guru/questions/$OtherQuestionId/update" @{ question_text = 'DIUBAH-4G'; points = '10'; order_number = ''; 'options[0]' = 'a'; 'options[1]' = 'b'; correct = '0' })
        Expect404 'POST hapus soal guru lain'  (Post-Auth $guru 'guru' "guru/questions/$OtherQuestionId/delete")
    }
    else { Skip 'Uji soal guru lain' 'OtherQuestionId tidak diisi' }

    if ($OtherAttemptId -gt 0) {
        Expect404 'GET detail attempt pada quiz guru lain' (Req 'GET' "$BaseUrl/guru/attempts/$OtherAttemptId" $guru)
    }
    else { Skip 'Uji attempt guru lain' 'OtherAttemptId tidak diisi' }

    if ($OtherCourseId -gt 0) {
        Expect404 'GET daftar quiz course guru lain' (Req 'GET' "$BaseUrl/guru/courses/$OtherCourseId/quizzes" $guru)
        Expect404 'GET form tambah quiz di course guru lain' (Req 'GET' "$BaseUrl/guru/courses/$OtherCourseId/quizzes/create" $guru)
        Expect404 'POST tambah quiz di course guru lain' (Post-Auth $guru 'guru' "guru/courses/$OtherCourseId/quizzes" @{ title = 'SUSUPAN-4G'; description = ''; duration_minutes = ''; start_at = ''; end_at = '' })
    }
    else { Skip 'Uji course guru lain' 'OtherCourseId tidak diisi' }

    Expect404 'Guru: quiz 999999'     (Req 'GET' "$BaseUrl/guru/quizzes/999999" $guru)
    Expect404 'Guru: hasil 999999'    (Req 'GET' "$BaseUrl/guru/quizzes/999999/results" $guru)
    Expect404 'Guru: soal 999999'     (Req 'GET' "$BaseUrl/guru/questions/999999/edit" $guru)
    Expect404 'Guru: attempt 999999'  (Req 'GET' "$BaseUrl/guru/attempts/999999" $guru)
    Expect404 'Guru: course 999999'   (Req 'GET' "$BaseUrl/guru/courses/999999/quizzes" $guru)

    # ---------------------------------------------------------------
    Write-Host "`n4. Siswa: draft, course tidak diikuti, ID acak" -ForegroundColor Cyan
    Expect404 'GET quiz draft'        (Req 'GET' "$BaseUrl/siswa/quizzes/$draft" $siswa)
    Expect404 'GET pengerjaan quiz draft' (Req 'GET' "$BaseUrl/siswa/quizzes/$draft/take" $siswa)
    Expect404 'POST mulai quiz draft' (Post-Auth $siswa 'siswa' "siswa/quizzes/$draft/start")
    Expect404 'POST simpan sementara quiz draft' (Post-Auth $siswa 'siswa' "siswa/quizzes/$draft/save" @{ 'answers[1]' = '1' })
    Expect404 'POST kumpul quiz draft' (Post-Auth $siswa 'siswa' "siswa/quizzes/$draft/submit")

    $listBody = (Req 'GET' "$BaseUrl/siswa/courses/$OwnCourseId/quizzes" $siswa).Body
    Check 'Daftar quiz siswa tidak memuat quiz draft' ($listBody -notmatch 'UJI-4G-DRAFT')
    Check 'Daftar quiz siswa memuat quiz published' ($listBody -match 'UJI-4G-BELUM-MULAI')

    if ($NotEnrolledQuizId -gt 0) {
        Expect404 'GET quiz di course yang tidak diikuti' (Req 'GET' "$BaseUrl/siswa/quizzes/$NotEnrolledQuizId" $siswa)
        Expect404 'GET pengerjaan quiz course tidak diikuti' (Req 'GET' "$BaseUrl/siswa/quizzes/$NotEnrolledQuizId/take" $siswa)
        Expect404 'POST mulai quiz course tidak diikuti' (Post-Auth $siswa 'siswa' "siswa/quizzes/$NotEnrolledQuizId/start")
        Expect404 'POST kumpul quiz course tidak diikuti' (Post-Auth $siswa 'siswa' "siswa/quizzes/$NotEnrolledQuizId/submit")
    }
    else { Skip 'Uji quiz course tidak diikuti' 'NotEnrolledQuizId tidak diisi' }

    if ($NotEnrolledCourseId -gt 0) {
        Expect404 'GET daftar quiz course yang tidak diikuti' (Req 'GET' "$BaseUrl/siswa/courses/$NotEnrolledCourseId/quizzes" $siswa)
    }
    else { Skip 'Uji daftar quiz course tidak diikuti' 'NotEnrolledCourseId tidak diisi' }

    Expect404 'Siswa: quiz 999999'      (Req 'GET' "$BaseUrl/siswa/quizzes/999999" $siswa)
    Expect404 'Siswa: pengerjaan 999999' (Req 'GET' "$BaseUrl/siswa/quizzes/999999/take" $siswa)
    Expect404 'Siswa: mulai 999999'     (Post-Auth $siswa 'siswa' 'siswa/quizzes/999999/start')
    Expect404 'Siswa: course 999999'    (Req 'GET' "$BaseUrl/siswa/courses/999999/quizzes" $siswa)

    # ---------------------------------------------------------------
    Write-Host "`n5. Jadwal: belum mulai dan sudah ditutup" -ForegroundColor Cyan
    foreach ($case in @(@{ Id = $upcoming; Label = 'belum mulai' }, @{ Id = $closed; Label = 'sudah ditutup' })) {
        $id = $case.Id
        $lb = $case.Label
        Check "Detail quiz $lb dapat dibuka (200)" ((Req 'GET' "$BaseUrl/siswa/quizzes/$id" $siswa).Status -eq 200)
        ExpectNoTake "POST mulai quiz $lb ditolak" (Post-Auth $siswa 'siswa' "siswa/quizzes/$id/start")
        ExpectNoTake "GET pengerjaan quiz $lb diarahkan kembali" (Req 'GET' "$BaseUrl/siswa/quizzes/$id/take" $siswa)
        ExpectNoTake "POST kumpul quiz $lb tanpa mulai ditolak" (Post-Auth $siswa 'siswa' "siswa/quizzes/$id/submit" @{ 'answers[1]' = '1' })
    }

    # ---------------------------------------------------------------
    Write-Host "`n6. Alur pengerjaan, kunci jawaban, anti-ulang, nilai" -ForegroundColor Cyan
    ExpectNoTake 'GET pengerjaan sebelum mulai diarahkan ke detail' (Req 'GET' "$BaseUrl/siswa/quizzes/$q1/take" $siswa)

    $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/start"
    Check 'POST mulai quiz terbuka diarahkan ke pengerjaan' (($r.Status -in $script:Redir) -and $r.Location -match '/take') "(status $($r.Status), location '$($r.Location)')"
    $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/start"
    Check 'POST mulai kedua tetap ke attempt yang sama (tidak membuat attempt baru)' (($r.Status -in $script:Redir) -and $r.Location -match '/take') "(status $($r.Status), location '$($r.Location)')"

    $take = Req 'GET' "$BaseUrl/siswa/quizzes/$q1/take" $siswa
    Check 'Halaman pengerjaan dapat dibuka (200)' ($take.Status -eq 200) "(status $($take.Status))"
    $takeBody = $take.Body
    Check 'Halaman pengerjaan tidak memuat is_correct' ($takeBody -notmatch 'is_correct')
    Check 'Halaman pengerjaan tidak memuat penanda jawaban benar' ($takeBody -notmatch 'bi-check-circle-fill' -and $takeBody -notmatch 'text-success fw-semibold')

    $radios = Get-Radios $takeBody
    $keys = @($radios.Keys)
    Check 'Halaman pengerjaan memuat 2 soal dengan minimal 2 opsi' ($keys.Count -eq 2 -and $radios[$keys[0]].Count -ge 2 -and $radios[$keys[1]].Count -ge 2) "(soal terbaca: $($keys.Count))"

    if ($keys.Count -eq 2) {
        $k1 = $keys[0]
        $k2 = $keys[1]
        $foreign = $radios[$k2][0]

        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/save" @{ "answers[$k1]" = $foreign }
        Check 'Simpan sementara dengan opsi milik soal lain diproses tanpa error' ($r.Status -in $script:Redir) "(status $($r.Status))"
        $t2 = Req 'GET' "$BaseUrl/siswa/quizzes/$q1/take" $siswa
        Check 'Opsi milik soal lain tidak tersimpan sebagai jawaban' ($t2.Body -notmatch ('value="' + $foreign + '"\s*checked'))

        $good = @{ "answers[$k1]" = $radios[$k1][0]; "answers[$k2]" = $radios[$k2][1] }  # soal 1 benar (A), soal 2 salah (B)
        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/save" $good
        Check 'Simpan sementara berhasil' (($r.Status -in $script:Redir) -and $r.Location -match '/take') "(status $($r.Status), location '$($r.Location)')"
        $t3 = Req 'GET' "$BaseUrl/siswa/quizzes/$q1/take" $siswa
        Check 'Jawaban sementara tampil kembali (checked)' ($t3.Status -eq 200 -and $t3.Body -match 'checked')

        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/submit" $good
        ExpectNoTake 'Kumpulkan quiz berhasil (kembali ke detail)' $r

        $show = Req 'GET' "$BaseUrl/siswa/quizzes/$q1" $siswa
        $showBody = $show.Body
        Check 'Nilai otomatis 50,00 tampil' ($show.Status -eq 200 -and $showBody -match '50,00') "(status $($show.Status))"
        Check 'Poin diperoleh 10 dari 20 tampil' ($showBody -match 'Poin diperoleh: 10 dari 20')

        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/submit" @{ "answers[$k1]" = $radios[$k1][1]; "answers[$k2]" = $radios[$k2][0] }
        ExpectNoTake 'Kumpulkan kedua kali ditolak' $r
        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/save" @{ "answers[$k1]" = $radios[$k1][1] }
        ExpectNoTake 'Simpan sementara setelah kumpul ditolak' $r
        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$q1/start"
        ExpectNoTake 'Mulai ulang setelah kumpul ditolak (tanpa retake)' $r
        ExpectNoTake 'GET pengerjaan setelah kumpul diarahkan ke detail' (Req 'GET' "$BaseUrl/siswa/quizzes/$q1/take" $siswa)

        $show2 = Req 'GET' "$BaseUrl/siswa/quizzes/$q1" $siswa
        Check 'Nilai tetap 50,00 setelah percobaan ulang' ($show2.Body -match '50,00' -and $show2.Body -match 'Poin diperoleh: 10 dari 20')

        $res = Req 'GET' "$BaseUrl/guru/quizzes/$q1/results" $guru
        Check 'Guru melihat hasil dengan nilai 50,00' ($res.Status -eq 200 -and $res.Body -match '50,00') "(status $($res.Status))"
    }
    else {
        Skip 'Alur pengerjaan' 'soal tidak terbaca dari halaman pengerjaan'
        $showBody = ''
    }

    # ---------------------------------------------------------------
    Write-Host "`n7. XSS" -ForegroundColor Cyan
    $guruShow = (Req 'GET' "$BaseUrl/guru/quizzes/$q1" $guru).Body
    $escTitle = '&lt;script&gt;alert\(1\)&lt;/script&gt;4GXSS'
    $escQ1 = '&lt;img src=x onerror=alert\(1\)&gt; 4GXSS'
    $escOpt = '&lt;script&gt;alert\(2\)&lt;/script&gt;4GXSS-OPT'

    Check 'Halaman pengerjaan: judul ter-escape' ($takeBody -notmatch '<script>alert\(1\)</script>4GXSS' -and $takeBody -match $escTitle)
    Check 'Halaman pengerjaan: teks soal dan opsi ter-escape' ($takeBody -notmatch '<img src=x onerror' -and $takeBody -notmatch '<script>alert\(2\)</script>' -and $takeBody -match $escQ1 -and $takeBody -match $escOpt)
    if ($showBody -ne '') {
        Check 'Detail siswa (hasil dan pembahasan): tidak ada tag mentah' ($showBody -notmatch '<script>alert\(1\)</script>4GXSS' -and $showBody -notmatch '<img src=x onerror' -and $showBody -notmatch '<script>alert\(2\)</script>')
        Check 'Detail siswa: versi ter-escape tampil' ($showBody -match $escTitle -and $showBody -match $escQ1)
    }
    Check 'Detail guru: tidak ada tag mentah' ($guruShow -notmatch '<script>alert\(1\)</script>4GXSS' -and $guruShow -notmatch '<img src=x onerror' -and $guruShow -notmatch '<script>alert\(2\)</script>')
    Check 'Detail guru: versi ter-escape tampil' ($guruShow -match $escTitle -and $guruShow -match $escQ1)
    $listG = (Req 'GET' "$BaseUrl/guru/courses/$OwnCourseId/quizzes" $guru).Body
    Check 'Daftar quiz guru: judul ter-escape' ($listG -notmatch '<script>alert\(1\)</script>4GXSS' -and $listG -match $escTitle)

    # ---------------------------------------------------------------
    Write-Host "`n8. Penguncian setelah ada attempt (guru)" -ForegroundColor Cyan
    $r = Post-Auth $guru 'guru' "guru/quizzes/$q1/questions" @{ question_text = 'SOAL-BARU-4G'; points = '10'; order_number = ''; 'options[0]' = 'a'; 'options[1]' = 'b'; correct = '0' }
    Check 'POST tambah soal pada quiz terkunci diproses (redirect)' ($r.Status -in $script:Redir) "(status $($r.Status))"
    $gs = Req 'GET' "$BaseUrl/guru/quizzes/$q1" $guru
    Check 'Soal baru tidak tersimpan dan pesan penolakan tampil' ($gs.Body -notmatch 'SOAL-BARU-4G' -and $gs.Body -match 'tidak dapat ditambah atau diubah')

    $qIds = @([regex]::Matches($guruShow, 'guru/questions/(\d+)/edit') | ForEach-Object { $_.Groups[1].Value })
    if ($qIds.Count -gt 0) {
        $r = Post-Auth $guru 'guru' "guru/questions/$($qIds[0])/delete"
        Check 'POST hapus soal pada quiz terkunci diproses (redirect)' ($r.Status -in $script:Redir) "(status $($r.Status))"
        $gs = Req 'GET' "$BaseUrl/guru/quizzes/$q1" $guru
        Check 'Soal tetap ada setelah percobaan hapus' ($gs.Body -match $escQ1)
        $r = Post-Auth $guru 'guru' "guru/questions/$($qIds[0])/update" @{ question_text = 'SOAL-UBAH-4G'; points = '10'; order_number = ''; 'options[0]' = 'a'; 'options[1]' = 'b'; correct = '0' }
        $gs = Req 'GET' "$BaseUrl/guru/quizzes/$q1" $guru
        Check 'Soal tidak berubah setelah percobaan ubah' ($gs.Body -notmatch 'SOAL-UBAH-4G' -and $gs.Body -match $escQ1)
    }
    else { Skip 'Uji hapus dan ubah soal terkunci' 'ID soal tidak terbaca dari halaman detail' }

    $r = Post-Auth $guru 'guru' "guru/quizzes/$q1/update" @{ title = $xssTitle; description = ''; duration_minutes = '999'; start_at = ''; end_at = '' }
    Check 'POST ubah durasi pada quiz terkunci diproses (redirect)' ($r.Status -in $script:Redir) "(status $($r.Status))"
    $gs = Req 'GET' "$BaseUrl/guru/quizzes/$q1" $guru
    Check 'Durasi tidak berubah (tetap Bebas)' ($gs.Body -match 'Bebas' -and $gs.Body -notmatch '999 mnt')

    # ---------------------------------------------------------------
    Write-Host "`n9. CSRF" -ForegroundColor Cyan
    $r = Req 'POST' "$BaseUrl/siswa/quizzes/$closed/start" $siswa @{ x = '1' }
    Check 'POST siswa mulai quiz tanpa token CSRF ditolak (4xx)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"
    $r = Req 'POST' "$BaseUrl/siswa/quizzes/$timed/submit" $siswa @{ x = '1' }
    Check 'POST siswa kumpul quiz tanpa token CSRF ditolak (4xx)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"
    $r = Req 'POST' "$BaseUrl/guru/quizzes/$draft/toggle-publish" $guru @{ x = '1' }
    Check 'POST guru publish tanpa token CSRF ditolak (4xx)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"
    $r = Req 'POST' "$BaseUrl/guru/quizzes/$draft/delete" $guru @{ csrf_test_name = 'tokenpalsu123' }
    Check 'POST guru hapus dengan token CSRF palsu ditolak (4xx)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"
    $r = Req 'POST' "$BaseUrl/guru/quizzes/$draft/questions" $guru @{ question_text = 'TANPA-TOKEN-4G'; points = '10' }
    Check 'POST guru tambah soal tanpa token CSRF ditolak (4xx)' ($r.Status -ge 400 -and $r.Status -lt 500) "(status $($r.Status))"
    $chk = Req 'GET' "$BaseUrl/guru/quizzes/$draft" $guru
    Check 'Quiz draft uji masih ada dan tidak berubah setelah uji CSRF' ($chk.Status -eq 200 -and $chk.Body -match 'Draft' -and $chk.Body -notmatch 'TANPA-TOKEN-4G')

    # ---------------------------------------------------------------
    Write-Host "`n10. Batas waktu (menunggu sampai 75 detik sejak quiz berdurasi dimulai)" -ForegroundColor Cyan
    while ($clock.Elapsed.TotalSeconds -lt 75) { Start-Sleep -Seconds 2 }

    if ($timedRadios.Count -ge 1) {
        $tk = @($timedRadios.Keys)[0]
        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$timed/submit" @{ "answers[$tk]" = $timedRadios[$tk][0] }
        ExpectNoTake 'Kumpul setelah batas waktu (lewat kelonggaran) diarahkan ke detail' $r

        $ts = Req 'GET' "$BaseUrl/siswa/quizzes/$timed" $siswa
        Check 'Pesan waktu habis tampil' ($ts.Body -match 'Waktu pengerjaan telah habis')
        Check 'Jawaban terlambat diabaikan: nilai 0 (0 dari 10 poin)' ($ts.Body -match 'Poin diperoleh: 0 dari 10') '(jawaban yang dikirim setelah batas waktu ikut dinilai)'
        Check 'Status quiz menjadi Selesai' ($ts.Body -match 'Hasil Quiz')
        ExpectNoTake 'GET pengerjaan setelah waktu habis diarahkan ke detail' (Req 'GET' "$BaseUrl/siswa/quizzes/$timed/take" $siswa)
        $r = Post-Auth $siswa 'siswa' "siswa/quizzes/$timed/save" @{ "answers[$tk]" = $timedRadios[$tk][0] }
        ExpectNoTake 'Simpan sementara setelah waktu habis ditolak' $r
    }
    else {
        Skip 'Uji kumpul setelah batas waktu' 'opsi quiz berdurasi tidak terbaca'
    }
}
finally {
    Write-Host "`nMembersihkan data uji..." -ForegroundColor DarkGray
    foreach ($id in $script:created) {
        try {
            $d = Post-Auth $guru 'guru' "guru/quizzes/$id/delete"
            Write-Host "  quiz uji $id dihapus (status $($d.Status))" -ForegroundColor DarkGray
        }
        catch {
            Write-Host "  PERIKSA: quiz uji $id mungkin tertinggal; hapus manual (judul berawalan UJI-4G atau berisi 4GXSS)." -ForegroundColor DarkYellow
        }
    }
}

Write-Host ''
$color = if ($script:fail -eq 0) { 'Green' } else { 'Red' }
Write-Host "Hasil: $($script:pass) lolos, $($script:fail) gagal, $($script:skip) dilewati." -ForegroundColor $color

if ($script:fail -gt 0) { exit 1 }