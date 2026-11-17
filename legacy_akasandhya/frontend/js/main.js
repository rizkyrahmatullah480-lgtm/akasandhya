document.addEventListener('DOMContentLoaded', () => {
    // 1. Ambil Parameter URL ?to=NamaTamu
    const urlParams = new URLSearchParams(window.location.search);
    const guestName = urlParams.get('to') || 'Tamu Undangan';
    
    // Tampilkan nama di halaman depan dan di form RSVP
    document.getElementById('guestNameDisplay').textContent = guestName;
    document.getElementById('rsvpName').value = guestName;

    // Elements
    const opening = document.getElementById('opening');
    const mainContent = document.getElementById('mainContent');
    const btnOpen = document.getElementById('btnOpen');
    const bgMusic = document.getElementById('bgMusic');
    const musicToggle = document.getElementById('musicToggle');
    let isPlaying = false;

    // 2. Buka Undangan (Animasi & Musik)
    btnOpen.addEventListener('click', () => {
        // Animasi slide up
        opening.style.transform = 'translateY(-100%)';
        
        setTimeout(() => {
            opening.style.display = 'none';
            mainContent.classList.remove('hidden');
            document.body.classList.remove('overflow-hidden');
            
            // Play Music
            bgMusic.play().then(() => {
                isPlaying = true;
                musicToggle.classList.remove('hidden');
                musicToggle.innerHTML = '<i class="fas fa-pause"></i>';
            }).catch(e => console.log("Auto-play dicegah oleh browser, user harus menekan tombol play."));
            
            // Load list ucapan saat halaman utama terbuka
            loadUcapan();
        }, 800); // Wait for transition
    });

    // Toggle Play/Pause Music
    musicToggle.addEventListener('click', () => {
        if (isPlaying) {
            bgMusic.pause();
            musicToggle.innerHTML = '<i class="fas fa-play"></i>';
        } else {
            bgMusic.play();
            musicToggle.innerHTML = '<i class="fas fa-pause"></i>';
        }
        isPlaying = !isPlaying;
    });

    // 3. Countdown Timer (Misal: 12 Juli 2026 08:00 WIB)
    const countDownDate = new Date("Jul 12, 2026 08:00:00").getTime();
    const x = setInterval(function() {
        const now = new Date().getTime();
        const distance = countDownDate - now;

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        const elDays = document.getElementById("days");
        if(elDays) {
            elDays.innerHTML = String(days).padStart(2, '0');
            document.getElementById("hours").innerHTML = String(hours).padStart(2, '0');
            document.getElementById("mins").innerHTML = String(minutes).padStart(2, '0');
            document.getElementById("secs").innerHTML = String(seconds).padStart(2, '0');
        }

        if (distance < 0) {
            clearInterval(x);
            if(elDays) {
                elDays.innerHTML = "00";
                document.getElementById("hours").innerHTML = "00";
                document.getElementById("mins").innerHTML = "00";
                document.getElementById("secs").innerHTML = "00";
            }
        }
    }, 1000);

    // 4. Buat Animasi Partikel Biru Laut
    function createParticles() {
        const container = document.getElementById('particles-container');
        if(!container) return;
        
        for(let i=0; i<30; i++) {
            const particle = document.createElement('div');
            particle.classList.add('particle');
            
            // Random properties
            const size = Math.random() * 8 + 4; // 4px to 12px
            const left = Math.random() * 100; // 0% to 100%
            const delay = Math.random() * 10; // 0s to 10s
            const duration = Math.random() * 10 + 10; // 10s to 20s
            
            particle.style.width = `${size}px`;
            particle.style.height = `${size}px`;
            particle.style.left = `${left}%`;
            particle.style.animationDelay = `${delay}s`;
            particle.style.animationDuration = `${duration}s`;
            
            container.appendChild(particle);
        }
    }
    createParticles();

    // 5. Generate QR Code untuk Guest
    // Menggunakan CDN QRCode dari HTML file
    setTimeout(() => {
        const qrContainer = document.getElementById("qrcodeLocation");
        if(qrContainer && typeof QRCode !== 'undefined') {
            new QRCode(qrContainer, {
                text: "https://maps.app.goo.gl/ContohLokasiAkasandhya",
                width: 150,
                height: 150,
                colorDark : "#0c2445",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }
    }, 1500);

    // 6. Submit RSVP & Ucapan
    const rsvpForm = document.getElementById('rsvpForm');
    if(rsvpForm) {
        rsvpForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // Tampilkan state loading
            const btnSubmit = rsvpForm.querySelector('button[type="submit"]');
            const originalText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mengirim...';
            btnSubmit.disabled = true;

            const data = {
                nama: document.getElementById('rsvpName').value,
                kehadiran: document.getElementById('rsvpStatus').value,
                ucapan: document.getElementById('rsvpMessage').value
            };

            try {
                // Gunakan URL absolute backend API
                const res = await fetch('http://localhost:3000/api/rsvp', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();
                
                // Beri notifikasi sukses
                alert(result.message || 'Konfirmasi berhasil dikirim!');
                
                // Clear form dan reload list ucapan
                document.getElementById('rsvpMessage').value = '';
                loadUcapan();
                
            } catch (err) {
                alert('Ada kesalahan saat menghubungi server. Pastikan Backend berjalan.');
                console.error(err);
            } finally {
                btnSubmit.innerHTML = originalText;
                btnSubmit.disabled = false;
            }
        });
    }

    // 7. Load Data Ucapan
    async function loadUcapan() {
        const listContainer = document.getElementById('ucapanList');
        if(!listContainer) return;
        
        try {
            const res = await fetch('http://localhost:3000/api/ucapan');
            const ucapanData = await res.json();
            
            listContainer.innerHTML = '';
            
            if(ucapanData.length === 0) {
                listContainer.innerHTML = '<p class="text-ocean-300 text-sm italic">Belum ada ucapan, jadilah yang pertama!</p>';
                return;
            }

            ucapanData.forEach(item => {
                const dateObj = new Date(item.created_at);
                const dateStr = dateObj.toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'});
                
                listContainer.innerHTML += `
                    <div class="bg-ocean-900/60 p-4 rounded-xl border border-ocean-700 mb-4 animate-fade-in-up">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 bg-gold text-ocean-900 rounded-full flex items-center justify-center font-bold">
                                ${item.nama.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h4 class="font-bold text-ocean-100">${item.nama}</h4>
                                <span class="text-xs text-ocean-400">${dateStr}</span>
                            </div>
                        </div>
                        <p class="text-slate-300 text-sm mt-2 font-light">"${item.ucapan}"</p>
                    </div>
                `;
            });
            
        } catch(e) {
            console.error("Gagal load ucapan:", e);
        }
    }
});
