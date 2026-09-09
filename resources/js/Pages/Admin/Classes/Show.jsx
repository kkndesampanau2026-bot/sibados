import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import ConfirmDialog from '../../../Components/ConfirmDialog';
import { Badge, Button, Card, CardHeader, EmptyState, Field, Input, Modal, Select } from '../../../Components/UI';

export default function Show({ classRoom, practicums, availableCourses }) {
    const [adding, setAdding] = useState(false);
    const [deleting, setDeleting] = useState(null);

    const addForm = useForm({ course_id: '', max_asdos: 1 });

    const filled = practicums.filter((p) => p.is_filled).length;

    return (
        <AppLayout
            title={classRoom.label}
            heading={classRoom.label}
            subheading={`Semester ${classRoom.semester} · ${practicums.length} praktikum`}
            actions={
                <Button
                    variant="secondary"
                    onClick={() =>
                        router.post(`/admin/kelas/${classRoom.id}/sinkron-praktikum`, {}, { preserveScroll: true })
                    }
                >
                    Sinkronkan
                </Button>
            }
        >
            <div className="mx-auto max-w-4xl space-y-5">
                <Link href="/admin/kelas" className="inline-block text-sm font-medium text-brand-600">
                    ← Kembali ke daftar kelas
                </Link>

                <Card>
                    <CardHeader
                        title="Informasi Kelas"
                        action={
                            <Badge
                                className={
                                    filled === practicums.length && practicums.length > 0
                                        ? 'success'
                                        : 'bg-amber-100 text-amber-800 ring-amber-600/20'
                                }
                            >
                                {filled}/{practicums.length} praktikum terisi
                            </Badge>
                        }
                    />
                    <dl className="grid gap-4 px-4 py-4 sm:grid-cols-3 sm:px-5">
                        {[
                            ['Kelas', `Kelas ${classRoom.class_name}`],
                            ['Angkatan', classRoom.angkatan],
                            ['Semester', classRoom.semester],
                            ['Ketua Kelas', classRoom.representative ?? '— belum ditentukan —'],
                            ['Email Ketua', classRoom.representative_email ?? '—'],
                            ['Nomor HP', classRoom.representative_phone ?? '—'],
                        ].map(([label, value]) => (
                            <div key={label}>
                                <dt className="text-xs uppercase tracking-wide text-ink-soft">{label}</dt>
                                <dd className="mt-0.5 text-sm font-medium text-ink">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </Card>

                <Card>
                    <CardHeader
                        title="Mata Kuliah Praktikum"
                        description="Setiap praktikum dipilihkan Asdos oleh ketua kelas"
                        action={
                            <Button onClick={() => setAdding(true)} disabled={availableCourses.length === 0}>
                                + Praktikum
                            </Button>
                        }
                    />

                    {practicums.length === 0 ? (
                        <EmptyState
                            title="Belum ada praktikum"
                            description="Tekan Sinkronkan untuk menambahkan seluruh mata kuliah semester ini."
                        />
                    ) : (
                        <ul className="divide-y divide-line-soft">
                            {practicums.map((item) => (
                                <li key={item.id} className="px-4 py-4 sm:px-5">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="font-semibold text-ink">{item.course}</p>
                                            <p className="text-sm text-ink-soft">
                                                Semester {item.semester} · kuota {item.max_asdos} Asdos
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-1">
                                            {item.is_filled ? (
                                                <Badge tone="success" dot>
                                                    Terisi
                                                </Badge>
                                            ) : (
                                                <Badge tone="danger" dot>
                                                    Belum
                                                </Badge>
                                            )}
                                            {item.is_locked && (
                                                <Badge tone="neutral" dot>
                                                    Terkunci
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    {item.asdos.length > 0 && (
                                        <ul className="mt-3 space-y-1.5 rounded-lg bg-canvas p-3">
                                            {item.asdos.map((a) => (
                                                <li
                                                    key={a.booking_id}
                                                    className="flex flex-wrap items-center gap-2 text-sm"
                                                >
                                                    <span className="font-medium text-ink">{a.label}</span>
                                                    <span className="ml-auto text-xs text-ink-faint">
                                                        {a.booked_at}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}

                                    <div className="mt-3 flex flex-wrap gap-1">
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                router.post(
                                                    `/admin/praktikum/${item.id}/kunci`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {item.is_locked ? 'Buka kunci' : 'Kunci'}
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            className="text-rose-600 hover:bg-rose-50"
                                            onClick={() => setDeleting(item)}
                                        >
                                            Hapus praktikum
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <Modal
                open={adding}
                onClose={() => setAdding(false)}
                title="Tambah Praktikum"
                description={`Untuk ${classRoom.label}`}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setAdding(false)}>
                            Batal
                        </Button>
                        <Button
                            disabled={addForm.processing || !addForm.data.course_id}
                            onClick={() =>
                                addForm.post(`/admin/kelas/${classRoom.id}/praktikum`, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        addForm.reset();
                                        setAdding(false);
                                    },
                                })
                            }
                        >
                            Tambahkan
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Field label="Mata Kuliah" error={addForm.errors.course_id} required>
                        <Select
                            value={addForm.data.course_id}
                            onChange={(e) => addForm.setData('course_id', e.target.value)}
                        >
                            <option value="">— Pilih mata kuliah —</option>
                            {availableCourses.map((course) => (
                                <option key={course.id} value={course.id}>
                                    {course.name} (Sem. {course.semester})
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Kuota Asdos" error={addForm.errors.max_asdos} required>
                        <Input
                            type="number"
                            min={1}
                            max={10}
                            value={addForm.data.max_asdos}
                            onChange={(e) => addForm.setData('max_asdos', Number(e.target.value))}
                        />
                    </Field>
                </div>
            </Modal>

            <ConfirmDialog
                open={Boolean(deleting)}
                onClose={() => setDeleting(null)}
                onConfirm={() =>
                    router.delete(`/admin/praktikum/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
                title="Hapus Praktikum"
                message={`Hapus praktikum ${deleting?.course} dari ${classRoom.label}? Booking pada praktikum ini ikut terhapus.`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
