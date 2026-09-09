import { Link } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import { Avatar, Badge, Card, CardHeader, EmptyState, Icon, Meter, StatCard } from '../../Components/UI';
import { availabilityOf, quotaTone } from '../../lib/status';

export default function Dashboard({ profile, pairs, practicums }) {
    const status = availabilityOf(profile.availability);

    return (
        <AppLayout
            title="Dashboard"
            heading={`Halo, ${profile.name} 👋`}
            subheading="Dashboard Asisten Dosen"
            width="narrow"
        >
            <div className="space-y-5">
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatCard
                        label="Praktikum Diampu"
                        value={`${profile.used_quota}/${profile.max_classes}`}
                        tone="brand"
                    />
                    <StatCard label="Pasangan" value={pairs.length} />
                    <StatCard label="Mata Kuliah" value={profile.courses.length} />
                    <Card className="flex flex-col justify-between p-4">
                        <p className="text-xs font-medium text-ink-soft">Ketersediaan</p>
                        <Badge tone={status.tone} dot className="mt-2 self-start">
                            {status.label}
                        </Badge>
                    </Card>
                </div>

                {/* Ringkas saja; rincian lengkap ada di halaman Profil Saya. */}
                <Card className="flex flex-wrap items-center gap-4 p-4 sm:p-5">
                    <Avatar name={profile.name} size="lg" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold text-ink">{profile.name}</p>
                        <p className="truncate text-sm text-ink-soft">
                            NIM {profile.nim}
                            {profile.phone && <span className="text-ink-faint"> · {profile.phone}</span>}
                        </p>
                    </div>
                    <Link
                        href="/profil"
                        className="inline-flex h-9.5 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-surface px-3.5 text-sm font-medium text-ink shadow-card ring-1 ring-line ring-inset transition hover:bg-canvas"
                    >
                        Lihat profil
                        <Icon name="chevronRight" className="size-4" />
                    </Link>
                </Card>

                <Card>
                    <CardHeader title="Pasangan Saya" description="Dibentuk oleh Koordinator per mata kuliah" />
                    {pairs.length === 0 ? (
                        <EmptyState
                            icon="users"
                            title="Belum ada pasangan"
                            description="Koordinator belum memasangkan Anda dengan Asdos lain."
                        />
                    ) : (
                        <ul className="divide-y divide-line-soft">
                            {pairs.map((pair) => (
                                <li key={pair.id} className="flex items-center gap-3 px-4 py-3 sm:px-5">
                                    <Avatar name={pair.partner} size="sm" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-ink">{pair.course}</p>
                                        <p className="truncate text-xs text-ink-soft">
                                            Bersama {pair.partner ?? '—'} · Sem. {pair.semester}
                                        </p>
                                    </div>
                                    <Badge tone={pair.status === 'aktif' ? 'success' : 'neutral'} dot>
                                        {pair.status === 'aktif' ? 'Aktif' : 'Nonaktif'}
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card>
                    <CardHeader
                        title="Mata Kuliah & Kuota Saya"
                        description="Kuota per mata kuliah ditetapkan Koordinator"
                    />
                    {profile.courses.length === 0 ? (
                        <EmptyState
                            icon="book"
                            title="Belum ada mata kuliah"
                            description="Koordinator belum menetapkan mata kuliah untuk Anda."
                        />
                    ) : (
                        <ul className="divide-y divide-line-soft">
                            {profile.courses.map((course) => (
                                <li key={course.id} className="px-4 py-3.5 sm:px-5">
                                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-ink">{course.name}</p>
                                            <p className="text-xs text-ink-faint">Semester {course.semester}</p>
                                        </div>
                                        <span className="shrink-0 text-xs font-medium tabular-nums text-ink-soft">
                                            {course.used}/{course.max} praktikum
                                        </span>
                                    </div>
                                    <Meter
                                        value={course.used}
                                        max={course.max}
                                        tone={quotaTone(course.used, course.max)}
                                        className="mt-2"
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card>
                    <CardHeader
                        title="Praktikum Saya"
                        description={`${profile.used_quota} dari ${profile.max_classes} kuota total terpakai`}
                    />
                    {practicums.length === 0 ? (
                        <EmptyState
                            title="Belum ada praktikum"
                            description="Praktikum akan muncul di sini setelah ketua kelas memilih pasangan Anda."
                        />
                    ) : (
                        <ul className="divide-y divide-line-soft">
                            {practicums.map((item) => (
                                <li key={item.id} className="px-4 py-4 sm:px-5">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-ink">{item.course}</p>
                                            <p className="mt-0.5 text-sm text-ink-soft">
                                                Semester {item.semester} · {item.class_label}
                                            </p>
                                        </div>
                                        <Badge tone="success" dot>
                                            Aktif
                                        </Badge>
                                    </div>

                                    <dl className="mt-3 grid grid-cols-1 gap-1.5 rounded-lg bg-canvas p-3 text-sm sm:grid-cols-2">
                                        <div className="flex gap-1.5">
                                            <dt className="text-ink-faint">Bersama</dt>
                                            <dd className="font-medium text-ink">{item.partner ?? '—'}</dd>
                                        </div>
                                        <div className="flex gap-1.5">
                                            <dt className="text-ink-faint">Ketua kelas</dt>
                                            <dd className="min-w-0 truncate font-medium text-ink">
                                                {item.representative ?? '—'}
                                                {item.representative_phone && (
                                                    <span className="font-normal text-ink-faint">
                                                        {' '}
                                                        · {item.representative_phone}
                                                    </span>
                                                )}
                                            </dd>
                                        </div>
                                    </dl>

                                    <p className="mt-2 text-xs text-ink-faint">Ditempatkan {item.booked_at}</p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
