<?php

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor data booking ke Excel, mengikuti penyaring yang sedang aktif pada
 * halaman Booking & Penempatan.
 */
class BookingsExport extends DefaultValueBinder implements FromCollection, ShouldAutoSize, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  string  $status  aktif | dibatalkan | semua
     * @param  int|null  $courseId  penyaring mata kuliah
     */
    public function __construct(
        private readonly string $status = 'aktif',
        private readonly ?int $courseId = null,
    ) {}

    /** Penomoran baris; disimpan per instance agar tidak bocor antar ekspor. */
    private int $rowNumber = 0;

    public function title(): string
    {
        return 'Data Booking';
    }

    public function collection(): Collection
    {
        return Booking::query()
            ->with([
                'practicum.course:id,name,code,semester',
                'practicum.classRoom:id,class_name,angkatan,semester',
                'practicum.classRoom.representative:id,name,phone',
                'pair.asdosOne.user:id,name,phone',
                'pair.asdosTwo.user:id,name,phone',
                'representative:id,name,phone',
                'canceller:id,name',
            ])
            ->when($this->status !== 'semua', fn ($q) => $q->where('status', $this->status))
            ->when($this->courseId, fn ($q, $id) => $q
                ->whereHas('practicum', fn ($p) => $p->where('course_id', $id)))
            ->get()
            // Diurutkan agar berkas mudah dibaca: angkatan terbaru dulu, lalu
            // kelas, lalu mata kuliah.
            ->sortBy(fn (Booking $b) => [
                -(int) ($b->practicum?->classRoom?->angkatan ?? 0),
                $b->practicum?->classRoom?->class_name ?? '',
                $b->practicum?->course?->name ?? '',
            ])
            ->values();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return [
            'No',
            'Angkatan',
            'Kelas',
            'Semester',
            'Mata Kuliah',
            'Kode MK',
            'Pasangan Asdos',
            'Asdos 1',
            'NIM Asdos 1',
            'Asdos 2',
            'NIM Asdos 2',
            'Ketua Kelas',
            'No. HP Ketua Kelas',
            'Status',
            'Waktu Booking',
            'Waktu Dibatalkan',
            'Dibatalkan Oleh',
            'Catatan',
        ];
    }

    /**
     * @param  Booking  $booking
     * @return array<int, mixed>
     */
    public function map($booking): array
    {
        $this->rowNumber++;

        $members = $booking->pair?->members() ?? collect();
        $first = $members->get(0);
        $second = $members->get(1);
        $representative = $booking->representative ?? $booking->practicum?->classRoom?->representative;

        return [
            $this->rowNumber,
            $booking->practicum?->classRoom?->angkatan,
            $booking->practicum?->classRoom?->class_name,
            $booking->practicum?->course?->semester,
            $booking->practicum?->course?->name,
            $booking->practicum?->course?->code,
            $booking->pair?->label(),
            $first?->user?->name,
            $first?->nim,
            $second?->user?->name,
            $second?->nim,
            $representative?->name,
            $representative?->phone,
            $booking->status === Booking::STATUS_AKTIF ? 'Aktif' : 'Dibatalkan',
            $booking->booked_at?->translatedFormat('d/m/Y H:i'),
            $booking->cancelled_at?->translatedFormat('d/m/Y H:i'),
            $booking->canceller?->name,
            $booking->note,
        ];
    }

    /**
     * Menyimpan setiap nilai teks sebagai TEKS, bukan angka. Tanpa ini NIM dan
     * nomor HP diubah Excel menjadi bilangan sehingga angka nol di depan
     * (mis. 081300002024) ikut hilang.
     */
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && $value !== '') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** @return array<string, int> */
    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'E' => 28,
            'G' => 26,
            'H' => 18,
            'J' => 18,
            'L' => 20,
            'R' => 30,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4F46E5']],
            ],
        ];
    }
}
