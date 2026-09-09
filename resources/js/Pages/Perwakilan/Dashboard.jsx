import { Link, usePage } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import CountdownBanner from '../../Components/CountdownBanner';
import { AvatarStack, Badge, Card, EmptyState, Icon, Meter, SectionTitle } from '../../Components/UI';

export default function Dashboard({ classRoom, practicums }) {
    const { auth, booking } = usePage().props;

    return (
        <AppLayout
            title="Praktikum Kelas Saya"
            heading={`Halo, ${auth?.user?.name ?? 'Ketua Kelas'} 👋`}
            subheading={classRoom ? `${classRoom.label} · Semester ${classRoom.semester}` : 'Ketua Kelas'}
            width="narrow"
        >
            <div className="space-y-5">
                <CountdownBanner booking={booking} />

                {!classRoom ? (
                    <Card>
                        <EmptyState
                            icon="user"
                            title="Anda belum terdaftar sebagai ketua kelas"
                            description="Hubungi Koordinator Asdos agar akun Anda ditetapkan sebagai ketua sebuah kelas."
                        />
                    </Card>
                ) : (
                    <>
                        {/* Ringkasan progres kelas */}
                        <Card className="p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="text-lg font-semibold tracking-tight text-ink">{classRoom.label}</h2>
                                    <p className="mt-0.5 text-sm text-ink-soft">
                                        Semester {classRoom.semester} · {classRoom.total} mata kuliah praktikum
                                    </p>
                                </div>
                                <Badge
                                    tone={classRoom.filled === classRoom.total ? 'success' : 'warning'}
                                    dot
                                    className="shrink-0"
                                >
                                    {classRoom.filled}/{classRoom.total} sudah ada pasangan
                                </Badge>
                            </div>

                            <Meter
                                value={classRoom.filled}
                                max={classRoom.total}
                                tone={classRoom.filled === classRoom.total ? 'success' : 'brand'}
                                className="mt-4"
                            />
                        </Card>

                        <SectionTitle hint="Siapa cepat dia dapat">Pilih pasangan Asdos</SectionTitle>

                        {practicums.length === 0 ? (
                            <Card>
                                <EmptyState
                                    title="Belum ada praktikum"
                                    description="Koordinator belum menetapkan mata kuliah praktikum untuk kelas Anda."
                                />
                            </Card>
                        ) : (
                            <div className="space-y-3">
                                {practicums.map((item) => (
                                    <Card key={item.id} className="p-4 sm:p-5">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <h3 className="font-semibold text-ink">{item.course}</h3>
                                                <p className="mt-0.5 text-xs text-ink-soft">
                                                    Semester {item.semester}
                                                </p>
                                            </div>
                                            <Badge tone={item.is_filled ? 'success' : 'danger'} dot>
                                                {item.is_filled ? 'Sudah ada pasangan' : 'Belum ada pasangan'}
                                            </Badge>
                                        </div>

                                        {item.pairs.length > 0 && (
                                            <ul className="mt-4 space-y-2.5 rounded-lg bg-canvas p-3">
                                                {item.pairs.map((pair, index) => (
                                                    <li key={index} className="flex flex-wrap items-center gap-3">
                                                        <AvatarStack names={pair.members} size="sm" />
                                                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-ink">
                                                            {pair.label}
                                                        </span>
                                                        <span className="text-xs text-ink-faint">{pair.booked_at}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                        <div className="mt-4">
                                            {item.is_locked ? (
                                                <p className="flex items-center gap-2 text-sm text-ink-soft">
                                                    <Icon name="lock" className="size-4 shrink-0 text-ink-faint" />
                                                    Dikunci Koordinator. Hubungi Koordinator untuk perubahan.
                                                </p>
                                            ) : item.is_filled ? (
                                                <p className="text-sm text-ink-soft">
                                                    Praktikum ini sudah mendapatkan pasangan Asdos. Hubungi Koordinator
                                                    untuk perubahan.
                                                </p>
                                            ) : (
                                                <Link
                                                    href={`/perwakilan/praktikum/${item.id}/booking`}
                                                    className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 text-sm font-medium text-white shadow-card transition hover:bg-brand-700 active:bg-brand-800 sm:w-auto"
                                                >
                                                    Pilih Pasangan Asdos
                                                    <Icon name="chevronRight" className="size-4" />
                                                </Link>
                                            )}
                                        </div>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
