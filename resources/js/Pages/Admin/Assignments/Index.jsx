import { router } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import { Badge, Card, EmptyState, Input, TableWrap, Td, Th, cx } from '../../../Components/UI';
import { availabilityOf } from '../../../lib/status';

/**
 * Matriks Asdos x Mata Kuliah. Centang menentukan siapa boleh menangani apa,
 * dan angka di sampingnya adalah KUOTA per mata kuliah — pembatas berapa kelas
 * boleh memilih Asdos tersebut untuk mata kuliah itu.
 */
export default function Index({ asdos, courses, defaultCourseQuota }) {
    const toggle = (asdosId, courseId) => {
        router.post(
            `/admin/penempatan-matkul/${asdosId}/toggle`,
            { course_id: courseId },
            { preserveScroll: true, preserveState: false },
        );
    };

    const setQuota = (asdosId, courseId, value) => {
        router.put(
            `/admin/penempatan-matkul/${asdosId}/kuota`,
            { course_id: courseId, max_practicums: Number(value) },
            { preserveScroll: true, preserveState: false },
        );
    };

    const setTotal = (asdosId, item, value) => {
        router.put(
            `/admin/penempatan-matkul/${asdosId}`,
            {
                course_ids: Object.keys(item.courses).map(Number),
                course_quotas: Object.fromEntries(
                    Object.entries(item.courses).map(([id, v]) => [id, v.max]),
                ),
                max_classes: Number(value),
            },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <AppLayout
            title="Penempatan Matkul"
            heading="Penempatan & Kuota Mata Kuliah"
            subheading="Angka pada tiap sel = berapa praktikum mata kuliah itu yang boleh diampu"
        >
            <Card>
                {asdos.length === 0 ? (
                    <EmptyState title="Belum ada Asdos" description="Tambahkan Asdos terlebih dahulu." />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th className="sticky left-0 z-10 bg-canvas">Asdos</Th>
                                <Th>Kuota Total</Th>
                                <Th>Status</Th>
                                {courses.map((course) => (
                                    <Th key={course.id} className="min-w-28 text-center">
                                        <span className="block max-w-24 whitespace-normal text-xs leading-tight">
                                            {course.name}
                                        </span>
                                        <span className="text-[10px] font-normal text-ink-faint">
                                            Sem. {course.semester}
                                        </span>
                                    </Th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {asdos.map((item) => {
                                const status = availabilityOf(item.availability);

                                return (
                                    <tr key={item.id}>
                                        <Td className="sticky left-0 z-10 bg-surface whitespace-nowrap">
                                            <span className="font-medium text-ink">{item.name}</span>
                                            <span className="block text-xs text-ink-faint">NIM {item.nim}</span>
                                        </Td>
                                        <Td className="whitespace-nowrap">
                                            <div className="flex items-center gap-1">
                                                <span className="text-xs tabular-nums text-ink-soft">
                                                    {item.used_quota}/
                                                </span>
                                                <Input
                                                    size="sm"
                                                    type="number"
                                                    min={1}
                                                    max={20}
                                                    defaultValue={item.max_classes}
                                                    onBlur={(e) =>
                                                        Number(e.target.value) !== item.max_classes &&
                                                        setTotal(item.id, item, e.target.value)
                                                    }
                                                    className="w-14 shrink-0 px-2 text-center"
                                                />
                                            </div>
                                        </Td>
                                        <Td>
                                            <Badge dot tone={status.tone}>
                                                {status.label}
                                            </Badge>
                                        </Td>
                                        {courses.map((course) => {
                                            const cell = item.courses[course.id];
                                            const assigned = Boolean(cell);
                                            const full = assigned && cell.used >= cell.max;

                                            return (
                                                <Td key={course.id} className="text-center align-top">
                                                    <input
                                                        type="checkbox"
                                                        className="h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500"
                                                        checked={assigned}
                                                        onChange={() => toggle(item.id, course.id)}
                                                        aria-label={`${item.name} — ${course.name}`}
                                                    />
                                                    {assigned && (
                                                        <div className="mt-1 flex items-center justify-center gap-0.5">
                                                            <span
                                                                className={cx(
                                                                    'shrink-0 text-[10px] tabular-nums',
                                                                    full ? 'font-semibold text-rose-600' : 'text-ink-soft',
                                                                )}
                                                                title={`${cell.used} praktikum sedang diampu`}
                                                            >
                                                                {cell.used}/
                                                            </span>
                                                            <Input
                                                                size="sm"
                                                                type="number"
                                                                min={1}
                                                                max={20}
                                                                defaultValue={cell.max}
                                                                onBlur={(e) =>
                                                                    Number(e.target.value) !== cell.max &&
                                                                    setQuota(item.id, course.id, e.target.value)
                                                                }
                                                                className="h-7 w-12 shrink-0 px-1 text-center"
                                                                aria-label={`Kuota ${item.name} untuk ${course.name}`}
                                                            />
                                                        </div>
                                                    )}
                                                </Td>
                                            );
                                        })}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </TableWrap>
                )}
            </Card>

            <div className="mt-3 space-y-1 text-xs text-ink-soft">
                <p>
                    Centang untuk mengizinkan Asdos menangani mata kuliah tersebut — kuota bawaan{' '}
                    <strong>{defaultCourseQuota}</strong> praktikum, dapat diubah di kotak angka. Format{' '}
                    <strong>terpakai/kuota</strong>; angka merah berarti kuota mata kuliah itu sudah penuh dan Asdos
                    tidak akan muncul lagi untuk kelas lain di mata kuliah tersebut.
                </p>
                <p>
                    <strong>Kuota Total</strong> adalah batas atas lintas semua mata kuliah. Booking hanya lolos bila
                    kuota mata kuliah dan kuota total sama-sama masih ada sisa. Perubahan tersimpan otomatis.
                </p>
            </div>
        </AppLayout>
    );
}
