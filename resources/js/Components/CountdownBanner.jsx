import { useEffect, useState } from 'react';
import { Icon, cx } from './UI';
import { countdownParts } from '../lib/status';

/** Countdown periode booking, dengan penanda buka/tutup yang jelas. */
export default function CountdownBanner({ booking }) {
    const target = booking?.is_open ? booking?.ends_at : booking?.starts_at;
    const [parts, setParts] = useState(() => (target ? countdownParts(target) : null));

    useEffect(() => {
        if (!target) {
            setParts(null);

            return undefined;
        }

        setParts(countdownParts(target));
        const timer = setInterval(() => setParts(countdownParts(target)), 1000);

        return () => clearInterval(timer);
    }, [target]);

    const open = booking?.is_open;

    return (
        <div
            className={cx(
                'flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border px-4 py-3.5',
                open ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50',
            )}
        >
            <span
                className={cx(
                    'flex size-9 shrink-0 items-center justify-center rounded-lg text-white',
                    open ? 'bg-emerald-600' : 'bg-amber-500',
                )}
            >
                <Icon name={open ? 'check' : 'lock'} className="size-4.5" strokeWidth={2} />
            </span>

            <div className="min-w-0 flex-1">
                <p className={cx('text-sm font-semibold', open ? 'text-emerald-900' : 'text-amber-900')}>
                    {open ? 'Booking sedang dibuka' : 'Booking sedang ditutup'}
                </p>
                <p className={cx('text-xs', open ? 'text-emerald-800' : 'text-amber-800')}>
                    {booking?.closed_reason ?? 'Silakan pilih pasangan Asdos untuk praktikum kelas Anda.'}
                </p>
            </div>

            {parts && (
                <div
                    className="flex gap-1.5"
                    aria-label={open ? 'Sisa waktu booking' : 'Booking dibuka dalam'}
                >
                    {[
                        ['Hari', parts.hari],
                        ['Jam', parts.jam],
                        ['Mnt', parts.menit],
                        ['Dtk', parts.detik],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="min-w-11 rounded-lg border border-white/70 bg-white/80 px-2 py-1 text-center shadow-card"
                        >
                            <p className="text-sm font-semibold tabular-nums text-ink">
                                {String(value).padStart(2, '0')}
                            </p>
                            <p className="text-[10px] tracking-wide text-ink-faint uppercase">{label}</p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
