import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Avatar, Icon, cx } from './UI';

/*
|--------------------------------------------------------------------------
| Navigasi per peran
|--------------------------------------------------------------------------
*/

const MENUS = {
    admin: [
        { section: null, items: [{ label: 'Dashboard', href: '/admin/dashboard', icon: 'grid' }] },
        {
            section: 'Master Data',
            items: [
                { label: 'Mata Kuliah', href: '/admin/mata-kuliah', icon: 'book' },
                { label: 'Kelas & Praktikum', href: '/admin/kelas', icon: 'door', short: 'Kelas' },
                { label: 'Asdos', href: '/admin/asdos', icon: 'user' },
                { label: 'Pasangan Asdos', href: '/admin/pasangan', icon: 'users', short: 'Pasangan' },
                { label: 'Pengguna', href: '/admin/pengguna', icon: 'key' },
            ],
        },
        {
            section: 'Penempatan',
            items: [
                { label: 'Penempatan & Kuota', href: '/admin/penempatan-matkul', icon: 'sliders', short: 'Kuota' },
                { label: 'Booking', href: '/admin/booking', icon: 'check' },
                { label: 'Praktikum Belum Terisi', href: '/admin/praktikum-belum-terisi', icon: 'alert', short: 'Kosong' },
            ],
        },
        { section: 'Monitoring', items: [{ label: 'Riwayat Booking', href: '/admin/riwayat', icon: 'clock' }] },
        { section: 'Sistem', items: [{ label: 'Pengaturan', href: '/admin/pengaturan', icon: 'cog' }] },
    ],
    asdos: [
        {
            section: null,
            items: [
                { label: 'Dashboard', href: '/asdos/dashboard', icon: 'grid' },
                { label: 'Profil Saya', href: '/profil', icon: 'user', short: 'Profil' },
            ],
        },
    ],
    perwakilan: [
        {
            section: null,
            items: [
                { label: 'Praktikum Kelas Saya', href: '/perwakilan/dashboard', icon: 'grid', short: 'Praktikum' },
                { label: 'Profil Saya', href: '/profil', icon: 'user', short: 'Profil' },
            ],
        },
    ],
};

const ROLE_LABEL = {
    admin: 'Koordinator Asdos',
    asdos: 'Asisten Dosen',
    perwakilan: 'Ketua Kelas',
};

const NAV_ICONS = {
    grid: 'M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z',
    book: 'M5 4.5A1.5 1.5 0 0 1 6.5 3H19v14H6.5A1.5 1.5 0 0 0 5 18.5v-14ZM5 18.5A1.5 1.5 0 0 0 6.5 20H19',
    door: 'M6 3h10a1 1 0 0 1 1 1v17H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7.5 8.5h.01M4 21h16',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0',
    users: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.5 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2.5 20a6.5 6.5 0 0 1 13 0m2-6a6 6 0 0 1 4 6',
    key: 'M14 3a7 7 0 0 0-6.7 9L3 16.3V21h4.7l1.3-1.3V18h1.7l1.3-1.3V15h1.6l1.2-1.2A7 7 0 1 0 14 3Zm2.8 4.2h.01',
    sliders: 'M4 6h16M4 12h16M4 18h16M9 4v4m6 2v4M8 16v4',
    check: 'M4 12.5 9 17.5 20 6.5',
    alert: 'M12 9v4m0 4h.01M10.3 4.3 2.6 17.6A2 2 0 0 0 4.3 20.6h15.4a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z',
    clock: 'M12 7v5l3.5 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    cog: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8.4-3a8.4 8.4 0 0 0-.15-1.55l2-1.55-2-3.45-2.35.95a8.4 8.4 0 0 0-2.7-1.55L14.8 2h-4l-.4 2.85a8.4 8.4 0 0 0-2.7 1.55L5.35 5.45l-2 3.45 2 1.55A8.4 8.4 0 0 0 5.2 12c0 .53.05 1.05.15 1.55l-2 1.55 2 3.45 2.35-.95a8.4 8.4 0 0 0 2.7 1.55l.4 2.85h4l.4-2.85a8.4 8.4 0 0 0 2.7-1.55l2.35.95 2-3.45-2-1.55c.1-.5.15-1.02.15-1.55Z',
};

function NavIcon({ name, className = 'size-4.5' }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.7"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            <path d={NAV_ICONS[name] ?? NAV_ICONS.grid} />
        </svg>
    );
}

/** Cocok bila URL sekarang tepat sama atau merupakan anak dari href. */
function isActive(current, href) {
    const path = current.split('?')[0];

    return path === href || path.startsWith(`${href}/`);
}

/*
|--------------------------------------------------------------------------
| Merek
|--------------------------------------------------------------------------
*/

function BrandMark({ className = 'size-9' }) {
    return (
        <span
            className={cx(
                'flex items-center justify-center rounded-xl bg-brand-600 font-bold text-white shadow-card',
                className,
            )}
            aria-hidden="true"
        >
            <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M4 7.5 12 3.5l8 4-8 4-8-4Z" />
                <path d="M6.5 10.5v4.2c0 .6.3 1.1.8 1.4 1.3.8 3 1.4 4.7 1.4s3.4-.6 4.7-1.4c.5-.3.8-.8.8-1.4v-4.2" />
            </svg>
        </span>
    );
}

