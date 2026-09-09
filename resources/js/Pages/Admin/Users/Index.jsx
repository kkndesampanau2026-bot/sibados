import { router, useForm } from '@inertiajs/react';
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
import { ROLE_LABEL, ROLE_TONE } from '../../../lib/status';

export default function Index({ users, filters }) {
    const [editing, setEditing] = useState(null);
    const [deleting, setDeleting] = useState(null);
    const [search, setSearch] = useState(filters.q ?? '');

    const empty = { name: '', email: '', phone: '', password: '', role: 'perwakilan', is_active: true };
    const form = useForm(empty);

    const openCreate = () => {
        form.setData(empty);
        form.clearErrors();
        setEditing('new');
    };

    const openEdit = (user) => {
        form.setData({
            name: user.name,
            email: user.email,
            phone: user.phone ?? '',
            password: '',
            role: user.role,
            is_active: user.is_active,
        });
        form.clearErrors();
        setEditing(user);
    };

    const submit = (event) => {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };

        if (editing === 'new') {
            form.post('/admin/pengguna', options);
        } else {
            form.put(`/admin/pengguna/${editing.id}`, options);
        }
    };

    const applyFilters = (next) => {
        router.get(
            '/admin/pengguna',
            { q: search || undefined, role: filters.role || undefined, ...next },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout
            title="Pengguna"
            heading="Pengguna"
            subheading={`${users.length} akun terdaftar`}
            actions={<Button onClick={openCreate}>+ Tambah</Button>}
        >
            <Card>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        applyFilters({});
                    }}
                    className="flex flex-wrap gap-2 border-b border-line p-3"
                >
                    <Input
                        placeholder="Cari nama atau email…"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="sm:max-w-xs"
                    />
                    <Select
                        value={filters.role ?? ''}
                        onChange={(e) => applyFilters({ role: e.target.value || undefined })}
                        className="sm:max-w-xs"
                    >
                        <option value="">Semua peran</option>
                        <option value="admin">Admin / Koordinator</option>
                        <option value="asdos">Asisten Dosen</option>
                        <option value="perwakilan">Ketua Kelas</option>
                    </Select>
                    <Button type="submit" variant="secondary">
                        Cari
                    </Button>
                </form>

                {users.length === 0 ? (
                    <EmptyState title="Tidak ada pengguna" description="Sesuaikan pencarian atau tambahkan akun baru." />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Nama</Th>
                                <Th>Email</Th>
                                <Th>Nomor HP</Th>
                                <Th>Peran</Th>
                                <Th>Kelas Dipimpin</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <Td className="font-medium text-ink">{user.name}</Td>
                                    <Td className="text-ink-soft">{user.email}</Td>
                                    <Td className="text-ink-soft">{user.phone ?? '—'}</Td>
                                    <Td>
                                        <Badge dot tone={ROLE_TONE[user.role]}>{ROLE_LABEL[user.role]}</Badge>
                                    </Td>
                                    <Td className="max-w-xs text-ink-soft">
                                        {user.classes.length === 0 ? '—' : user.classes.join(', ')}
                                    </Td>
                                    <Td>
                                        <Badge
                                            className={
                                                user.is_active
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                        >
                                            {user.is_active ? 'Aktif' : 'Nonaktif'}
                                        </Badge>
                                    </Td>
                                    <Td className="text-right">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" onClick={() => openEdit(user)}>
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                className="text-rose-600 hover:bg-rose-50"
                                                onClick={() => setDeleting(user)}
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
                title={editing === 'new' ? 'Tambah Pengguna' : 'Edit Pengguna'}
                description="Akun Asisten Dosen dibuat melalui menu Asdos."
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
                    <Field label="Nama" error={form.errors.name} required>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
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
                    <Field label="Peran" error={form.errors.role} required>
                        <Select
                            value={form.data.role}
                            onChange={(e) => form.setData('role', e.target.value)}
                            disabled={editing !== 'new' && editing?.role === 'asdos'}
                        >
                            {editing !== 'new' && editing?.role === 'asdos' ? (
                                <option value="asdos">Asisten Dosen</option>
                            ) : (
                                <>
                                    <option value="perwakilan">Ketua Kelas</option>
                                    <option value="admin">Admin / Koordinator</option>
                                </>
                            )}
                        </Select>
                    </Field>
                    <Checkbox
                        label="Akun aktif"
                        checked={form.data.is_active}
                        onChange={(e) => form.setData('is_active', e.target.checked)}
                    />
                </form>
            </Modal>

            <ConfirmDialog
                open={Boolean(deleting)}
                onClose={() => setDeleting(null)}
                onConfirm={() =>
                    router.delete(`/admin/pengguna/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => setDeleting(null),
                    })
                }
                title="Hapus Pengguna"
                message={`Hapus akun ${deleting?.name}? Tindakan ini tidak dapat dibatalkan.`}
                confirmLabel="Hapus"
            />
        </AppLayout>
    );
}
