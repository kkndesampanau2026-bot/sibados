import { Button, Modal } from './UI';

/** Dialog konfirmasi untuk aksi yang tidak dapat dibatalkan. */
export default function ConfirmDialog({
    open,
    onClose,
    onConfirm,
    processing = false,
    title = 'Konfirmasi',
    message,
    confirmLabel = 'Ya, Lanjutkan',
    variant = 'danger',
    children,
}) {
    return (
        <Modal
            open={open}
            onClose={() => !processing && onClose()}
            title={title}
            footer={
                <>
                    <Button variant="secondary" disabled={processing} onClick={onClose}>
                        Batal
                    </Button>
                    <Button variant={variant} disabled={processing} onClick={onConfirm}>
                        {processing ? 'Memproses…' : confirmLabel}
                    </Button>
                </>
            }
        >
            {message && <p className="text-sm text-ink">{message}</p>}
            {children}
        </Modal>
    );
}
