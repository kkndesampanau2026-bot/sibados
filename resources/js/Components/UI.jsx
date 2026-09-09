import { useEffect, useId } from 'react';

export function cx(...parts) {
    return parts.filter(Boolean).join(' ');
}

/*
|--------------------------------------------------------------------------
| Ikon
|--------------------------------------------------------------------------
| Set kecil ikon garis 24px, cukup untuk seluruh kebutuhan antarmuka.
*/

const ICON_PATHS = {
    close: 'M6 6l12 12M18 6L6 18',
    menu: 'M4 7h16M4 12h16M4 17h16',
    search: 'M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm10 2-4.35-4.35',
    plus: 'M12 5v14M5 12h14',
    check: 'M4 12.5 9 17.5 20 6.5',
    chevronDown: 'm6 9 6 6 6-6',
    chevronRight: 'm9 6 6 6-6 6',
    logout: 'M15 17l5-5-5-5M20 12H9M12 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6',
    inbox: 'M3 12h5l2 3h4l2-3h5M5 5h14l2 7v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5l2-7Z',
    lock: 'M6 11V8a6 6 0 1 1 12 0v3M5 11h14v10H5V11Z',
    alert: 'M12 9v4m0 4h.01M10.3 4.3 2.6 17.6A2 2 0 0 0 4.3 20.6h15.4a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z',
    info: 'M12 16v-4m0-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    trash: 'M4 7h16M10 11v6m4-6v6M5 7l1 13h12l1-13M9 7V4h6v3',
    user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0',
    users: 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7.5 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2.5 20a6.5 6.5 0 0 1 13 0m2-6a6 6 0 0 1 4 6',
    book: 'M5 4.5A1.5 1.5 0 0 1 6.5 3H19v14H6.5A1.5 1.5 0 0 0 5 18.5v-14ZM5 18.5A1.5 1.5 0 0 0 6.5 20H19',
    door: 'M6 3h10a1 1 0 0 1 1 1v17H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm7.5 8.5h.01M4 21h16',
    clock: 'M12 7v5l3.5 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    unlock: 'M6 11V8a6 6 0 0 1 11.5-2.5M5 11h14v10H5V11Z',
    sparkle: 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z',
    download: 'M12 3v12m0 0 4-4m-4 4-4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2',
    mail: 'M3 7l9 6 9-6M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z',
    phone: 'M6.5 3h3l1.5 4-2 1.5a12 12 0 0 0 5.5 5.5L16 12l4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4 6.2 2 2 0 0 1 6 4V3Z',
    idCard: 'M3 6h18v12H3V6Zm5 3.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM5.5 16a2.5 2.5 0 0 1 5 0M14 9.5h4M14 13h4',
};

export function Icon({ name, className = 'size-5', strokeWidth = 1.75, ...props }) {
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            {...props}
        >
            <path d={ICON_PATHS[name] ?? ICON_PATHS.info} />
        </svg>
    );
}

/*
|--------------------------------------------------------------------------
| Permukaan
|--------------------------------------------------------------------------
*/

export function Card({ children, className = '', as: Tag = 'div', ...props }) {
    return (
        <Tag
            className={cx(
                'rounded-xl border border-line bg-surface shadow-card',
                'overflow-hidden',
                className,
            )}
            {...props}
        >
            {children}
        </Tag>
    );
}

