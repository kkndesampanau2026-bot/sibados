import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Card, Checkbox, Field, Icon, Input } from '../../Components/UI';

const HIGHLIGHTS = [
    ['Satu halaman untuk semua praktikum', 'Ketua kelas memilih Asdos untuk tiap mata kuliah praktikum sekaligus.'],
    ['Kuota terjaga otomatis', 'Kuota per mata kuliah membatasi berapa kelas boleh memilih Asdos yang sama.'],
    ['Siapa cepat dia dapat', 'Slot yang sudah diambil kelas lain tidak dapat direbut.'],
];

export default function Login() {
    const { appName } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();
        post('/login');
    };

    return (
        <div className="grid min-h-dvh lg:grid-cols-2">
            <Head title="Masuk" />

            {/* Panel merek — hanya tampil pada layar lebar. */}
            <div className="relative hidden overflow-hidden bg-brand-700 p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <div
                    className="pointer-events-none absolute inset-0 opacity-30"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 20% 20%, rgba(255,255,255,.35), transparent 45%), radial-gradient(circle at 80% 70%, rgba(255,255,255,.22), transparent 40%)',
                    }}
                    aria-hidden="true"
                />

                <div className="relative flex items-center gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25">
                        <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M4 7.5 12 3.5l8 4-8 4-8-4Z" />
                            <path d="M6.5 10.5v4.2c0 .6.3 1.1.8 1.4 1.3.8 3 1.4 4.7 1.4s3.4-.6 4.7-1.4c.5-.3.8-.8.8-1.4v-4.2" />
                        </svg>
                    </span>
                    <span className="text-lg font-bold tracking-tight">{appName}</span>
                </div>

                <div className="relative max-w-md">
                    <h2 className="text-3xl leading-tight font-bold tracking-tight text-balance">
                        Pembagian Asisten Dosen yang rapi, transparan, dan terkontrol.
                    </h2>

                    <ul className="mt-8 space-y-5">
                        {HIGHLIGHTS.map(([title, desc]) => (
                            <li key={title} className="flex gap-3">
                                <span className="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-white/20">
                                    <Icon name="check" className="size-3" strokeWidth={3} />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold">{title}</p>
                                    <p className="mt-0.5 text-sm text-white/70">{desc}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <p className="relative text-xs text-white/60">Sistem Booking Asisten Dosen</p>
            </div>

            {/* Panel formulir */}
            <div className="flex items-center justify-center bg-canvas px-4 py-10 sm:px-8">
                <div className="w-full max-w-sm">
                    <div className="mb-8 text-center lg:text-left">
                        <span className="mx-auto mb-4 flex size-11 items-center justify-center rounded-xl bg-brand-600 text-white shadow-card lg:hidden">
                            <svg viewBox="0 0 24 24" className="size-6" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M4 7.5 12 3.5l8 4-8 4-8-4Z" />
                                <path d="M6.5 10.5v4.2c0 .6.3 1.1.8 1.4 1.3.8 3 1.4 4.7 1.4s3.4-.6 4.7-1.4c.5-.3.8-.8.8-1.4v-4.2" />
                            </svg>
                        </span>
                        <h1 className="text-2xl font-bold tracking-tight text-ink">Selamat datang kembali</h1>
                        <p className="mt-1.5 text-sm text-ink-soft">Masuk untuk melanjutkan ke {appName}.</p>
                    </div>

                    <Card className="p-6">
                        <form onSubmit={submit} className="space-y-4" noValidate>
                            <Field label="Email" error={errors.email} required>
                                <Input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    autoComplete="username"
                                    placeholder="nama@sibados.test"
                                    autoFocus
                                    required
                                />
                            </Field>

                            <Field label="Kata Sandi" error={errors.password} required>
                                <Input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    autoComplete="current-password"
                                    placeholder="••••••••"
                                    required
                                />
                            </Field>

                            <Checkbox
                                label="Ingat saya di perangkat ini"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                            />

                            <Button type="submit" size="lg" loading={processing} className="w-full">
                                {processing ? 'Memproses…' : 'Masuk'}
                            </Button>
                        </form>
                    </Card>

                    <p className="mt-5 text-center text-xs text-ink-faint">
                        Belum punya akun? Hubungi Koordinator Asdos — seluruh akun dibuat oleh Koordinator.
                    </p>
                </div>
            </div>
        </div>
    );
}
