import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../../Components/AppLayout';
import { Badge, Button, Card, EmptyState, Field, Modal, Select, TableWrap, Td, Th } from '../../../Components/UI';
import { availabilityOf, isBookable } from '../../../lib/status';

export default function EmptyPracticums({ practicums, pairsByCourse }) {
    const [target, setTarget] = useState(null);
    const form = useForm({ practicum_id: '', pair_id: '' });

    const candidates = target ? (pairsByCourse[target.course_id] ?? []) : [];

    const open = (item) => {
        form.setData({ practicum_id: item.id, pair_id: '' });
        form.clearErrors();
        setTarget(item);
    };

    return (
        <AppLayout
            title="Praktikum Belum Terisi"
            heading="Praktikum Belum Terisi"
            subheading={`${practicums.length} praktikum menunggu pasangan Asdos`}
        >
            <Card>
                {practicums.length === 0 ? (
                    <EmptyState
                        title="Semua praktikum sudah memiliki pasangan Asdos"
                        description="Tidak ada praktikum yang menunggu penempatan."
                    />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Mata Kuliah</Th>
                                <Th>Kelas</Th>
                                <Th>Ketua Kelas</Th>
                                <Th>Terisi</Th>
                                <Th>Status</Th>
                                <Th className="text-right">Aksi</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {practicums.map((item) => (
                                <tr key={item.id}>
                                    <Td>
                                        <span className="font-medium text-ink">{item.course}</span>
                                        <span className="ml-1 text-xs text-ink-soft">Sem. {item.semester}</span>
                                    </Td>
                                    <Td>{item.class_label}</Td>
                                    <Td className="text-ink-soft">{item.representative ?? '—'}</Td>
                                    <Td className="tabular-nums">
                                        {item.filled} / {item.max_asdos}
                                    </Td>
                                    <Td>
                                        {item.is_locked ? (
                                            <Badge tone="neutral" dot>
                                                Terkunci
                                            </Badge>
                                        ) : (
                                            <Badge tone="danger" dot>
                                                Belum terisi
                                            </Badge>
                                        )}
                                    </Td>
                                    <Td className="text-right">
                                        <Button variant="ghost" onClick={() => open(item)}>
                                            Tempatkan Pasangan
                                        </Button>
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableWrap>
                )}
            </Card>

            <Modal
                open={Boolean(target)}
                onClose={() => setTarget(null)}
                title="Tempatkan Pasangan Asdos"
                description={target ? `${target.course} — ${target.class_label}` : undefined}
                footer={
                    <>
                        <Button variant="secondary" onClick={() => setTarget(null)}>
                            Batal
                        </Button>
                        <Button
                            disabled={form.processing || !form.data.pair_id}
                            onClick={() =>
                                form.post('/admin/booking', {
                                    preserveScroll: true,
                                    onSuccess: () => setTarget(null),
                                })
                            }
                        >
                            {form.processing ? 'Menyimpan…' : 'Tempatkan'}
                        </Button>
                    </>
                }
            >
                {candidates.length === 0 ? (
                    <p className="text-sm text-amber-800">
                        Belum ada pasangan Asdos untuk mata kuliah ini. Bentuk lewat menu Pasangan Asdos.
                    </p>
                ) : (
                    <Field label="Pasangan Asdos" error={form.errors.pair_id} required>
                        <Select value={form.data.pair_id} onChange={(e) => form.setData('pair_id', e.target.value)}>
                            <option value="">— Pilih pasangan —</option>
                            {candidates.map((item) => (
                                <option key={item.id} value={item.id} disabled={!isBookable(item.availability)}>
                                    {item.label} — {availabilityOf(item.availability).label} (sisa {item.remaining})
                                </option>
                            ))}
                        </Select>
                    </Field>
                )}
            </Modal>
        </AppLayout>
    );
}
