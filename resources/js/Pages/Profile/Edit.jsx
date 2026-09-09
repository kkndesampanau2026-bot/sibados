import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '../../Components/AppLayout';
import {
    Avatar,
    Badge,
    Button,
    Card,
    CardHeader,
    EmptyState,
    Field,
    Input,
    Meter,
    Notice,
    StatCard,
} from '../../Components/UI';
import { ROLE_LABEL, availabilityOf, quotaTone } from '../../lib/status';

/** Ringkasan baca-saja milik Asisten Dosen. */
function AsdosContext({ context }) {
    if (!context.ready) {
        return (
            <Card>
                <EmptyState
                    icon="user"
                    title="Profil Asdos belum dibuat"
                    description="Hubungi Koordinator Asdos untuk melengkapi data Anda."
                />
            </Card>
        );
    }

    const status = availabilityOf(context.availability);

    return (
        <>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <StatCard label="Praktikum Diampu" value={`${context.used_quota}/${context.max_classes}`} tone="brand" />
                <StatCard label="Pasangan" value={context.pairs.length} />
                <StatCard label="Mata Kuliah" value={context.courses.length} />
                <Card className="flex flex-col justify-between p-4">
                    <p className="text-xs font-medium text-ink-soft">Ketersediaan</p>
                    <Badge tone={status.tone} dot className="mt-2 self-start">
                        {status.label}
                    </Badge>
                </Card>
            </div>

            <Card>
                <CardHeader
                    title="Data Akademik"
                    description="Ditetapkan Koordinator — hubungi Koordinator bila ada yang keliru"
                />
                <dl className="grid grid-cols-1 gap-4 px-4 py-4 sm:grid-cols-3 sm:px-5">
                    {[
                        ['NIM', context.nim],
                        ['Semester', context.semester],
                        ['Status', context.status === 'aktif' ? 'Aktif' : 'Nonaktif'],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-xs text-ink-faint">{label}</dt>
                            <dd className="mt-0.5 text-sm font-medium text-ink">{value ?? '—'}</dd>
                        </div>
                    ))}
                </dl>
            </Card>

            <Card>
                <CardHeader title="Pasangan Saya" description="Dibentuk Koordinator per mata kuliah" />
                {context.pairs.length === 0 ? (
                    <EmptyState
                        icon="users"
                        title="Belum ada pasangan"
                        description="Koordinator belum memasangkan Anda dengan Asdos lain."
                    />
                ) : (
                    <ul className="divide-y divide-line-soft">
                        {context.pairs.map((pair) => (
                            <li key={pair.id} className="flex items-center gap-3 px-4 py-3 sm:px-5">
                                <Avatar name={pair.partner} size="sm" />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-ink">{pair.course}</p>
                                    <p className="truncate text-xs text-ink-soft">Bersama {pair.partner ?? '—'}</p>
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
                <CardHeader title="Mata Kuliah & Kuota" description="Batas berapa kelas boleh memilih Anda" />
                {context.courses.length === 0 ? (
                    <EmptyState
                        icon="book"
                        title="Belum ada mata kuliah"
                        description="Koordinator belum menetapkan mata kuliah untuk Anda."
                    />
                ) : (
                    <ul className="divide-y divide-line-soft">
                        {context.courses.map((course) => (
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

            {context.practicums.length > 0 && (
                <Card>
                    <CardHeader title="Praktikum yang Saya Ampu" />
                    <ul className="divide-y divide-line-soft">
                        {context.practicums.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-5"
                            >
                                <span className="min-w-0 truncate text-sm font-medium text-ink">{item.course}</span>
                                <span className="shrink-0 text-xs text-ink-soft">{item.class_label}</span>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </>
    );
}

/** Ringkasan baca-saja milik Ketua Kelas. */
function RepresentativeContext({ context }) {
    if (!context.ready) {
        return (
            <Card>
                <EmptyState
                    icon="door"
                    title="Anda belum memimpin kelas"
                    description="Hubungi Koordinator Asdos agar akun Anda ditetapkan sebagai ketua sebuah kelas."
                />
            </Card>
        );
    }

    return (
        <>
            <Card className="p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <h2 className="text-lg font-semibold tracking-tight text-ink">{context.label}</h2>
                        <p className="mt-0.5 text-sm text-ink-soft">
                            Semester {context.semester} · {context.total} mata kuliah praktikum
                        </p>
                    </div>
                    <Badge tone={context.filled === context.total ? 'success' : 'warning'} dot>
                        {context.filled}/{context.total} sudah ada pasangan
                    </Badge>
                </div>
                <Meter
                    value={context.filled}
                    max={context.total}
                    tone={context.filled === context.total ? 'success' : 'brand'}
                    className="mt-4"
                />
            </Card>

            <Card>
                <CardHeader title="Praktikum Kelas Saya" description="Ditetapkan Koordinator sesuai semester kelas" />
                {context.practicums.length === 0 ? (
                    <EmptyState
                        icon="book"
                        title="Belum ada praktikum"
                        description="Koordinator belum menetapkan mata kuliah praktikum untuk kelas Anda."
                    />
                ) : (
                    <ul className="divide-y divide-line-soft">
                        {context.practicums.map((item) => (
                            <li
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 sm:px-5"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-ink">{item.course}</p>
                                    <p className="text-xs text-ink-faint">Semester {item.semester}</p>
                                </div>
                                <Badge tone={item.is_filled ? 'success' : 'danger'} dot>
                                    {item.is_filled ? 'Sudah ada pasangan' : 'Belum ada pasangan'}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </>
    );
}

export default function Edit({ profile, context }) {
    const { auth } = usePage().props;

    const identity = useForm({
        name: profile.name ?? '',
        email: profile.email ?? '',
        phone: profile.phone ?? '',
    });

    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submitIdentity = (event) => {
        event.preventDefault();
        identity.put('/profil', { preserveScroll: true });
    };

    const submitPassword = (event) => {
        event.preventDefault();
        password.put('/profil/kata-sandi', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    };

    return (
        <AppLayout title="Profil Saya" heading="Profil Saya" subheading={ROLE_LABEL[profile.role]} width="narrow">
            <div className="space-y-5">
                {/* Kartu identitas */}
                <Card className="flex flex-wrap items-center gap-4 p-5">
                    <Avatar name={profile.name} size="lg" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-lg font-semibold tracking-tight text-ink">{profile.name}</p>
                        <p className="truncate text-sm text-ink-soft">{profile.email}</p>
                    </div>
                    <Badge tone={profile.is_active ? 'success' : 'neutral'} dot>
                        {profile.is_active ? 'Akun Aktif' : 'Akun Nonaktif'}
                    </Badge>
                </Card>

                {/* Ubah identitas kontak */}
                <Card>
                    <CardHeader title="Data Diri" description="Perbarui nama dan kontak yang dapat dihubungi" />
                    <form onSubmit={submitIdentity} className="space-y-4 px-4 py-4 sm:px-5">
                        <Field label="Nama Lengkap" error={identity.errors.name} required>
                            <Input
                                value={identity.data.name}
                                onChange={(e) => identity.setData('name', e.target.value)}
                                autoComplete="name"
                                required
                            />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Email"
                                error={identity.errors.email}
                                hint="Dipakai untuk masuk ke sistem"
                                required
                            >
                                <Input
                                    type="email"
                                    value={identity.data.email}
                                    onChange={(e) => identity.setData('email', e.target.value)}
                                    autoComplete="email"
                                    required
                                />
                            </Field>

                            <Field
                                label="Nomor HP"
                                error={identity.errors.phone}
                                hint="Agar mudah dihubungi saat praktikum"
                            >
                                <Input
                                    type="tel"
                                    value={identity.data.phone}
                                    onChange={(e) => identity.setData('phone', e.target.value)}
                                    autoComplete="tel"
                                    placeholder="08xxxxxxxxxx"
                                />
                            </Field>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" loading={identity.processing}>
                                {identity.processing ? 'Menyimpan…' : 'Simpan Perubahan'}
                            </Button>
                        </div>
                    </form>
                </Card>

                {/* Ganti kata sandi */}
                <Card>
                    <CardHeader title="Kata Sandi" description="Gunakan kata sandi yang tidak dipakai di tempat lain" />
                    <form onSubmit={submitPassword} className="space-y-4 px-4 py-4 sm:px-5">
                        <Field label="Kata Sandi Saat Ini" error={password.errors.current_password} required>
                            <Input
                                type="password"
                                value={password.data.current_password}
                                onChange={(e) => password.setData('current_password', e.target.value)}
                                autoComplete="current-password"
                                required
                            />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Kata Sandi Baru"
                                error={password.errors.password}
                                hint="Minimal 8 karakter"
                                required
                            >
                                <Input
                                    type="password"
                                    value={password.data.password}
                                    onChange={(e) => password.setData('password', e.target.value)}
                                    autoComplete="new-password"
                                    required
                                />
                            </Field>

                            <Field label="Ulangi Kata Sandi Baru" error={password.errors.password_confirmation} required>
                                <Input
                                    type="password"
                                    value={password.data.password_confirmation}
                                    onChange={(e) => password.setData('password_confirmation', e.target.value)}
                                    autoComplete="new-password"
                                    required
                                />
                            </Field>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" variant="secondary" loading={password.processing}>
                                {password.processing ? 'Menyimpan…' : 'Ganti Kata Sandi'}
                            </Button>
                        </div>
                    </form>
                </Card>

                <Notice tone="neutral">
                    NIM, kuota, pasangan, dan penempatan kelas diatur oleh Koordinator Asdos. Hubungi Koordinator bila
                    ada data yang perlu diperbaiki.
                </Notice>

                {/* Ringkasan sesuai peran */}
                {auth?.user?.role === 'asdos' ? (
                    <AsdosContext context={context} />
                ) : (
                    <RepresentativeContext context={context} />
                )}
            </div>
        </AppLayout>
    );
}
