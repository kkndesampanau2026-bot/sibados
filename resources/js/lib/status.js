/**
 * Status ketersediaan pasangan/Asdos. `tone` dipakai oleh komponen Badge,
 * sehingga warna status hanya didefinisikan di satu tempat.
 */
export const AVAILABILITY = {
    tersedia: { label: 'Tersedia', tone: 'success' },
    hampir_penuh: { label: 'Hampir Penuh', tone: 'warning' },
    penuh: { label: 'Penuh', tone: 'danger' },
    sudah_dibooking: { label: 'Sudah Dibooking', tone: 'info' },
    nonaktif: { label: 'Nonaktif', tone: 'neutral' },
};

export function availabilityOf(key) {
    return AVAILABILITY[key] ?? AVAILABILITY.nonaktif;
}

/** Pasangan hanya dapat dipilih ketika masih ada sisa kuota. */
export function isBookable(key) {
    return key === 'tersedia' || key === 'hampir_penuh';
}

export const BOOKING_STATUS = {
    aktif: { label: 'Aktif', tone: 'success' },
    dibatalkan: { label: 'Dibatalkan', tone: 'neutral' },
};

export const ROLE_LABEL = {
    admin: 'Admin / Koordinator',
    asdos: 'Asisten Dosen',
    perwakilan: 'Ketua Kelas',
};

export const ROLE_TONE = {
    admin: 'brand',
    asdos: 'info',
    perwakilan: 'success',
};

export const LOG_ACTION = {
    booking: { label: 'Booking', tone: 'success' },
    penempatan_admin: { label: 'Penempatan Admin', tone: 'brand' },
    perubahan: { label: 'Perubahan', tone: 'warning' },
    pembatalan: { label: 'Pembatalan', tone: 'danger' },
    pengaturan: { label: 'Pengaturan', tone: 'neutral' },
};

export function logAction(key) {
    return LOG_ACTION[key] ?? { label: key, tone: 'neutral' };
}

/** Nada untuk rasio pemakaian kuota. */
export function quotaTone(used, max) {
    if (!max || used >= max) return 'danger';
    if (max - used === 1) return 'warning';

    return 'success';
}

/** Menyusun sisa waktu untuk countdown booking. */
export function countdownParts(target) {
    const diff = new Date(target).getTime() - Date.now();

    if (Number.isNaN(diff) || diff <= 0) {
        return null;
    }

    return {
        hari: Math.floor(diff / 86400000),
        jam: Math.floor((diff / 3600000) % 24),
        menit: Math.floor((diff / 60000) % 60),
        detik: Math.floor((diff / 1000) % 60),
    };
}
