import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import ConfirmDialog from '../../../Components/ConfirmDialog';
import { Badge, Button, Card, EmptyState, Field, Input, Modal, Select, TableWrap, Td, Th } from '../../../Components/UI';

const EMPTY = { name: '', code: '', semester: 'I', status: 'aktif' };

export default function Index({ courses, filters }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [search, setSearch] = useState(filters.q ?? '');

    const form = useForm(EMPTY);

    const openCreate = () => {
        form.setDefaults(EMPTY);
        form.reset();
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (course) => {
        form.setData({
            name: course.name,
            code: course.code ?? '',
            semester: course.semester,
            status: course.status,
        });
        form.clearErrors();
        setEditing(course);
    };

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/admin/mata-kuliah', options);
        } else {
            form.put(`/admin/mata-kuliah/${editing.id}`, options);
        }
    };

    const applySearch = (event) => {
        event.preventDefault();
        router.get('/admin/mata-kuliah', { q: search || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout
            title="Mata Kuliah"
            heading="Mata Kuliah"
            subheading={`${courses.length} mata kuliah terdaftar`}
            actions={<Button onClick={openCreate}>+ Tambah</Button>}
        >
            <Card>
                <form onSubmit={applySearch} className="flex gap-2 border-b border-line p-3">
                    <Input
                        placeholder="Cari nama mata kuliah…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                    <Button type="submit" variant="secondary">
                        Cari
                    </Button>
                </form>

                {courses.length === 0 ? (
                    <EmptyState
                        title="Belum ada mata kuliah"
                        description="Tambahkan mata kuliah terlebih dahulu sebelum membuat kelas."
                        action={<Button onClick={openCreate}>Tambah Mata Kuliah</Button>}
                    />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Mata Kuliah</Th>
                                <Th>Kode</Th>
                                <Th>Semester</Th>
                                <Th>Praktikum</Th>
                                <Th>Asdos</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {courses.map((course) => (
                                <tr key={course.id}>
                                    <Td className="font-medium text-ink">{course.name}</Td>
                                    <Td className="text-ink-soft">{course.code ?? '—'}</Td>
                                    <Td>{course.semester}</Td>
                                    <Td>{course.practicums_count}</Td>
                                    <Td>{course.asdos_count}</Td>
                                    <Td>
                                        <Badge
                                            className={
                                                course.status === 'aktif'
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {course.status === 'aktif' ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </Td>
                                    <Td className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" onClick={() => openEdit(course)}>
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                className="text-rose-600 hover:bg-rose-50"
                                                onClick={() => setDeleting(course)}
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
                title={editing === 'new' ? 'Tambah Mata Kuliah' : 'Edit Mata Kuliah'}
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
                    <Field label="Nama Mata Kuliah" error={form.errors.name} required>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Kode" error={form.errors.code} hint="Opsional, mis. ALP">
                        <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Semester" error={form.errors.semester} required>
                            <Select
                                value={form.data.semester}
                                onChange={(e) => form.setData('semester', e.target.value)}
                            >
                                {['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'].map((s) => (
                                    <option key={s} value={s}>
                                        {s}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label="Status" error={form.errors.status} required>
                            <Select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </Select>
                        </Field>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={Boolean(deleting)}
                onClose={() => setDeleting(null)}
                onConfirm={() =>
                    router.delete(`/admin/mata-kuliah/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
                title="Hapus Mata Kuliah"
                message={`Hapus "${deleting?.name}"? Seluruh praktikum, penugasan Asdos, dan booking pada mata kuliah ini ikut terhapus.`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
