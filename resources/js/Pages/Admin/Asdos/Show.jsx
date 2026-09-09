import { Link } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import { Badge, Card, CardHeader, EmptyState, TableWrap, Td, Th } from '../../../Components/UI';
import { availabilityOf } from '../../../lib/status';

export default function Show({ asdos }) {
    const status = availabilityOf(asdos.availability);

    return (
        <AppLayout title={asdos.name} heading={asdos.name} subheading={`NIM ${asdos.nim}`}>
            <div className="mx-auto max-w-4xl space-y-5">
                <Link href="/admin/asdos" className="inline-block text-sm font-medium text-brand-600">
                    ← Kembali ke daftar Asdos
                </Link>

                <Card>
                    <CardHeader
                        title="Detail Asdos"
                        action={
                            <Badge dot tone={status.tone}>
                                {status.label}
                            </Badge>
                        }
                    />
                    <dl className="grid gap-4 px-4 py-4 sm:grid-cols-3 sm:px-5">
                        {[
                            ['NIM', asdos.nim],
                            ['Email', asdos.email],
                            ['Nomor HP', asdos.phone ?? '—'],
                            ['Semester', asdos.semester ?? '—'],
                            ['Status', asdos.status === 'aktif' ? 'Aktif' : 'Nonaktif'],
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
                        title="Mata Kuliah & Kuota"
                        description="Kuota per mata kuliah membatasi berapa kelas boleh memilih Asdos ini"
                    />
                    {asdos.courses.length === 0 ? (
                        <EmptyState
                            title="Belum ada mata kuliah"
                            description="Tetapkan mata kuliah melalui menu Penempatan Matkul."
                        />
                    ) : (
                        <ul className="divide-y divide-line-soft">
                            {asdos.courses.map((course) => (
                                <li
                                    key={course.id}
                                    className="flex flex-wrap items-center gap-2 px-4 py-3 sm:px-5"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-ink">{course.name}</p>
                                        <p className="text-xs text-ink-soft">Semester {course.semester}</p>
                                    </div>
                                    <Badge
                                        className={
                                            course.used >= course.max
                                                ? 'danger'
                                                : 'success'
                                        }
                                    >
                                        {course.used}/{course.max} praktikum
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card>
                    <CardHeader
                        title="Praktikum yang Diampu"
                        description={`Total praktikum: ${asdos.used_quota}/${asdos.max_classes}`}
                    />
                    {asdos.practicums.length === 0 ? (
                        <EmptyState title="Belum ada praktikum" description="Asdos ini belum dipilih oleh kelas mana pun." />
                    ) : (
                        <TableWrap>
                            <thead>
                                <tr>
                                    <Th>Mata Kuliah</Th>
                                    <Th>Kelas</Th>
                                    <Th>Pasangan</Th>
                                    <Th>Ketua Kelas</Th>
                                    <Th>Ditempatkan</Th>
                                </tr>
                            </thead>
                            <tbody>
                                {asdos.practicums.map((item) => (
                                    <tr key={item.id}>
                                        <Td>
                                            <span className="font-medium text-ink">{item.course}</span>
                                            <span className="ml-1 text-xs text-ink-soft">Sem. {item.semester}</span>
                                        </Td>
                                        <Td>{item.class_label}</Td>
                                        <Td className="text-ink">{item.pair ?? '—'}</Td>
                                        <Td className="text-ink-soft">{item.representative ?? '—'}</Td>
                                        <Td className="text-ink-faint">{item.booked_at}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </TableWrap>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
