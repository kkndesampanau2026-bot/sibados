import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import ConfirmDialog from '../../../Components/ConfirmDialog';
import {
    Badge,
    Button,
    Card,
    Checkbox,
    EmptyState,
    Field,
    Input,
    Modal,
    Select,
    TableWrap,
    Td,
    Th,
} from '../../../Components/UI';
import { availabilityOf } from '../../../lib/status';

export default function Index({ asdos, courses, filters, defaultMaxClasses, defaultCourseQuota }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [search, setSearch] = useState(filters.q ?? '');

    const empty = {
        name: '',
        email: '',
        phone: '',
        password: '',
        nim: '',
        semester: '',
        max_classes: defaultMaxClasses,
        status: 'aktif',
        course_ids: [],
        course_quotas: {},
    };

    const form = useForm(empty);

    const openCreate = () => {
        form.setData(empty);
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (item) => {
        form.setData({
            name: item.name ?? '',
            email: item.email ?? '',
            phone: item.phone ?? '',
            password: '',
            nim: item.nim ?? '',
            semester: item.semester ?? '',
            max_classes: item.max_classes,
            status: item.status,
            course_ids: item.course_ids ?? [],
            course_quotas: Object.fromEntries((item.courses ?? []).map((c) => [c.id, c.max])),
        });
        form.clearErrors();
        setEditing(item);
    };

    const toggleCourse = (id) => {
        const on = form.data.course_ids.includes(id);
        const list = on ? form.data.course_ids.filter((c) => c !== id) : [...form.data.course_ids, id];

        form.setData('course_ids', list);

        // Mata kuliah yang baru dicentang mendapat kuota bawaan.
        if (!on && form.data.course_quotas[id] === undefined) {
            form.setData('course_quotas', { ...form.data.course_quotas, [id]: defaultCourseQuota });
        }
    };

    const setCourseQuota = (id, value) => {
        form.setData('course_quotas', { ...form.data.course_quotas, [id]: Number(value) });
    };

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/admin/asdos', options);
        } else {
            form.put(`/admin/asdos/${editing.id}`, options);
        }
    };

    const applySearch = (event) => {
        event.preventDefault();
        router.get('/admin/asdos', { q: search || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout
            title="Asdos"
            heading="Asisten Dosen"
            subheading={`${asdos.length} Asdos terdaftar`}
            actions={<Button onClick={openCreate}>+ Tambah</Button>}
        >
            <Card>
                <form onSubmit={applySearch} className="flex flex-wrap gap-2 border-b border-line p-3">
                    <Input
                        placeholder="Cari nama atau NIM…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="sm:max-w-xs"
                    />
                    <Button type="submit" variant="secondary">
                        Cari
                    </Button>
                </form>

                {asdos.length === 0 ? (
                    <EmptyState
                        title="Belum ada Asdos"
                        description="Tambahkan Asdos beserta akun login dan mata kuliah yang ditangani."
                        action={<Button onClick={openCreate}>Tambah Asdos</Button>}
                    />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Nama</Th>
                                <Th>NIM</Th>
                                <Th>Mata Kuliah</Th>
                                <Th>Kuota</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {asdos.map((item) => {
                                const status = availabilityOf(item.availability);

                                return (
                                    <tr key={item.id}>
                                        <Td>
                                            <Link
                                                href={`/admin/asdos/${item.id}`}
                                                className="font-medium text-brand-600 hover:text-brand-600"
                                            >
                                                {item.name}
                                            </Link>
                                            <p className="text-xs text-ink-faint">{item.email}</p>
                                        </Td>
                                        <Td>{item.nim}</Td>
                                        <Td className="max-w-xs text-ink-soft">
                                            {item.courses.length === 0 ? (
                                                '—'
                                            ) : (
                                                <div className="space-y-0.5 text-xs">
                                                    {item.courses.map((c) => (
                                                        <div key={c.id} className="tabular-nums">
                                                            {c.name}{' '}
                                                            <span
                                                                className={
                                                                    c.used >= c.max
                                                                        ? 'font-semibold text-rose-600'
                                                                        : 'text-ink-faint'
                                                                }
                                                            >
                                                                {c.used}/{c.max}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </Td>
                                        <Td className="tabular-nums">
                                            {item.used_quota} / {item.max_classes}
                                        </Td>
                                        <Td>
                                            <Badge dot tone={status.tone}>
                                                {status.label}
                                            </Badge>
                                        </Td>
                                        <Td className="text-right">
                                            <div className="flex justify-end gap-1">
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
                                );
                            })}
                        </tbody>
                    </TableWrap>
                )}
            </Card>

            <Modal
                open={Boolean(editing)}
                onClose={() => setEditing(null)}
                title={editing === 'new' ? 'Tambah Asdos' : 'Edit Asdos'}
                description="Akun login Asdos dibuat otomatis bersama profil ini."
                size="lg"
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
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Nama" error={form.errors.name} required>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        </Field>
                        <Field label="NIM" error={form.errors.nim} required>
                            <Input value={form.data.nim} onChange={(e) => form.setData('nim', e.target.value)} required />
                        </Field>
                        <Field label="Email" error={form.errors.email} required>
                            <Input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                                required
                            />
                        </Field>
                        <Field label="Nomor HP" error={form.errors.phone}>
                            <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </Field>
                        <Field
                            label="Kata Sandi"
                            error={form.errors.password}
                            hint={editing === 'new' ? 'Minimal 8 karakter' : 'Kosongkan bila tidak diubah'}
                            required={editing === 'new'}
                        >
                            <Input
                                type="password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                required={editing === 'new'}
                            />
                        </Field>
                        <Field label="Semester" error={form.errors.semester}>
                            <Input
                                value={form.data.semester}
                                onChange={(e) => form.setData('semester', e.target.value)}
                                placeholder="mis. V"
                            />
                        </Field>
                        <Field
                            label="Kuota Total"
                            error={form.errors.max_classes}
                            hint="Batas atas lintas semua mata kuliah"
                            required
                        >
                            <Input
                                type="number"
                                min={1}
                                max={20}
                                value={form.data.max_classes}
                                onChange={(e) => form.setData('max_classes', Number(e.target.value))}
                                required
                            />
                        </Field>
                        <Field label="Status" error={form.errors.status} required>
                            <Select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </Select>
                        </Field>
                    </div>

                    <div>
                        <p className="mb-2 text-sm font-medium text-ink">
                            Mata Kuliah & Kuota
                            <span className="ml-1 font-normal text-ink-soft">
                                — angka membatasi berapa kelas boleh memilih Asdos ini per mata kuliah
                            </span>
                        </p>
                        <div className="max-h-64 space-y-1.5 overflow-y-auto rounded-lg border border-line p-3">
                            {courses.map((course) => {
                                const checked = form.data.course_ids.includes(course.id);

                                return (
                                    <div key={course.id} className="flex items-center justify-between gap-3">
                                        <Checkbox
                                            label={`${course.name} (Sem. ${course.semester})`}
                                            checked={checked}
                                            onChange={() => toggleCourse(course.id)}
                                        />
                                        {checked && (
                                            <Input
                                                type="number"
                                                min={1}
                                                max={20}
                                                value={form.data.course_quotas[course.id] ?? defaultCourseQuota}
                                                onChange={(e) => setCourseQuota(course.id, e.target.value)}
                                                className="w-20 shrink-0 px-2 py-1 text-xs"
                                                aria-label={`Kuota ${course.name}`}
                                            />
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </form>
            </Modal>

            <ConfirmDialog
                open={Boolean(deleting)}
                onClose={() => setDeleting(null)}
                onConfirm={() =>
                    router.delete(`/admin/asdos/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
                title="Hapus Asdos"
                message={`Hapus ${deleting?.name}? Akun login dan seluruh booking Asdos ini ikut terhapus.`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
