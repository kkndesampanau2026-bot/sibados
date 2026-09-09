import { Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import {
    Badge,
    Button,
    Card,
    CardHeader,
    EmptyState,
    Icon,
    Meter,
    StatCard,
    TableWrap,
    Td,
    Th,
    Tr,
} from '../../Components/UI';
import { logAction } from '../../lib/status';

function CardLink({ href, children }) {
    return (
        <Link
            href={href}
            className="text-xs font-medium text-brand-600 transition hover:text-brand-700"
        >
            {children}
        </Link>
    );
}

export default function Dashboard({ stats, emptyPracticums, recentLogs }) {
    const { booking } = usePage().props;
    const open = booking?.is_open;

    return (
        <AppLayout title="Dashboard" heading="Dashboard" subheading="Ringkasan penempatan Asisten Dosen">
            <div className="space-y-5">
                {/* Status periode booking */}
                <div
                    className={`flex flex-wrap items-center gap-x-4 gap-y-3 rounded-xl border px-4 py-3.5 ${
                        open ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'
                    }`}
                >
                    <span
                        className={`flex size-9 shrink-0 items-center justify-center rounded-lg text-white ${
                            open ? 'bg-emerald-600' : 'bg-amber-500'
                        }`}
                    >
                        <Icon name={open ? 'check' : 'lock'} className="size-4.5" strokeWidth={2} />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className={`text-sm font-semibold ${open ? 'text-emerald-900' : 'text-amber-900'}`}>
                            Periode booking {open ? 'sedang dibuka' : 'sedang ditutup'}
                        </p>
                        <p className={`text-xs ${open ? 'text-emerald-800' : 'text-amber-800'}`}>
                            {booking?.closed_reason ?? 'Ketua kelas dapat melakukan booking sekarang.'}
                        </p>
                    </div>
                    <Link href="/admin/pengaturan">
                        <Button variant="secondary" size="sm">
                            Atur periode
                        </Button>
                    </Link>
                </div>

                {/* Progres pengisian praktikum */}
                <Card className="p-5">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-sm font-semibold tracking-tight text-ink">Progres Pengisian Praktikum</h2>
                        <span className="text-sm font-medium tabular-nums text-ink-soft">
                            {stats.practicums_filled} dari {stats.total_practicums} praktikum terisi
                        </span>
                    </div>
                    <Meter
                        value={stats.practicums_filled}
                        max={stats.total_practicums}
                        tone={stats.practicums_empty === 0 ? 'success' : 'brand'}
                        className="mt-3 h-2"
                    />
                </Card>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
                    <StatCard label="Mata Kuliah" value={stats.total_courses} />
                    <StatCard label="Kelas" value={stats.total_classes} />
                    <StatCard label="Praktikum" value={stats.total_practicums} tone="brand" />
                    <StatCard label="Asdos" value={stats.total_asdos} />
                    <StatCard label="Pasangan" value={stats.total_pairs} />
                    <StatCard label="Booking Aktif" value={stats.active_bookings} tone="brand" />
                    <StatCard label="Praktikum Terisi" value={stats.practicums_filled} tone="success" />
                    <StatCard label="Belum Terisi" value={stats.practicums_empty} tone="danger" />
                    <StatCard label="Asdos Tersedia" value={stats.asdos_available} tone="success" />
                    <StatCard label="Asdos Penuh" value={stats.asdos_full} tone="warning" />
                </div>

                <div className="grid gap-5 xl:grid-cols-2">
                    <Card>
                        <CardHeader
                            title="Praktikum Belum Memiliki Asdos"
                            description="Perlu tindak lanjut Koordinator"
                            action={<CardLink href="/admin/praktikum-belum-terisi">Lihat semua</CardLink>}
                        />
                        {emptyPracticums.length === 0 ? (
                            <EmptyState
                                icon="check"
                                title="Semua praktikum sudah terisi"
                                description="Tidak ada praktikum yang menunggu penempatan."
                            />
                        ) : (
                            <TableWrap>
                                <thead>
                                    <tr>
                                        <Th>Mata Kuliah</Th>
                                        <Th>Kelas</Th>
                                        <Th>Ketua Kelas</Th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {emptyPracticums.map((item) => (
                                        <Tr key={item.id}>
                                            <Td>
                                                <span className="font-medium text-ink">{item.course}</span>
                                                <span className="block text-xs text-ink-faint">
                                                    Semester {item.semester}
                                                </span>
                                            </Td>
                                            <Td className="whitespace-nowrap">{item.class_label}</Td>
                                            <Td className="text-ink-soft">{item.representative ?? '—'}</Td>
                                        </Tr>
                                    ))}
                                </tbody>
                            </TableWrap>
                        )}
                    </Card>

                    <Card>
                        <CardHeader
                            title="Aktivitas Terakhir"
                            description="Riwayat booking dan perubahan"
                            action={<CardLink href="/admin/riwayat">Riwayat lengkap</CardLink>}
                        />
                        {recentLogs.length === 0 ? (
                            <EmptyState
                                icon="clock"
                                title="Belum ada aktivitas"
                                description="Aktivitas booking akan tampil di sini."
                            />
                        ) : (
                            <ul className="divide-y divide-line-soft">
                                {recentLogs.map((log) => {
                                    const action = logAction(log.action);

                                    return (
                                        <li key={log.id} className="flex flex-wrap items-start gap-2.5 px-4 py-3 sm:px-5">
                                            <Badge tone={action.tone} dot className="mt-0.5">
                                                {action.label}
                                            </Badge>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm text-ink">{log.description}</p>
                                                <p className="mt-0.5 text-xs text-ink-faint">
                                                    {log.user} · {log.at}
                                                </p>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
