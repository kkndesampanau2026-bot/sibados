import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import ConfirmDialog from '../../../Components/ConfirmDialog';
import { Badge, Button, Card, EmptyState, Field, Input, Modal, Select, TableWrap, Td, Th } from '../../../Components/UI';

const SEMESTERS = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

export default function Index({ classes, angkatanList, representatives, filters }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const empty = {
        class_name: '',
        angkatan: new Date().getFullYear(),
        semester: 'I',
        representative_id: '',
    };

    const form = useForm(empty);

    const openCreate = () => {
        form.setData(empty);
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (item) => {
        form.setData({
            class_name: item.class_name,
            angkatan: item.angkatan,
            semester: item.semester,
            representative_id: item.representative_id ?? '',
        });
        form.clearErrors();
        setEditing(item);
    };

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/admin/kelas', options);
        } else {
            form.put(`/admin/kelas/${editing.id}`, options);
        }
    };

    return (
        <AppLayout
            title="Kelas"
            heading="Kelas (Rombel)"
            subheading={`${classes.length} kelas terdaftar`}
            actions={<Button onClick={openCreate}>+ Tambah</Button>}
        >
            <Card>
                <div className="border-b border-line p-3">
                    <Select
                        value={filters.angkatan ?? ''}
                        onChange={(e) =>
                            router.get(
                                '/admin/kelas',
                                { angkatan: e.target.value || undefined },
                                { preserveState: true, replace: true },
                            )
                        }
                        className="sm:max-w-xs"
                    >
                        <option value="">Semua angkatan</option>
                        {angkatanList.map((a) => (
                            <option key={a} value={a}>
                                Angkatan {a}
                            </option>
                        ))}
                    </Select>
                </div>

                {classes.length === 0 ? (
                    <EmptyState
                        title="Belum ada kelas"
                        description="Tambahkan rombel, lalu praktikum semesternya dibuat otomatis."
                        action={<Button onClick={openCreate}>Tambah Kelas</Button>}
                    />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Kelas</Th>
                                <Th>Angkatan</Th>
                                <Th>Semester</Th>
                                <Th>Ketua Kelas</Th>
                                <Th>Praktikum</Th>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {classes.map((item) => (
                                <tr key={item.id}>
                                    <Td>
                                        <Link
                                            href={`/admin/kelas/${item.id}`}
                                            className="font-medium text-brand-600 hover:text-brand-600"
                                        >
                                            Kelas {item.class_name}
                                        </Link>
                                    </Td>
                                    <Td>{item.angkatan}</Td>
                                    <Td>{item.semester}</Td>
                                    <Td className="text-ink-soft">
                                        {item.representative ?? <span className="text-rose-500">— belum ada —</span>}
                                    </Td>
                                    <Td>
                                        <Badge
                                            className={
                                                item.filled_practicums === item.total_practicums &&
                                                item.total_practicums > 0
                                                    ? 'success'
                                                    : 'bg-amber-100 text-amber-800 ring-amber-600/20'
                                            }
                                        >
                                            {item.filled_practicums}/{item.total_practicums} terisi
                                        </Badge>
                                    </Td>
                                    <Td className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Link
                                                href={`/admin/kelas/${item.id}`}
                                                className="rounded-lg px-3 py-2 text-sm font-medium text-ink-soft hover:bg-line-soft"
                                            >
                                                Detail
                                            </Link>
                                            <Button variant="ghost" onClick={() => openEdit(item)}>
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                className="text-rose-600 hover:bg-rose-50"
                                                onClick={() => setDeleting(item)}
                                            >
                                                Hapus
                                            </Button>
                                        </div>
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableWrap>
                )}
            </Card>

            <Modal
                open={Boolean(editing)}
                onClose={() => setEditing(null)}
                title={editing === 'new' ? 'Tambah Kelas' : 'Edit Kelas'}
                description={
                    editing === 'new'
                        ? 'Praktikum untuk seluruh mata kuliah pada semester ini dibuat otomatis.'
                        : undefined
                }
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
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Nama Kelas" error={form.errors.class_name} hint="A, B, C" required>
                            <Input
                                value={form.data.class_name}
                                onChange={(e) => form.setData('class_name', e.target.value.toUpperCase())}
                                required
                            />
                        </Field>
                        <Field label="Angkatan" error={form.errors.angkatan} required>
                            <Input
                                type="number"
                                min={2000}
                                max={2100}
                                value={form.data.angkatan}
                                onChange={(e) => form.setData('angkatan', Number(e.target.value))}
                                required
                            />
                        </Field>
                        <Field label="Semester" error={form.errors.semester} required>
                            <Select
                                value={form.data.semester}
                                onChange={(e) => form.setData('semester', e.target.value)}
                            >
                                {SEMESTERS.map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>

                    <Field
                        label="Ketua Kelas"
                        error={form.errors.representative_id}
                        hint="Satu mahasiswa hanya boleh menjadi ketua satu kelas"
                    >
                        <Select
                            value={form.data.representative_id}
                            onChange={(e) => form.setData('representative_id', e.target.value)}
                        >
                            <option value="">— Belum ditentukan —</option>
                            {representatives.map((user) => (
                                <option key={user.id} value={user.id}>
                                    {user.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </form>
            </Modal>

            <ConfirmDialog
                open={Boolean(deleting)}
                onClose={() => setDeleting(null)}
                onConfirm={() =>
                    router.delete(`/admin/kelas/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
                title="Hapus Kelas"
                message={`Hapus ${deleting?.label}? Seluruh praktikum dan booking kelas ini ikut terhapus.`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
