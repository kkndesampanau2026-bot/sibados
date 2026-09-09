import { useForm } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import CountdownBanner from '../../../Components/CountdownBanner';
import { Button, Card, CardHeader, Checkbox, Field, Input } from '../../../Components/UI';

export default function Edit({ settings, status }) {
    const form = useForm({
        booking_open: settings.booking_open,
        booking_start: settings.booking_start ?? '',
        booking_end: settings.booking_end ?? '',
        default_max_classes: settings.default_max_classes,
        default_max_asdos: settings.default_max_asdos,
        default_course_quota: settings.default_course_quota,
    });

    const submit = (event) => {
        event.preventDefault();
        form.put('/admin/pengaturan', { preserveScroll: true });
    };

    return (
        <AppLayout title="Pengaturan" heading="Pengaturan Sistem" subheading="Periode booking dan nilai bawaan">
            <div className="mx-auto max-w-2xl space-y-5">
                <CountdownBanner booking={status} />

                <Card>
                    <CardHeader
                        title="Periode Booking"
                        description="Perwakilan kelas hanya dapat melakukan booking saat periode terbuka."
                    />
                    <form onSubmit={submit} className="space-y-4 px-4 py-4 sm:px-5">
                        <Checkbox
                            label="Buka booking untuk perwakilan kelas"
                            checked={form.data.booking_open}
                            onChange={(e) => form.setData('booking_open', e.target.checked)}
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="Waktu Buka"
                                error={form.errors.booking_start}
                                hint="Kosongkan bila tanpa jadwal"
                            >
                                <Input
                                    type="datetime-local"
                                    value={form.data.booking_start}
                                    onChange={(e) => form.setData('booking_start', e.target.value)}
                                />
                            </Field>
                            <Field
                                label="Waktu Tutup"
                                error={form.errors.booking_end}
                                hint="Booking tertutup otomatis setelah waktu ini"
                            >
                                <Input
                                    type="datetime-local"
                                    value={form.data.booking_end}
                                    onChange={(e) => form.setData('booking_end', e.target.value)}
                                />
                            </Field>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label="Default Kuota Total per Asdos"
                                error={form.errors.default_max_classes}
                                hint="Batas atas lintas semua mata kuliah"
                                required
                            >
                                <Input
                                    type="number"
                                    min={1}
                                    max={20}
                                    value={form.data.default_max_classes}
                                    onChange={(e) => form.setData('default_max_classes', Number(e.target.value))}
                                    required
                                />
                            </Field>
                            <Field
                                label="Default Kuota per Mata Kuliah"
                                error={form.errors.default_course_quota}
                                hint="Batas berapa kelas boleh memilih satu Asdos di satu mata kuliah"
                                required
                            >
                                <Input
                                    type="number"
                                    min={1}
                                    max={20}
                                    value={form.data.default_course_quota}
                                    onChange={(e) => form.setData('default_course_quota', Number(e.target.value))}
                                    required
                                />
                            </Field>

                            <Field
                                label="Default Pasangan per Praktikum"
                                error={form.errors.default_max_asdos}
                                hint="1 = satu pasangan per praktikum"
                                required
                            >
                                <Input
                                    type="number"
                                    min={1}
                                    max={10}
                                    value={form.data.default_max_asdos}
                                    onChange={(e) => form.setData('default_max_asdos', Number(e.target.value))}
                                    required
                                />
                            </Field>
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Menyimpan…' : 'Simpan Pengaturan'}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
