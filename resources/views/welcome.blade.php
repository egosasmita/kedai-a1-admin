<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8f5e9">
    <title>Masuk - Kedai A1</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=Playfair+Display:wght@400;500;600&display=swap" rel="stylesheet">
    @vite('resources/css/welcome.css')
</head>
<body>
    <main class="login-page">
        <section class="story-panel" aria-label="Cerita Kedai A1">
            <div class="story-top">
                <span class="established">EKO.NGANJUK — 2039</span>
            </div>
            <div class="story-copy">
                <h1>Setiap angka punya cerita,<br>setiap cangkir punya rasa.</h1>
                <p>Kelola menu, tim, persediaan, dan hari-hari baik Kedai A1 dalam satu ruang kerja yang hangat.</p>
                <div class="seal" aria-label="Kedai Kopi A1">
                    <span>A1</span>
                    <span>KEDAI KOPI</span>
                </div>
            </div>
        </section>

        <section class="form-panel" aria-label="Masuk portal Kedai A1">
            <a class="brand" href="/" aria-label="Portal Kedai A1">
                <img src="{{ asset('logokedaia1.png') }}" alt="">
                <span class="brand-name">Kedai A1<small>PORTAL OPERASIONAL</small></span>
            </a>

            <div class="login-content">
                <p class="eyebrow">PORTAL KEDAI A1</p>
                <h2>Selamat datang<br>kembali.<span class="sparkle" aria-hidden="true">✳</span></h2>
                <p class="intro">Masuk menggunakan akun admin atau karyawan untuk melanjutkan aktivitas Kedai A1.</p>

                <form action="{{ route('login.store') }}" method="POST" aria-label="Form login">
                    @csrf
                    @if ($errors->any())
                        <p class="form-error" role="alert">
                            {{ $errors->first() }}
                        </p>
                    @endif
                    <label class="field">
                        <span class="field-label">Email</span>
                        <span class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <rect x="3.5" y="5.5" width="17" height="13" rx="2"></rect>
                                <path d="m4.5 7 7.5 5.5L19.5 7"></path>
                            </svg>
                            <input class="text-input" type="email" name="email" value="{{ old('email') }}" placeholder="nama@kedaia1.id" autocomplete="username" required>
                        </span>
                    </label>

                    <label class="field">
                        <span class="field-label">Password</span>
                        <span class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                                <circle cx="12" cy="15" r="1"></circle>
                            </svg>
                            <input class="text-input" id="password" type="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                            <button class="password-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                    <path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"></path>
                                    <circle cx="12" cy="12" r="2.5"></circle>
                                </svg>
                            </button>
                        </span>
                    </label>

                    <div class="form-options">
                        <label class="remember"><input type="checkbox" name="remember" value="1" @checked(old('remember', true))> Ingat saya</label>
                        <a class="forgot" href="mailto:admin@kedaia1.id?subject=Bantuan%20akses%20akun">Lupa password?</a>
                    </div>

                    <button class="submit-button" type="submit">
                        <span>Masuk ke dashboard</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path d="M7 17 17 7M7 7h10v10"></path>
                        </svg>
                    </button>
                </form>
            </div>

            <div class="security-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                    <path d="M12 3 19 6v5c0 4.7-2.8 8.3-7 10-4.2-1.7-7-5.3-7-10V6l7-3Z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
                <span>Akses aman untuk tim operasional Kedai A1 · v2.4</span>
            </div>
        </section>
    </main>
    <script>
        const passwordInput = document.querySelector("#password");
        const passwordToggle = document.querySelector(".password-toggle");

        passwordToggle.addEventListener("click", () => {
            const isVisible = passwordInput.type === "text";
            passwordInput.type = isVisible ? "password" : "text";
            passwordToggle.setAttribute("aria-pressed", String(!isVisible));
            passwordToggle.setAttribute("aria-label", isVisible ? "Tampilkan password" : "Sembunyikan password");
        });
    </script>
</body>
</html>
