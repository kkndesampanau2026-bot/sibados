import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Components/AppLayout';
import CountdownBanner from '../../Components/CountdownBanner';
import {
    Avatar,
    AvatarStack,
    Badge,
    Button,
    Card,
    DescList,
    EmptyState,
    Icon,
    Meter,
    Modal,
    Notice,
    SectionTitle,
    cx,
} from '../../Components/UI';
import { availabilityOf, isBookable, quotaTone } from '../../lib/status';

/** Detail satu Asdos: identitas dan kontak, agar ketua kelas dapat menghubunginya. */
function AsdosDetailModal({ asdos, onClose }) {
    if (!asdos) return null;

    const status = availabilityOf(asdos.status);

    return (
        <Modal open={Boolean(asdos)} onClose={onClose} title="Detail Asisten Dosen">
            <div className="flex items-center gap-3">
                <Avatar name={asdos.name} size="lg" />
                <div className="min-w-0">
                    <p className="truncate font-semibold text-ink">{asdos.name}</p>
                    <p className="truncate text-xs text-ink-soft">NIM {asdos.nim}</p>
                </div>
                <Badge tone={status.tone} dot className="ml-auto shrink-0">
                    {status.label}
                </Badge>
            </div>

            <dl className="mt-5 space-y-3">
                {[
                    ['idCard', 'NIM', asdos.nim, null],
                    ['mail', 'Email', asdos.email, asdos.email ? `mailto:${asdos.email}` : null],
                    ['phone', 'Nomor HP', asdos.phone, asdos.phone ? `tel:${asdos.phone}` : null],
                ].map(([icon, label, value, href]) => (
                    <div key={label} className="flex items-start gap-3">
                        <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-line-soft text-ink-faint">
                            <Icon name={icon} className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <dt className="text-xs text-ink-faint">{label}</dt>
                            <dd className="truncate text-sm font-medium text-ink">
                                {value ? (
                                    href ? (
                                        <a href={href} className="text-brand-600 transition hover:text-brand-700">
                                            {value}
                                        </a>
                                    ) : (
                                        value
                                    )
                                ) : (
                                    <span className="text-ink-faint">—</span>
                                )}
                            </dd>
                        </div>
                    </div>
                ))}
            </dl>

            <div className="mt-5 rounded-xl border border-line bg-canvas p-3">
                <div className="flex items-baseline justify-between gap-2">
                    <span className="text-xs font-medium text-ink-soft">Kuota mata kuliah ini</span>
                    <span className="text-xs font-medium tabular-nums text-ink">
                        {asdos.course_used}/{asdos.course_max} praktikum
                    </span>
                </div>
                <Meter
                    value={asdos.course_used}
                    max={asdos.course_max}
                    tone={quotaTone(asdos.course_used, asdos.course_max)}
                    className="mt-2"
                />
                <p className="mt-2 text-xs text-ink-faint">
                    Kuota total lintas mata kuliah: {asdos.total_used}/{asdos.total_max} praktikum
                </p>
            </div>
        </Modal>
    );
}

