import { Link, router } from '@inertiajs/react';
import AppLayout from '../../../Components/AppLayout';
import { Badge, Card, EmptyState, Select, TableWrap, Td, Th } from '../../../Components/UI';
import { logAction } from '../../../lib/status';

export default function History({ logs, filters, actions }) {
    return (
        <AppLayout
            title="Riwayat Booking"
            heading="Riwayat Booking"
            subheading="Catatan seluruh aktivitas booking, perubahan, dan pembatalan"
        >
            <Card>
                <div className="border-b border-line p-3">
                    <Select
                        value={filters.action ?? ''}
                        onChange={(e) =>
                            router.get(
                                '/admin/riwayat',
                                { action: e.target.value || undefined },
                                { preserveState: true, replace: true },
                            )
                        }
                        className="sm:max-w-xs"
                    >
                        <option value="">Semua aktivitas</option>
                        {actions.map((action) => (
                            <option key={action} value={action}>
                                {logAction(action).label}
                            </option>
                        ))}
                    </Select>
                </div>

                {logs.data.length === 0 ? (
                    <EmptyState title="Belum ada riwayat" description="Aktivitas booking akan tercatat di sini." />
                ) : (
                    <TableWrap>
                        <thead>
                            <tr>
                                <Th>Waktu</Th>
                                <Th>Pelaku</Th>
                                <Th>Aktivitas</Th>
                                <Th>Keterangan</Th>
                                <Th>Perubahan</Th>
                                <Th>IP</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.map((log) => {
                                const action = logAction(log.action);

                                return (
                                    <tr key={log.id}>
                                        <Td className="whitespace-nowrap text-xs text-ink-soft">{log.at}</Td>
                                        <Td className="font-medium text-ink">{log.user}</Td>
                                        <Td>
                                            <Badge dot tone={action.tone}>{action.label}</Badge>
                                        </Td>
                                        <Td className="text-ink-soft">{log.description ?? '—'}</Td>
                                        <Td className="text-xs text-ink-soft">
                                            {log.old_value || log.new_value
                                                ? `${log.old_value ?? '—'} → ${log.new_value ?? '—'}`
                                                : '—'}
                                        </Td>
                                        <Td className="text-xs text-ink-faint">{log.ip_address ?? '—'}</Td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </TableWrap>
                )}

                {logs.links?.length > 3 && (
                    <div className="flex flex-wrap gap-1 border-t border-line p-3">
                        {logs.links.map((link, index) =>
                            link.url ? (
                                <Link
                                    key={index}
                                    href={link.url}
                                    preserveState
                                    className={`rounded-lg px-3 py-1.5 text-sm ${
                                        link.active
                                            ? 'bg-brand-600 text-white'
                                            : 'text-ink-soft ring-1 ring-inset ring-line hover:bg-canvas'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={index}
                                    className="rounded-lg px-3 py-1.5 text-sm text-ink-faint"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ),
                        )}
                    </div>
                )}
            </Card>
        </AppLayout>
    );
}
