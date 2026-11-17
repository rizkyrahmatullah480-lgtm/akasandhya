/**
 * Format date string to Indonesian formal date format
 * e.g., "Sabtu, 14 Juni 2026" or "14 Juni 2026, 09:00 WIB"
 */
export function formatIndonesianDate(dateStr: string | Date, includeTime = false): string {
  if (!dateStr) return '-';
  const date = new Date(dateStr);
  if (isNaN(date.getTime())) return '-';

  const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];

  const dayName = days[date.getDay()];
  const day = date.getDate();
  const monthName = months[date.getMonth()];
  const year = date.getFullYear();

  let formatted = `${dayName}, ${day} ${monthName} ${year}`;

  if (includeTime) {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    formatted += ` • ${hours}:${minutes} WIB`;
  }

  return formatted;
}

/**
 * Format number to Indonesian Rupiah (Rp)
 */
export function formatRupiah(amount: number): string {
  if (amount === 0) return 'Gratis';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount);
}

/**
 * Calculate countdown components from target date
 */
export function calculateCountdown(targetDateStr: string | Date): {
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
  isPast: boolean;
} {
  const target = new Date(targetDateStr).getTime();
  const now = new Date().getTime();
  const diff = target - now;

  if (diff <= 0 || isNaN(diff)) {
    return { days: 0, hours: 0, minutes: 0, seconds: 0, isPast: true };
  }

  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);

  return { days, hours, minutes, seconds, isPast: false };
}

/**
 * Generate formatted WhatsApp share URL with pre-filled greeting message
 */
export function generateWhatsAppShareUrl(
  invitationTitle: string,
  eventDate: string,
  publicUrl: string,
  guestName?: string,
  phone?: string
): string {
  const greeting = guestName ? `Kepada Yth. *${guestName}*,\n\n` : '';
  const dateFormatted = formatIndonesianDate(eventDate);

  const message =
    `*Undangan Digital — ${invitationTitle}*\n\n` +
    greeting +
    `Tanpa mengurangi rasa hormat, perkenankan kami mengundang Anda untuk menghadiri acara kami pada:\n\n` +
    `🗓 Tanggal: ${dateFormatted}\n\n` +
    `Untuk melihat detail acara, lokasi, dan konfirmasi kehadiran, silakan buka tautan berikut:\n` +
    `${publicUrl}${guestName ? `?to=${encodeURIComponent(guestName)}` : ''}\n\n` +
    `Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir dan memberikan doa restu.\n\n` +
    `Terima kasih.`;

  const encodedMessage = encodeURIComponent(message);

  if (phone) {
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    const intlPhone = cleanPhone.startsWith('0') ? '62' + cleanPhone.slice(1) : cleanPhone;
    return `https://wa.me/${intlPhone}?text=${encodedMessage}`;
  }

  return `https://api.whatsapp.com/send?text=${encodedMessage}`;
}

/**
 * Generate URL-friendly slug
 */
export function slugify(text: string): string {
  return text
    .toString()
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '-')
    .replace(/[^\w\-]+/g, '')
    .replace(/\-\-+/g, '-')
    .replace(/^-+/, '')
    .replace(/-+$/, '');
}
