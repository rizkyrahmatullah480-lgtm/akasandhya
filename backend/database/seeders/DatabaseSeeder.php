<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Category;
use App\Models\Event;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@invitaku.com'],
            [
                'name' => 'Administrator Invitaku',
                'password' => Hash::make('admin123'),
                'role' => User::ROLE_ADMIN,
                'phone' => '081234567890',
            ]
        );

        $scanner = User::updateOrCreate(
            ['email' => 'scanner@invitaku.com'],
            [
                'name' => 'Petugas Scanner',
                'password' => Hash::make('scanner123'),
                'role' => User::ROLE_SCANNER,
                'phone' => '081298765432',
            ]
        );

        User::updateOrCreate(
            ['email' => 'petugas2@invitaku.com'],
            [
                'name' => 'Petugas Meja Registrasi',
                'password' => Hash::make('scanner123'),
                'role' => User::ROLE_SCANNER,
                'phone' => '081277665544',
            ]
        );

        $categories = [
            ['name' => 'Pernikahan', 'slug' => 'pernikahan', 'icon' => 'Heart', 'description' => 'Undangan pernikahan elegan dan romantis'],
            ['name' => 'Ulang Tahun', 'slug' => 'ulang-tahun', 'icon' => 'Cake', 'description' => 'Perayaan ulang tahun meriah dan ceria'],
            ['name' => 'Seminar', 'slug' => 'seminar', 'icon' => 'Presentation', 'description' => 'Undangan seminar dan konferensi profesional'],
            ['name' => 'Wisuda', 'slug' => 'wisuda', 'icon' => 'GraduationCap', 'description' => 'Perayaan kelulusan dan wisuda'],
            ['name' => 'Acara Formal', 'slug' => 'acara-formal', 'icon' => 'Building2', 'description' => 'Gala dinner, pelantikan, dan acara resmi'],
            ['name' => 'Acara Keluarga', 'slug' => 'acara-keluarga', 'icon' => 'Users', 'description' => 'Reuni, arisan, aqiqah, dan syukuran keluarga'],
            ['name' => 'Acara Lainnya', 'slug' => 'acara-lainnya', 'icon' => 'Calendar', 'description' => 'Berbagai acara lain yang perlu diundang'],
        ];

        $categoryModels = [];
        foreach ($categories as $category) {
            $categoryModels[$category['slug']] = Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }

        $templates = [
            ['pernikahan', 'Elegant Floral', 'elegant-floral', 'Desain bunga botani mewah dengan sentuhan burgundy dan rose gold.', '/templates/elegant-floral.jpg', false, [
                'theme' => 'floral', 'primary_color' => '#7B2D43', 'secondary_color' => '#A65D70', 'accent_color' => '#D6B98C',
                'bg_color' => '#FAF8F6', 'text_color' => '#252323', 'font_heading' => 'Playfair Display', 'font_body' => 'Inter',
                'decoration_style' => 'floral_corner', 'cover_style' => 'center_card',
            ]],
            ['pernikahan', 'Minimalist White', 'minimalist-white', 'Konsep clean modern monokromatik dengan tipografi estetis.', '/templates/minimalist-white.jpg', false, [
                'theme' => 'minimalist', 'primary_color' => '#1F2937', 'secondary_color' => '#4B5563', 'accent_color' => '#9CA3AF',
                'bg_color' => '#FFFFFF', 'text_color' => '#111827', 'font_heading' => 'Inter', 'font_body' => 'Inter',
                'decoration_style' => 'fine_borders', 'cover_style' => 'full_bleed',
            ]],
            ['pernikahan', 'Classic Burgundy', 'classic-burgundy', 'Warna marun megah dengan ornamen vintage mewah.', '/templates/classic-burgundy.jpg', true, [
                'theme' => 'burgundy', 'primary_color' => '#58111A', 'secondary_color' => '#7B2D43', 'accent_color' => '#E5C158',
                'bg_color' => '#FDFBF7', 'text_color' => '#2A1B1D', 'font_heading' => 'Cormorant Garamond', 'font_body' => 'Inter',
                'decoration_style' => 'vintage_crest', 'cover_style' => 'envelope_gate',
            ]],
            ['acara-formal', 'Golden Luxury', 'golden-luxury', 'Aksen emas berkilau dengan latar gelap elegan untuk acara berkelas.', '/templates/golden-luxury.jpg', true, [
                'theme' => 'luxury', 'primary_color' => '#C5A059', 'secondary_color' => '#DFBA73', 'accent_color' => '#FAF0D7',
                'bg_color' => '#181616', 'text_color' => '#F3EFE6', 'font_heading' => 'Playfair Display', 'font_body' => 'Inter',
                'decoration_style' => 'gold_sparkle', 'cover_style' => 'hero_split',
            ]],
            ['pernikahan', 'Rustic Garden', 'rustic-garden', 'Nuansa sage green, dedaunan eucalyptus, dan tekstur kayu hangat.', '/templates/rustic-garden.jpg', false, [
                'theme' => 'rustic', 'primary_color' => '#4A5D4E', 'secondary_color' => '#7A8F7E', 'accent_color' => '#D1BC9E',
                'bg_color' => '#F7F8F5', 'text_color' => '#2B352E', 'font_heading' => 'Cormorant Garamond', 'font_body' => 'Inter',
                'decoration_style' => 'botanical_wreath', 'cover_style' => 'polaroid_stack',
            ]],
            ['pernikahan', 'Modern Monogram', 'modern-monogram', 'Fokus pada inisial nama yang geometris, tegas, namun tetap anggun.', '/templates/modern-monogram.jpg', true, [
                'theme' => 'monogram', 'primary_color' => '#2C3E50', 'secondary_color' => '#4A6572', 'accent_color' => '#C5A880',
                'bg_color' => '#F9FBFB', 'text_color' => '#1E272C', 'font_heading' => 'Playfair Display', 'font_body' => 'Inter',
                'decoration_style' => 'geometric_monogram', 'cover_style' => 'badge_circle',
            ]],
            ['pernikahan', 'Islamic Wedding', 'islamic-wedding', 'Nuansa islami syahdu dengan kaligrafi dan ornamen kubah.', '/templates/islamic-wedding.jpg', false, [
                'theme' => 'islamic', 'primary_color' => '#1B4D3E', 'secondary_color' => '#2E7D63', 'accent_color' => '#D4AF37',
                'bg_color' => '#FAFBF7', 'text_color' => '#142921', 'font_heading' => 'Cormorant Garamond', 'font_body' => 'Inter',
                'decoration_style' => 'arabesque_arch', 'cover_style' => 'arch_window',
            ]],
            ['acara-keluarga', 'Traditional Java', 'traditional-java', 'Motif batik truntum keemasan dan ornamen gunungan khas Jawa.', '/templates/traditional-java.jpg', true, [
                'theme' => 'java', 'primary_color' => '#4A2E18', 'secondary_color' => '#754B24', 'accent_color' => '#C69214',
                'bg_color' => '#FBF7EE', 'text_color' => '#2B1A0D', 'font_heading' => 'Playfair Display', 'font_body' => 'Inter',
                'decoration_style' => 'gunungan_batik', 'cover_style' => 'traditional_gate',
            ]],
            ['ulang-tahun', 'Modern Birthday', 'modern-birthday', 'Pesta penuh energi dengan warna pastel cerah dan confetti.', '/templates/modern-birthday.jpg', false, [
                'theme' => 'birthday', 'primary_color' => '#8E44AD', 'secondary_color' => '#E91E63', 'accent_color' => '#FFC107',
                'bg_color' => '#FDF7FC', 'text_color' => '#2C1B33', 'font_heading' => 'Playfair Display', 'font_body' => 'Inter',
                'decoration_style' => 'confetti_burst', 'cover_style' => 'party_card',
            ]],
            ['acara-keluarga', 'Simple Family Event', 'simple-family-event', 'Desain hangat dan nyaman dibaca untuk acara keluarga.', '/templates/simple-family-event.jpg', false, [
                'theme' => 'family', 'primary_color' => '#2980B9', 'secondary_color' => '#3498DB', 'accent_color' => '#F39C12',
                'bg_color' => '#F4F9FC', 'text_color' => '#1B3A4B', 'font_heading' => 'Inter', 'font_body' => 'Inter',
                'decoration_style' => 'soft_waves', 'cover_style' => 'card_rounded',
            ]],
            ['seminar', 'Professional Seminar', 'professional-seminar', 'Tata letak bersih, informatif, dan profesional untuk seminar.', '/templates/simple-family-event.jpg', false, [
                'theme' => 'seminar', 'primary_color' => '#0F4C81', 'secondary_color' => '#1D6FA5', 'accent_color' => '#F2B705',
                'bg_color' => '#F5F9FC', 'text_color' => '#102A3C', 'font_heading' => 'Inter', 'font_body' => 'Inter',
                'decoration_style' => 'grid_lines', 'cover_style' => 'card_rounded',
            ]],
            ['wisuda', 'Wisuda Elegan', 'wisuda-elegan', 'Desain anggun untuk merayakan kelulusan dan wisuda.', '/templates/classic-burgundy.jpg', false, [
                'theme' => 'graduation', 'primary_color' => '#3B2F63', 'secondary_color' => '#5B4B8A', 'accent_color' => '#D4AF37',
                'bg_color' => '#F8F6FC', 'text_color' => '#241E3A', 'font_heading' => 'Playfair Display', 'font_body' => 'Inter',
                'decoration_style' => 'laurel', 'cover_style' => 'badge_circle',
            ]],
        ];

        $templateModels = [];
        foreach ($templates as [$categorySlug, $name, $slug, $description, $image, $premium, $configuration]) {
            $templateModels[$slug] = Template::updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categoryModels[$categorySlug]->id,
                    'name' => $name,
                    'description' => $description,
                    'preview_image' => $image,
                    'configuration' => $configuration,
                    'is_premium' => $premium,
                    'is_active' => true,
                ]
            );
        }

        $invitationService = app(InvitationService::class);

        // --- Demo event 1: wedding ---
        $wedding = Event::updateOrCreate(
            ['name' => 'Pernikahan Rian & Sinta'],
            [
                'event_type' => 'pernikahan',
                'organizer' => 'Keluarga Rian & Sinta',
                'event_date' => now()->addDays(30)->toDateString(),
                'event_time' => '09:00',
                'location' => 'Grand Ballroom Hotel Mulia Senayan',
                'address' => 'Jl. Asia Afrika No. 6, Gelora, Tanah Abang, Jakarta Pusat',
                'description' => 'Dengan penuh rasa syukur dan sukacita, kami mengundang Bapak/Ibu/Saudara/i untuk hadir dan memberikan doa restu pada pernikahan kami.',
                'template_id' => $templateModels['elegant-floral']->id,
                'created_by' => $admin->id,
            ]
        );

        // --- Demo event 2: seminar ---
        $seminar = Event::updateOrCreate(
            ['name' => 'Seminar Digital Marketing 2026'],
            [
                'event_type' => 'seminar',
                'organizer' => 'Invitaku Academy',
                'event_date' => now()->addDays(14)->toDateString(),
                'event_time' => '13:00',
                'location' => 'Auditorium Gedung Kreatif',
                'address' => 'Jl. Kemang Raya No. 18, Jakarta Selatan',
                'description' => 'Seminar praktis tentang strategi digital marketing untuk UMKM, bersama praktisi berpengalaman.',
                'template_id' => $templateModels['professional-seminar']->id,
                'created_by' => $admin->id,
            ]
        );

        // --- Demo event 3: simple santri mode ---
        $santriEvent = Event::updateOrCreate(
            ['name' => 'Wisuda Santri 2026'],
            [
                'event_type' => 'santri',
                'organizer' => 'Majelis Khidmah Darul Ulum',
                'event_date' => now()->addDays(45)->toDateString(),
                'event_time' => '09:00',
                'location' => 'Kantor Pusat Darul Ulum',
                'address' => 'Gedung Serbaguna Darul Ulum',
                'description' => 'Menyemai Ilmu, Menuai Cahaya. Mengharap kehadiran dan doa restu Bapak/Ibu/Saudara/i pada momentum wisuda santri.',
                'template_id' => $templateModels['wisuda-elegan']->id,
                'created_by' => $admin->id,
            ]
        );

        $santriGuests = [
            ['Ahmad Fauzi', '081298761001', 'Santri', 'Wali Ahmad Subki'],
            ['Muhammad Rizky', '081298761002', 'Santri', 'Wali Hasan Basri'],
            ['Siti Aisyah', '081298761003', 'Santri', 'Wali Abdul Rahman'],
            ['Nur Hidayah', '081298761004', 'Santri', 'Wali Maksum'],
            ['Aulia Rahma', '081298761005', 'Santri', 'Wali Syamsul Huda'],
            ['Fikri Maulana', '081298761006', 'Alumni', 'Angkatan 2024'],
            ['Zahra Nabila', '081298761007', 'Alumni', 'Angkatan 2023'],
            ['Ilham Ramadhan', '081298761008', 'Alumni', 'Angkatan 2022'],
        ];

        $santriGuestModels = [];
        foreach ($santriGuests as $index => [$name, $phone, $type, $wali]) {
            $guest = Guest::firstOrCreate(
                ['event_id' => $santriEvent->id, 'name' => $name],
                [
                    'whatsapp_number' => $invitationService->normalizePhone($phone),
                    'category' => $type,
                    'wali_angkatan' => $wali,
                    'santri_code' => Guest::nextSantriCode($santriEvent->id, $type),
                ]
            );

            if (empty($guest->santri_code)) {
                $guest->santri_code = Guest::nextSantriCode($santriEvent->id, $type);
            }
            if (empty($guest->wali_angkatan)) {
                $guest->wali_angkatan = $wali;
            }
            $guest->save();

            $invitation = $invitationService->generateForGuest($guest);
            $santriGuestModels[] = $guest;

            if ($index < 5) {
                $invitation->update([
                    'delivery_status' => Invitation::STATUS_SENT,
                    'sent_at' => now()->subHours($index + 1),
                ]);
            } elseif ($index === 5) {
                $invitation->update(['delivery_status' => Invitation::STATUS_QUEUED]);
            }
        }

        foreach (array_slice($santriGuestModels, 0, 2) as $index => $guest) {
            Attendance::firstOrCreate(
                ['guest_id' => $guest->id, 'event_id' => $santriEvent->id],
                [
                    'status' => 'present',
                    'checked_in_at' => now()->subMinutes(20 - ($index * 5)),
                    'checked_in_by' => $scanner->id,
                ]
            );
        }

        $weddingGuests = [
            ['Budi Santoso', '081234567801', 'Keluarga'],
            ['Dewi Anggraini', '081234567802', 'Teman'],
            ['Ahmad Fauzi', '081234567803', 'Kantor'],
            ['Siti Nurhaliza', '081234567804', 'Keluarga'],
            ['Rizky Pratama', '081234567805', 'Teman'],
            ['Maya Sari', '081234567806', 'Kantor'],
            ['Hendra Wijaya', '081234567807', 'Keluarga'],
            ['Lina Marlina', '081234567808', 'Teman'],
            ['Agus Setiawan', '081234567809', 'Kantor'],
            ['Putri Ayu', '081234567810', 'Keluarga'],
            ['Fajar Nugroho', '081234567811', 'Teman'],
            ['Nadia Safira', '081234567812', 'Kantor'],
        ];

        $seminarGuests = [
            ['Andi Kurniawan', '081298760001', 'Peserta'],
            ['Rina Wulandari', '081298760002', 'Peserta'],
            ['Bayu Saputra', '081298760003', 'Peserta'],
            ['Citra Lestari', '081298760004', 'Peserta'],
            ['Dimas Ardiansyah', '081298760005', 'Peserta'],
            ['Eka Prasetyo', '081298760006', 'Peserta'],
        ];

        $allWeddingGuests = [];
        foreach ($weddingGuests as $index => [$name, $phone, $category]) {
            $guest = Guest::firstOrCreate(
                ['event_id' => $wedding->id, 'name' => $name],
                [
                    'whatsapp_number' => $invitationService->normalizePhone($phone),
                    'category' => $category,
                ]
            );
            $invitation = $invitationService->generateForGuest($guest);
            $allWeddingGuests[] = [$guest, $invitation];

            if ($index < 8) {
                $invitation->update(['delivery_status' => Invitation::STATUS_SENT, 'sent_at' => now()->subHours($index + 1)]);
            } elseif ($index < 10) {
                $invitation->update(['delivery_status' => Invitation::STATUS_QUEUED]);
            } elseif ($index === 10) {
                $invitation->update(['delivery_status' => Invitation::STATUS_FAILED, 'delivery_error' => 'Nomor WhatsApp tidak aktif (contoh data demo).']);
            }
        }

        foreach ($seminarGuests as $index => [$name, $phone, $category]) {
            $guest = Guest::firstOrCreate(
                ['event_id' => $seminar->id, 'name' => $name],
                [
                    'whatsapp_number' => $invitationService->normalizePhone($phone),
                    'category' => $category,
                ]
            );
            $invitation = $invitationService->generateForGuest($guest);

            if ($index < 3) {
                $invitation->update(['delivery_status' => Invitation::STATUS_SENT, 'sent_at' => now()->subHours($index + 1)]);
            }
        }

        // Some guests already checked in for the wedding demo.
        foreach (array_slice($allWeddingGuests, 0, 4) as $index => [$guest]) {
            Attendance::firstOrCreate(
                ['guest_id' => $guest->id, 'event_id' => $wedding->id],
                [
                    'status' => 'present',
                    'checked_in_at' => now()->subMinutes(30 - ($index * 5)),
                    'checked_in_by' => $scanner->id,
                ]
            );
        }

        Attendance::firstOrCreate(
            ['guest_id' => Guest::where('event_id', $seminar->id)->first()->id, 'event_id' => $seminar->id],
            [
                'status' => 'present',
                'checked_in_at' => now()->subMinutes(10),
                'checked_in_by' => $scanner->id,
            ]
        );
    }
}
