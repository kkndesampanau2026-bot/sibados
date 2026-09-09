import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import ConfirmDialog from '../../../Components/ConfirmDialog';
import {
    Badge,
    Button,
    Card,
    EmptyState,
    Field,
    Icon,
    Modal,
    Select,
    TableWrap,
    Td,
    Textarea,
    Th,
} from '../../../Components/UI';
import { BOOKING_STATUS, availabilityOf, isBookable } from '../../../lib/status';

export default function Index({ bookings, filters, courses, practicums, pairsByCourse }) {
    const [placing, setPlacing] = useState(false);
    const [reassigning, setReassigning] = useState(null);
    const [cancelling, setCancelling] = useState(null);

    const placeForm = useForm({ practicum_id: '', pair_id: '' });
    const reassignForm = useForm({ pair_id: '' });
    const cancelForm = useForm({ note: '' });

    // Pasangan yang boleh dipilih mengikuti mata kuliah praktikum terpilih.
    const placeCandidates = useMemo(() => {
        const selected = practicums.find((p) => String(p.id) === String(placeForm.data.practicum_id));

        return selected ? (pairsByCourse[selected.course_id] ?? []) : [];
    }, [placeForm.data.practicum_id, practicums, pairsByCourse]);

    const reassignCandidates = useMemo(() => {
        if (!reassigning) return [];

        return (pairsByCourse[reassigning.course_id] ?? []).filter((p) => p.id !== reassigning.pair_id);
    }, [reassigning, pairsByCourse]);

    const applyFilter = (next) => {
        router.get(
            '/admin/booking',
            { status: filters.status, course_id: filters.course_id || undefined, ...next },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout
            title="Booking"
            heading="Booking & Penempatan"
            subheading={`${bookings.length} data ditampilkan`}
            actions={
                <>
                    {/* Tautan biasa, bukan kunjungan Inertia, agar berkas terunduh. */}
                    <a
                        href={`/admin/booking/export?${new URLSearchParams({
                            status: filters.status ?? 'aktif',
                            ...(filters.course_id ? { course_id: filters.course_id } : {}),
                        }).toString()}`}
                        className="inline-flex h-9.5 shrink-0 items-center justify-center gap-2 rounded-lg bg-surface px-3.5 text-sm font-medium text-ink shadow-card ring-1 ring-line ring-inset transition hover:bg-canvas"
                    >
                        <Icon name="download" className="size-4" />
                        Ekspor Excel
                    </a>
                    <Button onClick={() => setPlacing(true)}>+ Tempatkan Pasangan</Button>
                </>
            }
        >
            <Card>
                <div className="flex flex-wrap gap-2 border-b border-line p-3">
                    <Select
                        value={filters.status}
                        onChange={(e) => applyFilter({ status: e.target.value })}
                        className="sm:max-w-xs"
                    >
                        <option value="aktif">Booking aktif</option>
                        <option value="dibatalkan">Dibatalkan</option>
                        <option value="semua">Semua status</option>
                    </Select>
                    <Select
                        value={filters.course_id ?? ''}
                        onChange={(e) => applyFilter({ course_id: e.target.value || undefined })}
                        className="sm:max-w-xs"
                    >
                        <option value="">Semua mata kuliah</option>
                        {courses.map((course) => (
                            <option key={course.id} value={course.id}>
                                {course.name}
                            </option>
                        ))}
                    </Select>
                </div>

                {bookings.length === 0 ? (
                    <EmptyState
                        title="Belum ada booking"
                        description="Booking akan muncul setelah ketua kelas memilih pasangan Asdos."
                    />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Mata Kuliah</Th>
                                <Th>Kelas</Th>
                                <Th>Pasangan Asdos</Th>
                                <Th>Ketua Kelas</Th>
                                <Th>Waktu</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {bookings.map((item) => (
                                <tr key={item.id}>
                                    <Td className="whitespace-nowrap">
                                        <span className="font-medium text-ink">{item.course}</span>
                                        <span className="ml-1 text-xs text-ink-soft">Sem. {item.semester}</span>
                                    </Td>
                                    <Td className="whitespace-nowrap">{item.class_label}</Td>
                                    <Td className="font-medium whitespace-nowrap text-ink">{item.pair}</Td>
                                    <Td className="whitespace-nowrap text-ink-soft">{item.representative ?? '—'}</Td>
                                    <Td className="text-xs whitespace-nowrap text-ink-soft">
                                        {item.booked_at}
                                        {item.cancelled_at && (
                                            <span className="block text-rose-500">
                                                Dibatalkan {item.cancelled_at}
                                                {item.cancelled_by && ` oleh ${item.cancelled_by}`}
                                            </span>
                                        )}
                                    </Td>
                                    <Td>
                                        <Badge dot tone={BOOKING_STATUS[item.status].tone}>
                                            {BOOKING_STATUS[item.status].label}
                                        </Badge>
                                    </Td>
                                    <Td className="text-right">
                                        {item.status === 'aktif' && (
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    onClick={() => {
                                                        reassignForm.setData('pair_id', '');
                                                        setReassigning(item);
                                                    }}
                                                >
                                                    Ganti
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    className="text-rose-600 hover:bg-rose-50"
                                                    onClick={() => {
                                                        cancelForm.setData('note', '');
                                                        setCancelling(item);
                                                    }}
                                                >
                                                    Batalkan
                                                </Button>
                                            </div>
                                        )}
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableWrap>
                )}
            </Card>

            {/* Penempatan manual oleh Admin */}
            <Modal
                open={placing}
                onClose={() => setPlacing(false)}
                title="Tempatkan Pasangan Asdos"
                description="Penempatan oleh Koordinator mengabaikan jadwal booking dan kunci praktikum."
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setPlacing(false)}>
                            Batal
                        </Button>
                        <Button
                            disabled={placeForm.processing || !placeForm.data.pair_id}
                            onClick={() =>
                                placeForm.post('/admin/booking', {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        placeForm.reset();
                                        setPlacing(false);
                                    },
                                })
                            }
                        >
                            {placeForm.processing ? 'Menyimpan…' : 'Tempatkan'}
                        </Button>
                    </>
                }
            >
                <div className="space-y-4">
                    <Field label="Praktikum" error={placeForm.errors.practicum_id} required>
                        <Select
                            value={placeForm.data.practicum_id}
                            onChange={(e) => {
                                placeForm.setData('practicum_id', e.target.value);
                                placeForm.setData('pair_id', '');
                            }}
                        >
                            <option value="">— Pilih praktikum yang belum penuh —</option>
                            {practicums.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    <Field
                        label="Pasangan Asdos"
                        error={placeForm.errors.pair_id}
                        hint="Hanya pasangan yang dibentuk untuk mata kuliah praktikum tersebut"
                        required
                    >
                        <Select
                            value={placeForm.data.pair_id}
                            onChange={(e) => placeForm.setData('pair_id', e.target.value)}
                            disabled={!placeForm.data.practicum_id}
                        >
                            <option value="">— Pilih pasangan —</option>
                            {placeCandidates.map((item) => (
                                <option key={item.id} value={item.id} disabled={!isBookable(item.availability)}>
                                    {item.label} — {availabilityOf(item.availability).label} (sisa {item.remaining})
                                </option>
                            ))}
                        </Select>
                    </Field>

                    {placeForm.data.practicum_id && placeCandidates.length === 0 && (
                        <p className="text-sm text-amber-800">
                            Belum ada pasangan Asdos untuk mata kuliah ini. Bentuk lewat menu Pasangan Asdos.
                        </p>
                    )}
                </div>
            </Modal>

            {/* Ganti pasangan */}
            <Modal
                open={Boolean(reassigning)}
                onClose={() => setReassigning(null)}
                title="Ganti Pasangan Asdos"
                description={reassigning ? `${reassigning.course} — ${reassigning.class_label}` : undefined}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setReassigning(null)}>
                            Batal
                        </Button>
                        <Button
                            disabled={reassignForm.processing || !reassignForm.data.pair_id}
                            onClick={() =>
                                reassignForm.put(`/admin/booking/${reassigning.id}`, {
                                    preserveScroll: true,
                                    onSuccess: () => setReassigning(null),
                                })
                            }
                        >
                            {reassignForm.processing ? 'Menyimpan…' : 'Ganti'}
                        </Button>
                    </>
                }
            >
                <Field label="Pasangan Pengganti" error={reassignForm.errors.pair_id} required>
                    <Select
                        value={reassignForm.data.pair_id}
                        onChange={(e) => reassignForm.setData('pair_id', e.target.value)}
                    >
                        <option value="">— Pilih pasangan —</option>
                        {reassignCandidates.map((item) => (
                            <option key={item.id} value={item.id} disabled={!isBookable(item.availability)}>
                                {item.label} — {availabilityOf(item.availability).label} (sisa {item.remaining})
                            </option>
                        ))}
                    </Select>
                </Field>
                <p className="mt-3 text-xs text-ink-soft">
                    Pasangan saat ini: <strong>{reassigning?.pair}</strong>. Perubahan tercatat pada riwayat booking.
                </p>
            </Modal>

            {/* Pembatalan */}
            <ConfirmDialog
                open={Boolean(cancelling)}
                onClose={() => setCancelling(null)}
                processing={cancelForm.processing}
                onConfirm={() =>
                    cancelForm.delete(`/admin/booking/${cancelling.id}`, {
                        preserveScroll: true,
                        onSuccess: () => setCancelling(null),
                    })
                }
                title="Batalkan Booking"
                confirmLabel="Batalkan Booking"
            >
                <p className="text-sm text-ink">
                    Batalkan penempatan pasangan <strong>{cancelling?.pair}</strong> pada praktikum{' '}
                    <strong>{cancelling?.course}</strong> untuk <strong>{cancelling?.class_label}</strong>? Kuota kedua
                    Asdos akan kembali dan praktikum dapat dibooking ulang.
                </p>
                <div className="mt-4">
                    <Field label="Catatan" error={cancelForm.errors.note} hint="Opsional, tersimpan pada riwayat">
                        <Textarea
                            value={cancelForm.data.note}
                            onChange={(e) => cancelForm.setData('note', e.target.value)}
                        />
                    </Field>
                </div>
            </ConfirmDialog>
        </AppLayout>
    );
}