/*
|--------------------------------------------------------------------------
| Notifikasi flash
|--------------------------------------------------------------------------
*/

function Flash({ flash }) {
    const [visible, setVisible] = useState(null);

    useEffect(() => {
        const message = flash?.success || flash?.error;

        if (!message) {
            setVisible(null);

            return undefined;
        }

        setVisible({ type: flash.success ? 'success' : 'error', message, key: Date.now() });
        const timer = setTimeout(() => setVisible(null), 6000);

        return () => clearTimeout(timer);
    }, [flash?.success, flash?.error]);

    if (!visible) return null;

    const ok = visible.type === 'success';

    return (
        <div
            role="status"
            aria-live="polite"
            className={cx(
                'fixed inset-x-3 bottom-[calc(4.5rem+env(safe-area-inset-bottom,0px))] z-60 animate-rise',
                'sm:inset-x-auto sm:top-4 sm:right-4 sm:bottom-auto sm:max-w-sm',
            )}
        >
            <div
                className={cx(
                    'flex items-start gap-3 rounded-xl border px-4 py-3 shadow-overlay',
                    ok ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50',
                )}
            >
                <span
                    className={cx(
                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-white',
                        ok ? 'bg-emerald-600' : 'bg-rose-600',
                    )}
                >
                    <Icon name={ok ? 'check' : 'alert'} className="size-3" strokeWidth={2.5} />
                </span>
                <p className={cx('min-w-0 flex-1 text-sm font-medium', ok ? 'text-emerald-900' : 'text-rose-900')}>
                    {visible.message}
                </p>
                <button
                    type="button"
                    onClick={() => setVisible(null)}
                    aria-label="Tutup notifikasi"
                    className={cx(
                        '-m-1 shrink-0 rounded-md p-1 transition',
                        ok ? 'text-emerald-700 hover:bg-emerald-100' : 'text-rose-700 hover:bg-rose-100',
                    )}
                >
                    <Icon name="close" className="size-4" />
                </button>
            </div>
        </div>
    );
}

/*
|--------------------------------------------------------------------------
| Menu pengguna
|--------------------------------------------------------------------------
*/

