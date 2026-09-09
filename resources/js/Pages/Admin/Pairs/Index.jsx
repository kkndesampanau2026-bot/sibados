import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import ConfirmDialog from '../../../Components/ConfirmDialog';
import { Badge, Button, Card, EmptyState, Field, Modal, Select, TableWrap, Td, Th } from '../../../Components/UI';
import { availabilityOf } from '../../../lib/status';

export default function Index({ pairs, courses, asdosByCourse, filters }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const empty = {
        course_id: filters.course_id ?? courses[0]?.id ?? '',
        asdos_one_id: '',
        asdos_two_id: '',
        status: 'aktif',
    };

    const form = useForm(empty);

    // Kandidat anggota mengikuti mata kuliah yang dipilih (penempatan matkul).
    const candidates = useMemo(
        () => asdosByCourse[form.data.course_id] ?? [],
        [form.data.course_id, asdosByCourse],
    );

    const openCreate = () => {
        form.setData(empty);
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (pair) => {
        form.setData({
            course_id: pair.course_id,
            asdos_one_id: pair.asdos_one_id,
            asdos_two_id: pair.asdos_two_id,
            status: pair.status,
        });
        form.clearErrors();
        setEditing(pair);
    };

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/admin/pasangan', options);
        } else {
            form.put(`/admin/pasangan/${editing.id}`, options);
        }
    };

    return (
        <AppLayout
            title="Pasangan Asdos"
            heading="Pasangan Asdos"
            subheading={`${pairs.length} pasangan terbentuk`}
            actions={<Button onClick={openCreate}>+ Buat Pasangan</Button>}
        >
            <Card>
                <div className="border-b border-line p-3">
                    <Select
                        value={filters.course_id ?? ''}
                        onChange={(e) =>
                            router.get(
                                '/admin/pasangan',
                                { course_id: e.target.value || undefined },
                                { preserveState: true, replace: true },
                            )
                        }
                        className="sm:max-w-sm"
                    >
                        <option value="">Semua mata kuliah</option>
                        {courses.map((course) => (
                            <option key={course.id} value={course.id}>
                                {course.name} (Sem. {course.semester})
                            </option>
                        ))}
                    </Select>
                </div>

                {pairs.length === 0 ? (
                    <EmptyState
                        title="Belum ada pasangan"
                        description="Bentuk pasangan Asdos untuk tiap mata kuliah agar ketua kelas dapat memilihnya."
                        action={<Button onClick={openCreate}>Buat Pasangan</Button>}
                    />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Pasangan</Th>
                                <Th>Mata Kuliah</Th>
                                <Th>Kuota di Matkul Ini</Th>
                                <Th>Praktikum Diampu</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {pairs.map((pair) => {
                                const status = availabilityOf(pair.availability);

                                return (
                                    <tr key={pair.id}>
                                        <Td className="whitespace-nowrap">
                                            <span className="font-medium text-ink">{pair.label}</span>
                                            <span className="block text-xs text-ink-faint">
                                                {pair.members.map((m) => `NIM ${m.nim}`).join(' · ')}
                                            </span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <span className="text-ink">{pair.course}</span>
                                            <span className="ml-1 text-xs text-ink-soft">Sem. {pair.semester}</span>
                                        </Td>
                                        <Td>
                                            <div className="space-y-0.5 text-xs">
                                                {pair.members.map((m) => (
                                                    <div key={m.id} className="whitespace-nowrap tabular-nums text-ink-soft">
                                                        {m.name}: {m.course_used}/{m.course_max}
                                                        <span className="text-ink-faint">
                                                            {' '}
                                                            (total {m.total_used}/{m.total_max})
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        </Td>
                                        <Td className="tabular-nums">{pair.active_bookings}</Td>
                                        <Td>
                                            <Badge dot tone={status.tone}>
                                                {status.label}
                                            </Badge>
                                        </Td>
                                        <Td className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" onClick={() => openEdit(pair)}>
                                                    Edit
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    className="text-rose-600 hover:bg-rose-50"
                                                    onClick={() => setDeleting(pair)}
                                                >
                                                    Hapus
                                                </Button>
                                            </div>
                                        </Td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </TableWrap>
                )}
            </Card>

            <p className="mt-3 text-xs text-ink-soft">
                Anggota pasangan hanya dapat dipilih dari Asdos yang sudah ditugaskan pada mata kuliah tersebut melalui
                menu <strong>Penempatan Matkul</strong>. Seorang Asdos boleh tergabung dalam beberapa pasangan; kuota
                tetap dihitung per individu.
            </p>

            <Modal
                open={Boolean(editing)}
                onClose={() => setEditing(null)}
                title={editing === 'new' ? 'Buat Pasangan Asdos' : 'Edit Pasangan Asdos'}
                description="Pasangan dibentuk untuk satu mata kuliah tertentu."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setEditing(null)}>
                            Batal
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </Button>
                    </>
                }
            >
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Mata Kuliah" error={form.errors.course_id} required>
                        <Select
                            value={form.data.course_id}
                            onChange={(e) => {
                                form.setData('course_id', e.target.value);
                                form.setData('asdos_one_id', '');
                                form.setData('asdos_two_id', '');
                            }}
                            required
                        >
                            <option value="">— Pilih mata kuliah —</option>
                            {courses.map((course) => (
                                <option key={course.id} value={course.id}>
                                    {course.name} (Sem. {course.semester})
                                </option>
                            ))}
                        </Select>
                    </Field>

                    {form.data.course_id && candidates.length < 2 && (
                        <p className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            Mata kuliah ini baru punya {candidates.length} Asdos yang ditugaskan. Tambahkan lewat menu
                            Penempatan Matkul agar bisa dipasangkan.
                        </p>
                    )}

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Asdos Pertama" error={form.errors.asdos_one_id} required>
                            <Select
                                value={form.data.asdos_one_id}
                                onChange={(e) => form.setData('asdos_one_id', e.target.value)}
                                disabled={!form.data.course_id}
                                required
                            >
                                <option value="">— Pilih Asdos —</option>
                                {candidates
                                    .filter((a) => String(a.id) !== String(form.data.asdos_two_id))
                                    .map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name} — matkul {a.course_used}/{a.course_max}, total {a.used_quota}/
                                            {a.max_classes}
                                        </option>
                                    ))}
                            </Select>
                        </Field>

                        <Field label="Asdos Kedua" error={form.errors.asdos_two_id} required>
                            <Select
                                value={form.data.asdos_two_id}
                                onChange={(e) => form.setData('asdos_two_id', e.target.value)}
                                disabled={!form.data.course_id}
                                required
                            >
                                <option value="">— Pilih Asdos —</option>
                                {candidates
                                    .filter((a) => String(a.id) !== String(form.data.asdos_one_id))
                                    .map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.name} — matkul {a.course_used}/{a.course_max}, total {a.used_quota}/
                                            {a.max_classes}
                                        </option>
                                    ))}
                            </Select>
                        </Field>
                    </div>

                    <Field label="Status" error={form.errors.status} required>
                        <Select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                            <option value="aktif">Aktif</option>
                            <option value="nonaktif">Nonaktif</option>
                        </Select>
                    </Field>
                </form>
            </Modal>

            <ConfirmDialog
                open={Boolean(deleting)}
                onClose={() => setDeleting(null)}
                onConfirm={() =>
                    router.delete(`/admin/pasangan/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
                title="Hapus Pasangan"
                message={`Hapus pasangan ${deleting?.label} untuk ${deleting?.course}?`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
