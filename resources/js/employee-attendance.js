document.querySelectorAll('[data-attendance-capture]').forEach((panel) => {
    const video = panel.querySelector('[data-camera-video]');
    const preview = panel.querySelector('[data-camera-photo]');
    const placeholder = panel.querySelector('[data-camera-placeholder]');
    const startCameraButton = panel.querySelector('[data-camera-start]');
    const captureButton = panel.querySelector('[data-camera-capture]');
    const locationButton = panel.querySelector('[data-location-capture]');
    const photoInput = panel.querySelector('[data-photo-input]');
    const latitudeInput = panel.querySelector('[data-latitude]');
    const longitudeInput = panel.querySelector('[data-longitude]');
    const accuracyInput = panel.querySelector('[data-location-accuracy-input]');
    const locationLabel = panel.querySelector('[data-location-label]');
    const locationAccuracy = panel.querySelector('[data-location-accuracy]');
    const submitButton = panel.querySelector('[data-attendance-submit]');
    const hint = panel.querySelector('[data-capture-hint]');
    const cameraStep = panel.querySelector('[data-step-camera]');
    const locationStep = panel.querySelector('[data-step-location]');
    const saveStep = panel.querySelector('[data-step-save]');
    const shiftSelect = panel.querySelector('select[name="shift_id"]');
    let stream = null;
    let photoReady = false;
    let locationReady = false;

    const refreshState = () => {
        const ready = photoReady && locationReady && (!shiftSelect || shiftSelect.value !== '');
        submitButton.disabled = !ready;
        cameraStep.classList.toggle('is-complete', photoReady);
        locationStep.classList.toggle('is-complete', locationReady);
        saveStep.classList.toggle('is-active', ready);

        if (ready) {
            hint.textContent = 'Foto dan lokasi sudah siap. Periksa kembali data sebelum menyimpan.';
        } else if (photoReady && locationReady && shiftSelect && !shiftSelect.value) {
            hint.textContent = 'Pilih shift sebelum menyimpan absensi.';
        } else {
            hint.textContent = 'Ambil foto kamera dan lokasi GPS untuk mengaktifkan tombol simpan.';
        }
    };

    shiftSelect?.addEventListener('change', refreshState);

    startCameraButton.addEventListener('click', async () => {
        if (!navigator.mediaDevices?.getUserMedia) {
            hint.textContent = 'Kamera tidak tersedia. Buka portal melalui HTTPS atau localhost dan gunakan browser yang mendukung kamera.';
            return;
        }

        startCameraButton.disabled = true;
        hint.textContent = 'Meminta izin kamera depan...';

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: 'user' },
            });
            video.srcObject = stream;
            await video.play();
            video.classList.add('is-visible');
            preview.classList.remove('is-visible');
            placeholder.hidden = true;
            captureButton.disabled = false;
            locationButton.disabled = false;
            startCameraButton.querySelector('span').textContent = 'Kamera Aktif';
            hint.textContent = 'Kamera aktif. Ambil foto dan izinkan akses lokasi GPS.';
        } catch (error) {
            startCameraButton.disabled = false;
            hint.textContent = error.name === 'NotAllowedError'
                ? 'Izin kamera ditolak. Izinkan kamera di pengaturan browser, lalu coba lagi.'
                : 'Kamera gagal diaktifkan. Pastikan kamera tidak sedang digunakan aplikasi lain.';
        }
    });

    captureButton.addEventListener('click', () => {
        if (!stream || video.videoWidth === 0 || video.videoHeight === 0) {
            hint.textContent = 'Tunggu sampai pratinjau kamera siap sebelum mengambil foto.';
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        context.translate(canvas.width, 0);
        context.scale(-1, 1);
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => {
            if (!blob) {
                hint.textContent = 'Foto gagal diproses. Silakan ambil foto kembali.';
                return;
            }

            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], `absensi-${Date.now()}.jpg`, {
                type: 'image/jpeg',
                lastModified: Date.now(),
            }));
            photoInput.files = transfer.files;
            preview.src = URL.createObjectURL(blob);
            preview.classList.add('is-visible');
            video.classList.remove('is-visible');
            photoReady = true;
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
            captureButton.disabled = true;
            captureButton.textContent = '✓ Foto Diambil';
            refreshState();
        }, 'image/jpeg', 0.86);
    });

    locationButton.addEventListener('click', () => {
        if (!navigator.geolocation) {
            hint.textContent = 'Browser tidak mendukung GPS. Gunakan browser yang mengizinkan akses lokasi.';
            return;
        }

        locationButton.disabled = true;
        locationLabel.textContent = 'Mengambil lokasi perangkat...';
        hint.textContent = 'Izinkan akses lokasi di browser untuk menyelesaikan absensi.';

        navigator.geolocation.getCurrentPosition(
            (position) => {
                latitudeInput.value = position.coords.latitude.toFixed(7);
                longitudeInput.value = position.coords.longitude.toFixed(7);
                accuracyInput.value = position.coords.accuracy.toFixed(2);
                locationLabel.textContent = `${position.coords.latitude.toFixed(5)}, ${position.coords.longitude.toFixed(5)}`;
                locationAccuracy.textContent = `Akurasi perangkat ±${Math.round(position.coords.accuracy)} m`;
                locationReady = true;
                locationButton.textContent = '✓ Lokasi Tersimpan';
                refreshState();
            },
            (error) => {
                locationButton.disabled = false;
                locationLabel.textContent = 'Lokasi belum diambil';
                hint.textContent = error.code === error.PERMISSION_DENIED
                    ? 'Izin lokasi ditolak. Izinkan lokasi di pengaturan browser, lalu coba lagi.'
                    : 'Lokasi tidak dapat diperoleh. Aktifkan GPS dan coba kembali.';
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 15000 },
        );
    });

    panel.querySelector('form[data-capture-form]')?.addEventListener('submit', (event) => {
        if (submitButton.disabled) {
            event.preventDefault();
            hint.textContent = 'Foto kamera, lokasi GPS, dan shift wajib dilengkapi sebelum menyimpan.';
        }
    });

    window.addEventListener('pagehide', () => {
        stream?.getTracks().forEach((track) => track.stop());
    });

    refreshState();
});