function UserMenu({ user }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return undefined;

        const onClick = (e) => !ref.current?.contains(e.target) && setOpen(false);
        const onKey = (e) => e.key === 'Escape' && setOpen(false);

        document.addEventListener('mousedown', onClick);
        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('mousedown', onClick);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    return (
        <div className="relative" ref={ref}>
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-haspopup="menu"
                aria-expanded={open}
                className="flex items-center gap-2 rounded-lg py-1 pr-1 pl-1.5 transition hover:bg-line-soft sm:pr-2"
            >
                <Avatar name={user?.name} size="sm" />
                <span className="hidden min-w-0 text-left sm:block">
                    <span className="block max-w-32 truncate text-xs font-semibold text-ink">{user?.name}</span>
                    <span className="block text-[11px] text-ink-faint">{ROLE_LABEL[user?.role] ?? user?.role}</span>
                </span>
                <Icon name="chevronDown" className="hidden size-4 text-ink-faint sm:block" />
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute right-0 z-50 mt-2 w-56 animate-rise overflow-hidden rounded-xl border border-line bg-surface shadow-overlay"
                >
                    <div className="flex items-center gap-3 border-b border-line px-3 py-3">
                        <Avatar name={user?.name} size="md" />
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-ink">{user?.name}</p>
                            <p className="truncate text-xs text-ink-faint">{user?.email}</p>
                        </div>
                    </div>
                    <div className="p-1">
                        {/* Koordinator tidak memiliki halaman profil, jadi tautannya disembunyikan. */}
                        {user?.role !== 'admin' && (
                            <Link
                                href="/profil"
                                role="menuitem"
                                onClick={() => setOpen(false)}
                                className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-ink transition hover:bg-line-soft"
                            >
                                <Icon name="user" className="size-4" />
                                Profil Saya
                            </Link>
                        )}
                        <button
                            type="button"
                            role="menuitem"
                            onClick={() => router.post('/logout')}
                            className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50"
                        >
                            <Icon name="logout" className="size-4" />
                            Keluar
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

export default function AppLayout({ title, heading, subheading, actions, children, width = 'default' }) {
    const { auth, flash, appName } = usePage().props;
    const current = usePage().url;
    const [drawer, setDrawer] = useState(false);

    const menu = MENUS[auth?.user?.role] ?? [];
    const flatItems = menu.flatMap((group) => group.items);

    // Tutup laci saat berpindah halaman.
    useEffect(() => setDrawer(false), [current]);

    // Kunci gulir badan halaman selama laci terbuka.
    useEffect(() => {
        if (!drawer) return undefined;

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = previous;
        };
    }, [drawer]);

    const widths = {
        default: 'max-w-7xl',
        narrow: 'max-w-1xl',
        wide: 'max-w-none',
    };

    const nav = (
        <nav className="scrollbar-slim flex h-full flex-col gap-6 overflow-y-auto px-3 pb-6">
            <div className="sticky top-0 z-10 flex items-center gap-2.5 bg-surface pt-4 pb-3">
                <BrandMark />
                <div className="min-w-0">
                    <p className="truncate text-sm font-bold tracking-tight text-ink">{appName}</p>
                    <p className="truncate text-[11px] text-ink-faint">Booking Asisten Dosen</p>
                </div>
            </div>

            {menu.map((group, index) => (
                <div key={group.section ?? index} className="space-y-0.5">
                    {group.section && (
                        <p className="px-2.5 pb-1.5 text-[10px] font-semibold tracking-[0.08em] text-ink-faint uppercase">
                            {group.section}
                        </p>
                    )}
                    {group.items.map((item) => {
                        const active = isActive(current, item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cx(
                                    'group relative flex items-center gap-2.5 rounded-lg px-2.5 py-2',
                                    'text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-brand-50 text-brand-700'
                                        : 'text-ink-soft hover:bg-line-soft hover:text-ink',
                                )}
                            >
                                {active && (
                                    <span className="absolute top-1/2 -left-3 h-5 w-1 -translate-y-1/2 rounded-r-full bg-brand-600" />
                                )}
                                <NavIcon
                                    name={item.icon}
                                    className={cx('size-4.5 shrink-0', active ? 'text-brand-600' : 'text-ink-faint')}
                                />
                                <span className="truncate">{item.label}</span>
                            </Link>
                        );
                    })}
                </div>
            ))}
        </nav>
    );

    return (
        <div className="min-h-dvh">
            <Head title={title} />

            {/* Sidebar desktop */}
            <aside className="fixed inset-y-0 left-0 hidden w-64 border-r border-line bg-surface lg:block">{nav}</aside>

            {/* Laci navigasi mobile */}
            {drawer && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <div
                        className="absolute inset-0 animate-fade-in bg-ink/40 backdrop-blur-[2px]"
                        onClick={() => setDrawer(false)}
                        aria-hidden="true"
                    />
                    <aside className="relative h-full w-72 max-w-[82%] animate-slide-in bg-surface shadow-overlay">
                        <button
                            type="button"
                            onClick={() => setDrawer(false)}
                            aria-label="Tutup menu"
                            className="absolute top-4 right-3 z-20 rounded-lg p-1.5 text-ink-faint transition hover:bg-line-soft hover:text-ink"
                        >
                            <Icon name="close" className="size-5" />
                        </button>
                        {nav}
                    </aside>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-40 border-b border-line bg-surface/85 backdrop-blur-md">
                    <div className={cx('mx-auto flex items-center gap-3 px-4 py-3 sm:px-6', widths[width])}>
                        <button
                            type="button"
                            onClick={() => setDrawer(true)}
                            className="-ml-1.5 rounded-lg p-2 text-ink-soft transition hover:bg-line-soft hover:text-ink lg:hidden"
                            aria-label="Buka menu navigasi"
                        >
                            <Icon name="menu" className="size-5" />
                        </button>

                        <div className="min-w-0 flex-1">
                            <h1 className="truncate text-base font-semibold tracking-tight text-ink sm:text-lg">
                                {heading}
                            </h1>
                            {subheading && <p className="truncate text-xs text-ink-soft sm:text-sm">{subheading}</p>}
                        </div>

                        <div className="flex shrink-0 items-center gap-2">
                            <div className="hidden sm:flex sm:items-center sm:gap-2">{actions}</div>
                            <UserMenu user={auth?.user} />
                        </div>
                    </div>

                    {/* Aksi dipindah ke barisnya sendiri di layar sempit agar judul tidak terhimpit. */}
                    {actions && (
                        <div className="flex flex-wrap gap-2 border-t border-line-soft px-4 py-2 sm:hidden">
                            {actions}
                        </div>
                    )}
                </header>

                <main
                    className={cx(
                        'mx-auto px-4 pt-5 sm:px-6',
                        widths[width],
                        flatItems.length > 1
                            ? 'pb-[calc(5rem+env(safe-area-inset-bottom,0px))] lg:pb-10'
                            : 'pb-10',
                    )}
                >
                    {children}
                </main>
            </div>

            {/* Navigasi bawah untuk mobile: pintasan ke menu tersering. */}
            {flatItems.length > 1 && (
                <nav className="safe-bottom fixed inset-x-0 bottom-0 z-40 flex border-t border-line bg-surface/95 backdrop-blur-md lg:hidden">
                    {flatItems.slice(0, 5).map((item) => {
                        const active = isActive(current, item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cx(
                                    'flex flex-1 flex-col items-center gap-1 py-2.5 transition-colors',
                                    active ? 'text-brand-600' : 'text-ink-faint',
                                )}
                            >
                                <NavIcon name={item.icon} className="size-5" />
                                <span className="max-w-full truncate px-1 text-[10px] font-medium">
                                    {item.short ?? item.label}
                                </span>
                            </Link>
                        );
                    })}
                </nav>
            )}

            <Flash flash={flash} />
        </div>
    );
}