export function CardHeader({ title, description, action, className = '' }) {
    return (
        <div
            className={cx(
                'flex flex-wrap items-start justify-between gap-3 border-b border-line px-4 py-3.5 sm:px-5',
                className,
            )}
        >
            <div className="min-w-0">
                <h2 className="text-sm font-semibold tracking-tight text-ink">{title}</h2>
                {description && <p className="mt-0.5 text-xs text-ink-soft">{description}</p>}
            </div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

/** Judul bagian di dalam halaman, di luar kartu. */
export function SectionTitle({ children, hint }) {
    return (
        <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h2 className="text-sm font-semibold tracking-tight text-ink">{children}</h2>
            {hint && <span className="text-xs text-ink-faint">{hint}</span>}
        </div>
    );
}

/*
|--------------------------------------------------------------------------
| Tombol
|--------------------------------------------------------------------------
*/

const BUTTON_VARIANTS = {
    primary: 'bg-brand-600 text-white shadow-card hover:bg-brand-700 active:bg-brand-800',
    secondary: 'bg-surface text-ink ring-1 ring-inset ring-line shadow-card hover:bg-canvas active:bg-line-soft',
    danger: 'bg-rose-600 text-white shadow-card hover:bg-rose-700 active:bg-rose-800',
    ghost: 'text-ink-soft hover:bg-line-soft hover:text-ink active:bg-line',
    subtle: 'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200',
};

const BUTTON_SIZES = {
    sm: 'h-8 gap-1.5 px-2.5 text-xs rounded-lg',
    md: 'h-9.5 gap-2 px-3.5 text-sm rounded-lg',
    lg: 'h-11 gap-2 px-5 text-sm rounded-xl',
};

export function Button({
    variant = 'primary',
    size = 'md',
    className = '',
    type = 'button',
    loading = false,
    disabled = false,
    children,
    ...props
}) {
    return (
        <button
            type={type}
            disabled={disabled || loading}
            className={cx(
                'inline-flex shrink-0 items-center justify-center whitespace-nowrap font-medium',
                'transition-[background-color,box-shadow,color] duration-150',
                'disabled:pointer-events-none disabled:opacity-45',
                BUTTON_SIZES[size],
                BUTTON_VARIANTS[variant],
                className,
            )}
            {...props}
        >
            {loading && <Spinner className="size-3.5" />}
            {children}
        </button>
    );
}

export function Spinner({ className = 'size-4' }) {
    return (
        <svg className={cx('animate-spin', className)} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="3" opacity="0.25" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

/*
|--------------------------------------------------------------------------
| Badge & status
|--------------------------------------------------------------------------
*/

const TONES = {
    neutral: 'bg-line-soft text-ink-soft ring-line',
    brand: 'bg-brand-50 text-brand-700 ring-brand-200',
    success: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    warning: 'bg-amber-50 text-amber-800 ring-amber-200',
    danger: 'bg-rose-50 text-rose-700 ring-rose-200',
    info: 'bg-sky-50 text-sky-700 ring-sky-200',
};

const DOT_TONES = {
    neutral: 'bg-ink-faint',
    brand: 'bg-brand-500',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    info: 'bg-sky-500',
};

export function Badge({ tone = 'neutral', dot = false, className = '', children }) {
    return (
        <span
            className={cx(
                'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5',
                'text-xs font-medium ring-1 ring-inset',
                TONES[tone] ?? TONES.neutral,
                className,
            )}
        >
            {dot && <span className={cx('size-1.5 shrink-0 rounded-full', DOT_TONES[tone] ?? DOT_TONES.neutral)} />}
            {children}
        </span>
    );
}

/** Bilah kemajuan tipis, dipakai untuk kuota dan progres pengisian. */
export function Meter({ value, max = 1, tone = 'brand', className = '' }) {
    const pct = Math.min(100, Math.max(0, (value / Math.max(1, max)) * 100));
    const bar = {
        brand: 'bg-brand-500',
        success: 'bg-emerald-500',
        warning: 'bg-amber-500',
        danger: 'bg-rose-500',
        neutral: 'bg-ink-faint',
    };

    return (
        <div
            className={cx('h-1.5 overflow-hidden rounded-full bg-line-soft', className)}
            role="progressbar"
            aria-valuenow={value}
            aria-valuemin={0}
            aria-valuemax={max}
        >
            <div
                className={cx('h-full rounded-full transition-[width] duration-500', bar[tone] ?? bar.brand)}
                style={{ width: `${pct}%` }}
            />
        </div>
    );
}

/** Avatar inisial dengan warna deterministik dari nama. */
export function Avatar({ name, size = 'md', className = '' }) {
    const sizes = {
        xs: 'size-6 text-[10px]',
        sm: 'size-7 text-xs',
        md: 'size-9 text-sm',
        lg: 'size-11 text-base',
    };

    const palettes = [
        'bg-brand-100 text-brand-700',
        'bg-emerald-100 text-emerald-700',
        'bg-amber-100 text-amber-800',
        'bg-sky-100 text-sky-700',
        'bg-rose-100 text-rose-700',
        'bg-violet-100 text-violet-700',
    ];

    const seed = String(name ?? '?')
        .split('')
        .reduce((acc, ch) => acc + ch.charCodeAt(0), 0);

    return (
        <span
            title={name}
            className={cx(
                'inline-flex shrink-0 items-center justify-center rounded-full font-semibold uppercase',
                sizes[size],
                palettes[seed % palettes.length],
                className,
            )}
        >
            {String(name ?? '?').charAt(0)}
        </span>
    );
}

/** Tumpukan avatar untuk menampilkan pasangan Asdos. */
export function AvatarStack({ names = [], size = 'md' }) {
    return (
        <span className="flex -space-x-2">
            {names.map((name, i) => (
                <Avatar key={i} name={name} size={size} className="ring-2 ring-surface" />
            ))}
        </span>
    );
}

/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

export function Field({ label, error, hint, required, className = '', children }) {
    return (
        <label className={cx('block', className)}>
            {label && (
                <span className="mb-1.5 block text-xs font-medium text-ink-soft">
                    {label}
                    {required && <span className="text-rose-600"> *</span>}
                </span>
            )}
            {children}
            {hint && !error && <span className="mt-1.5 block text-xs text-ink-faint">{hint}</span>}
            {error && (
                <span className="mt-1.5 flex items-start gap-1 text-xs font-medium text-rose-600">
                    <Icon name="alert" className="mt-px size-3.5 shrink-0" />
                    {error}
                </span>
            )}
        </label>
    );
}

const CONTROL = cx(
    'block w-full rounded-lg border-0 bg-surface px-3 text-sm text-ink shadow-card',
    'ring-1 ring-inset ring-line placeholder:text-ink-faint',
    'transition-shadow focus:ring-2 focus:ring-inset focus:ring-brand-500 focus:outline-none',
    'disabled:cursor-not-allowed disabled:bg-line-soft disabled:text-ink-faint',
);

const CONTROL_SIZES = {
    sm: 'h-8 text-xs',
    md: 'h-9.5 text-sm',
};

export function Input({ size = 'md', className = '', ...props }) {
    return <input className={cx(CONTROL, CONTROL_SIZES[size] ?? CONTROL_SIZES.md, className)} {...props} />;
}

/**
 * `className` sengaja diterapkan pada pembungkus, bukan pada <select>, karena
 * pemanggil memakainya untuk mengatur lebar — dan ikon chevron diposisikan
 * relatif terhadap pembungkus itu.
 */
export function Select({ size = 'md', className = '', children, ...props }) {
    return (
        <div className={cx('relative', className)}>
            <select
                className={cx(CONTROL, CONTROL_SIZES[size] ?? CONTROL_SIZES.md, 'w-full appearance-none pr-9')}
                {...props}
            >
                {children}
            </select>
            <Icon
                name="chevronDown"
                className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-ink-faint"
            />
        </div>
    );
}

export function Textarea({ className = '', rows = 3, ...props }) {
    return <textarea className={cx(CONTROL, 'py-2', className)} rows={rows} {...props} />;
}

export function Checkbox({ label, description, className = '', ...props }) {
    const id = useId();

    return (
        <div className={cx('flex items-start gap-2.5', className)}>
            <input
                id={id}
                type="checkbox"
                className={cx(
                    'mt-0.5 size-4 shrink-0 rounded border-line text-brand-600',
                    'transition focus:ring-2 focus:ring-brand-500 focus:ring-offset-0',
                )}
                {...props}
            />
            <label htmlFor={id} className="min-w-0 cursor-pointer select-none text-sm leading-5 text-ink">
                {label}
                {description && <span className="block text-xs text-ink-faint">{description}</span>}
            </label>
        </div>
    );
}

/** Saklar untuk pengaturan on/off. */
export function Switch({ checked, onChange, label, description, id: providedId }) {
    const fallbackId = useId();
    const id = providedId ?? fallbackId;

    return (
        <div className="flex items-start justify-between gap-4">
            <label htmlFor={id} className="min-w-0 cursor-pointer select-none">
                <span className="block text-sm font-medium text-ink">{label}</span>
                {description && <span className="mt-0.5 block text-xs text-ink-soft">{description}</span>}
            </label>
            <button
                id={id}
                type="button"
                role="switch"
                aria-checked={checked}
                onClick={() => onChange(!checked)}
                className={cx(
                    'relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full',
                    'transition-colors duration-200',
                    checked ? 'bg-brand-600' : 'bg-line',
                )}
            >
                <span
                    className={cx(
                        'pointer-events-none absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow-card',
                        'transition-transform duration-200',
                        checked ? 'translate-x-5' : 'translate-x-0',
                    )}
                />
            </button>
        </div>
    );
}

/*
|--------------------------------------------------------------------------
| Modal
|--------------------------------------------------------------------------
| Di layar kecil tampil sebagai bottom sheet, di layar besar sebagai dialog
| tengah. Menutup lewat tombol, klik latar, atau Escape.
*/

export function Modal({ open, onClose, title, description, children, footer, size = 'md' }) {
    useEffect(() => {
        if (!open) return undefined;

        const onKey = (event) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);

        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = previous;
        };
    }, [open, onClose]);

    if (!open) return null;

    const widths = { md: 'sm:max-w-lg', lg: 'sm:max-w-2xl', xl: 'sm:max-w-4xl' };

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4">
            <div
                className="absolute inset-0 animate-fade-in bg-ink/40 backdrop-blur-[2px]"
                onClick={onClose}
                aria-hidden="true"
            />
            <div
                role="dialog"
                aria-modal="true"
                className={cx(
                    'relative z-10 flex max-h-[92dvh] w-full flex-col bg-surface shadow-overlay',
                    'animate-sheet rounded-t-2xl sm:animate-rise sm:rounded-2xl',
                    widths[size] ?? widths.md,
                )}
            >
                {/* Pegangan geser, penanda bottom sheet di layar kecil. */}
                <div className="mx-auto mt-2.5 h-1 w-9 shrink-0 rounded-full bg-line sm:hidden" />

                <div className="flex items-start justify-between gap-4 px-5 py-4">
                    <div className="min-w-0">
                        <h3 className="text-base font-semibold tracking-tight text-ink">{title}</h3>
                        {description && <p className="mt-1 text-sm text-ink-soft">{description}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="-m-1.5 shrink-0 rounded-lg p-1.5 text-ink-faint transition hover:bg-line-soft hover:text-ink"
                        aria-label="Tutup"
                    >
                        <Icon name="close" className="size-5" />
                    </button>
                </div>

                <div className="scrollbar-slim flex-1 overflow-y-auto border-t border-line px-5 py-4">{children}</div>

                {footer && (
                    <div className="safe-bottom flex flex-wrap justify-end gap-2 border-t border-line bg-canvas px-5 py-3">
                        {footer}
                    </div>
                )}
            </div>
        </div>
    );
}

/*
|--------------------------------------------------------------------------
| Tabel
|--------------------------------------------------------------------------
| Selalu dapat digulir horizontal pada layar sempit, dengan bayangan tepi
| sebagai penanda bahwa masih ada kolom di luar layar.
*/

export function TableWrap({ children, className = '' }) {
    return (
        <div className={cx('scrollbar-slim scroll-fade-x -mx-px overflow-x-auto', className)}>
            <table className="sibados-table min-w-full text-sm">{children}</table>
        </div>
    );
}

export function Th({ className = '', children, ...props }) {
    return (
        <th
            scope="col"
            className={cx(
                'border-b border-line bg-canvas px-4 py-2.5 text-left',
                'text-xs font-semibold whitespace-nowrap text-ink-soft',
                className,
            )}
            {...props}
        >
            {children}
        </th>
    );
}

export function Td({ className = '', children, ...props }) {
    return (
        <td className={cx('px-4 py-3 align-middle text-ink', className)} {...props}>
            {children}
        </td>
    );
}

export function Tr({ className = '', children, ...props }) {
    return (
        <tr className={cx('border-b border-line-soft transition-colors last:border-0 hover:bg-canvas', className)} {...props}>
            {children}
        </tr>
    );
}

/*
|--------------------------------------------------------------------------
| Status kosong & statistik
|--------------------------------------------------------------------------
*/

export function EmptyState({ icon = 'inbox', title, description, action }) {
    return (
        <div className="flex flex-col items-center px-6 py-14 text-center">
            <span className="mb-3 flex size-11 items-center justify-center rounded-full bg-line-soft text-ink-faint">
                <Icon name={icon} className="size-5" />
            </span>
            <p className="text-sm font-semibold text-ink">{title}</p>
            {description && <p className="mx-auto mt-1 max-w-sm text-sm text-ink-soft">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

const STAT_TONES = {
    neutral: 'text-ink',
    brand: 'text-brand-600',
    success: 'text-emerald-600',
    warning: 'text-amber-600',
    danger: 'text-rose-600',
};

export function StatCard({ label, value, tone = 'neutral', hint }) {
    return (
        <Card className="p-4">
            <p className="text-xs font-medium text-ink-soft">{label}</p>
            <p className={cx('mt-1.5 text-2xl leading-none font-semibold tabular-nums', STAT_TONES[tone])}>{value}</p>
            {hint && <p className="mt-1.5 text-xs text-ink-faint">{hint}</p>}
        </Card>
    );
}

/*
|--------------------------------------------------------------------------
| Papan pesan
|--------------------------------------------------------------------------
*/

const NOTICE_TONES = {
    info: { wrap: 'border-sky-200 bg-sky-50 text-sky-900', icon: 'info', mark: 'text-sky-600' },
    success: { wrap: 'border-emerald-200 bg-emerald-50 text-emerald-900', icon: 'check', mark: 'text-emerald-600' },
    warning: { wrap: 'border-amber-200 bg-amber-50 text-amber-900', icon: 'alert', mark: 'text-amber-600' },
    danger: { wrap: 'border-rose-200 bg-rose-50 text-rose-900', icon: 'alert', mark: 'text-rose-600' },
    neutral: { wrap: 'border-line bg-canvas text-ink', icon: 'info', mark: 'text-ink-faint' },
};

export function Notice({ tone = 'info', icon, className = '', children, action }) {
    const t = NOTICE_TONES[tone] ?? NOTICE_TONES.info;

    return (
        <div className={cx('flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3 text-sm', t.wrap, className)}>
            <Icon name={icon ?? t.icon} className={cx('size-5 shrink-0', t.mark)} />
            <div className="min-w-0 flex-1">{children}</div>
            {action && <div className="shrink-0">{action}</div>}
        </div>
    );
}

/** Baris alat di atas tabel: pencarian, penyaring, aksi. */
export function Toolbar({ children, className = '' }) {
    return (
        <div className={cx('flex flex-wrap items-center gap-2 border-b border-line px-3 py-3', className)}>
            {children}
        </div>
    );
}

/** Kotak pencarian dengan ikon. */
export function SearchInput({ className = '', ...props }) {
    return (
        <div className={cx('relative min-w-0 flex-1 sm:max-w-xs', className)}>
            <Icon name="search" className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-faint" />
            <Input className="pl-9" {...props} />
        </div>
    );
}

/** Pasangan label-nilai, dipakai pada panel ringkasan. */
export function DescList({ items, columns = 3, className = '' }) {
    const cols = {
        2: 'sm:grid-cols-2',
        3: 'sm:grid-cols-3',
        4: 'sm:grid-cols-2 lg:grid-cols-4',
    };

    return (
        <dl className={cx('grid grid-cols-1 gap-4 px-4 py-4 sm:px-5', cols[columns] ?? cols[3], className)}>
            {items.map(([label, value]) => (
                <div key={label} className="min-w-0">
                    <dt className="text-xs text-ink-faint">{label}</dt>
                    <dd className="mt-0.5 truncate text-sm font-medium text-ink">{value ?? '—'}</dd>
                </div>
            ))}
        </dl>
    );
}