export default function Booking({ practicum, pairs }) {
    const { booking } = usePage().props;
    const [selected, setSelected] = useState(null);
    const [detail, setDetail] = useState(null);
    const [processing, setProcessing] = useState(false);

    const locked = practicum.is_locked || practicum.is_filled || !booking?.is_open;

    const lockReason = practicum.is_locked
        ? 'Praktikum ini dikunci Koordinator. Hubungi Koordinator untuk perubahan.'
        : practicum.is_filled
          ? 'Praktikum ini sudah mendapatkan pasangan Asdos. Hubungi Koordinator untuk perubahan.'
          : booking?.closed_reason;

    const confirm = () => {
        setProcessing(true);
        router.post(
            `/perwakilan/praktikum/${practicum.id}/booking`,
            { pair_id: selected.id },
            {
                onFinish: () => {
                    setProcessing(false);
                    setSelected(null);
                },
            },
        );
    };

    return (
        <AppLayout
            title="Pilih Pasangan Asdos"
            heading="Pilih Pasangan Asdos"
            subheading={`${practicum.course} · ${practicum.class_label}`}
            width="narrow"
        >
            <div className="space-y-5">
                <Link
                    href="/perwakilan/dashboard"
                    className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 transition hover:text-brand-700"
                >
                    <Icon name="chevronRight" className="size-4 rotate-180" />
                    Kembali ke praktikum kelas saya
                </Link>

                <CountdownBanner booking={booking} />

                <Card>
                    <DescList
                        columns={4}
                        items={[
                            ['Mata Kuliah', practicum.course],
                            ['Semester', practicum.semester],
                            ['Kelas', practicum.class_label],
                            ['Kuota Pasangan', `${practicum.filled} / ${practicum.max_asdos}`],
                        ]}
                    />

                    {practicum.current.length > 0 && (
                        <div className="border-t border-line bg-canvas px-4 py-3 text-sm text-ink-soft sm:px-5">
                            Pasangan praktikum ini:{' '}
                            <span className="font-medium text-ink">
                                {practicum.current.map((p) => p.label).join(', ')}
                            </span>
                        </div>
                    )}
                </Card>

                {locked && <Notice tone="warning" icon={practicum.is_locked ? 'lock' : undefined}>{lockReason}</Notice>}

                <div className="space-y-3">
                    <SectionTitle hint="Siapa cepat dia dapat">Pasangan yang tersedia</SectionTitle>

                    <p className="text-xs text-ink-soft">
                        Ketuk nama Asdos untuk melihat detail dan kontaknya. Angka di sebelahnya adalah pemakaian kuota
                        mereka untuk mata kuliah ini — bila kuota salah satu anggota habis, pasangannya tidak dapat
                        dipilih lagi.
                    </p>

                    {pairs.length === 0 ? (
                        <Card>
                            <EmptyState
                                icon="users"
                                title="Belum ada pasangan Asdos"
                                description="Koordinator belum membentuk pasangan Asdos untuk mata kuliah ini."
                            />
                        </Card>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            {pairs.map((pair) => {
                                const status = availabilityOf(pair.availability);
                                const selectable = !locked && isBookable(pair.availability);

                                return (
                                    <Card
                                        key={pair.id}
                                        className={cx(
                                            'flex flex-col p-4 transition',
                                            selectable ? 'hover:border-brand-300 hover:shadow-raised' : 'opacity-75',
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <AvatarStack names={pair.members.map((m) => m.name)} size="lg" />
                                            <Badge tone={status.tone} dot>
                                                {status.label}
                                            </Badge>
                                        </div>

                                        <p className="mt-3 font-semibold text-ink">{pair.label}</p>

                                        <ul className="mt-3 space-y-2">
                                            {pair.members.map((m) => (
                                                <li key={m.id}>
                                                    <button
                                                        type="button"
                                                        onClick={() => setDetail(m)}
                                                        className="group flex w-full items-baseline justify-between gap-2 rounded-md py-0.5 text-left transition hover:bg-line-soft"
                                                        aria-label={`Lihat detail ${m.name}`}
                                                    >
                                                        <span className="flex min-w-0 items-center gap-1 text-xs">
                                                            <span className="truncate text-ink-soft group-hover:text-brand-700">
                                                                {m.name}
                                                            </span>
                                                            <Icon
                                                                name="info"
                                                                className="size-3.5 shrink-0 text-ink-faint group-hover:text-brand-600"
                                                            />
                                                        </span>
                                                        <span
                                                            className="shrink-0 text-xs font-medium tabular-nums text-ink-soft"
                                                            title={`Kuota mata kuliah ${m.course_used}/${m.course_max} · kuota total ${m.total_used}/${m.total_max}`}
                                                        >
                                                            {m.course_used}/{m.course_max}
                                                        </span>
                                                    </button>
                                                    <Meter
                                                        value={m.course_used}
                                                        max={m.course_max}
                                                        tone={quotaTone(m.course_used, m.course_max)}
                                                        className="mt-1 h-1"
                                                    />
                                                </li>
                                            ))}
                                        </ul>

                                        <Button
                                            size="lg"
                                            className="mt-4 w-full"
                                            disabled={!selectable}
                                            onClick={() => setSelected(pair)}
                                        >
                                            {selectable ? 'Booking Pasangan Ini' : status.label}
                                        </Button>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>

            <AsdosDetailModal asdos={detail} onClose={() => setDetail(null)} />

            <Modal
                open={Boolean(selected)}
                onClose={() => !processing && setSelected(null)}
                title="Konfirmasi Booking"
                footer={
                    <>
                        <Button variant="secondary" disabled={processing} onClick={() => setSelected(null)}>
                            Batal
                        </Button>
                        <Button onClick={confirm} loading={processing}>
                            {processing ? 'Memproses…' : 'Ya, Booking'}
                        </Button>
                    </>
                }
            >
                {selected && (
                    <>
                        <div className="flex items-center gap-3 rounded-xl border border-line bg-canvas p-3">
                            <AvatarStack names={selected.members.map((m) => m.name)} size="md" />
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-ink">{selected.label}</p>
                                <p className="truncate text-xs text-ink-soft">{practicum.course}</p>
                            </div>
                        </div>

                        <ul className="mt-3 divide-y divide-line-soft rounded-xl border border-line">
                            {selected.members.map((m) => (
                                <li key={m.id} className="px-3 py-2.5">
                                    <p className="text-sm font-medium text-ink">{m.name}</p>
                                    <p className="mt-0.5 text-xs text-ink-soft">
                                        NIM {m.nim}
                                        {m.phone && <> · {m.phone}</>}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </>
                )}

                <p className="mt-4 text-sm text-ink">
                    Yakin memilih pasangan <strong>{selected?.label}</strong> sebagai Asdos praktikum{' '}
                    <strong>{practicum.course}</strong> untuk <strong>{practicum.class_label}</strong>?
                </p>
                <p className="mt-2 text-xs text-ink-soft">
                    Kedua Asdos akan ditempatkan bersama pada praktikum ini. Setelah booking berhasil, hanya Koordinator
                    yang dapat mengubahnya.
                </p>
            </Modal>
        </AppLayout>
    );
}
