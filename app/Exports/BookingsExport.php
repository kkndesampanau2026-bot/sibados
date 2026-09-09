<?php

namespace App\Exports;

use App\Models\Booking;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor data booking ke Excel, mengikuti penyaring yang sedang aktif pada
 * halaman Booking & Penempatan.
 *
 * Tata letaknya meniru daftar penempatan yang biasa dipakai Koordinator: lima
 * kolom saja, dikelompokkan per mata kuliah, dengan sel Mata Kuliah dan
 * Angkatan digabung untuk baris-baris yang sekelompok.
 */
class BookingsExport extends DefaultValueBinder implements FromCollection, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** Warna latar bergantian antar kelompok mata kuliah. */
    private const GROUP_COLORS = ['FBE0DF', 'FFF2CC', 'E2EFDA', 'D9D9D9', 'D6DCE4', 'FFE699'];

    /**
     * @param  string  $status  aktif | dibatalkan | semua
     * @param  int|null  $courseId  penyaring mata kuliah
     */
    public function __construct(
        private readonly string $status = 'aktif',
        private readonly ?int $courseId = null,
    ) {
        $this->rows = new Collection;
    }

    /** Penomoran baris; disimpan per instance agar tidak bocor antar ekspor. */
    private int $rowNumber = 0;

    /**
     * Hasil collection() disimpan karena styles() membutuhkannya untuk
     * menentukan batas tiap kelompok yang digabung dan diwarnai.
     *
     * @var Collection<int, Booking>
     */
    private Collection $rows;

    public function title(): string
    {
        return 'Data Booking';
    }

    public function collection(): Collection
    {
        return $this->rows = Booking::query()
            ->with([
                'practicum.course:id,name,semester',
                'practicum.classRoom:id,class_name,angkatan,semester',
                'pair.asdosOne.user:id,name',
                'pair.asdosTwo.user:id,name',
            ])
            ->when($this->status !== 'semua', fn ($q) => $q->where('status', $this->status))
            ->when($this->courseId, fn ($q, $id) => $q
                ->whereHas('practicum', fn ($p) => $p->where('course_id', $id)))
            ->get()
            // Urutan menentukan pengelompokan: seluruh baris satu mata kuliah
            // harus berdampingan agar selnya dapat digabung.
            ->sortBy(fn (Booking $b) => [
                $b->practicum?->course?->semester ?? '',
                $b->practicum?->course?->name ?? '',
                -(int) ($b->practicum?->classRoom?->angkatan ?? 0),
                $b->practicum?->classRoom?->class_name ?? '',
            ])
            ->values();
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['No', 'Nama Asdos', 'Kelas', 'Mata Kuliah', 'Angkatan'];
    }

    /**
     * @param  Booking  $booking
     * @return array<int, mixed>
     */
    public function map($booking): array
    {
        $this->rowNumber++;

        return [
            $this->rowNumber,
            $booking->pair?->label(),
            $booking->practicum?->classRoom?->class_name,
            $booking->practicum?->course?->name,
            $booking->practicum?->classRoom?->angkatan,
        ];
    }

    /**
     * Menyimpan setiap nilai teks sebagai TEKS, bukan angka. Tanpa ini nama
     * kelas berupa angka (mis. "01") diubah Excel menjadi bilangan sehingga
     * angka nol di depan ikut hilang.
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
            'A' => 6,
            'B' => 54,
            'C' => 10,
            'D' => 34,
            'E' => 12,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(26);

        $lastRow = max($this->rows->count() + 1, 1);

        $sheet->getStyle('A1:E'.$lastRow)->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '8EA9DB']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $this->groupRows($sheet);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
            ],
        ];
    }

    /**
     * Mewarnai tiap kelompok mata kuliah lalu menggabungkan sel Mata Kuliah
     * dan Angkatan yang berulang, seperti daftar penempatan manual.
     */
    private function groupRows(Worksheet $sheet): void
    {
        if ($this->rows->isEmpty()) {
            return;
        }

        $courseKeys = [];
        $angkatanKeys = [];

        foreach ($this->rows as $booking) {
            $course = (string) ($booking->practicum?->course_id ?? '-');
            $courseKeys[] = $course;
            $angkatanKeys[] = $course.'|'.($booking->practicum?->classRoom?->angkatan ?? '-');
        }

        foreach ($this->runs($courseKeys) as $index => [$start, $end]) {
            $sheet->getStyle(sprintf('A%d:E%d', $start, $end))->applyFromArray([
                'fill' => [
                    'fillType' => 'solid',
                    'startColor' => ['rgb' => self::GROUP_COLORS[$index % count(self::GROUP_COLORS)]],
                ],
            ]);

            if ($end > $start) {
                $sheet->mergeCells(sprintf('D%d:D%d', $start, $end));
            }
        }

        foreach ($this->runs($angkatanKeys) as [$start, $end]) {
            if ($end > $start) {
                $sheet->mergeCells(sprintf('E%d:E%d', $start, $end));
            }
        }
    }

    /**
     * Mengelompokkan nilai sama yang berurutan menjadi rentang baris lembar
     * kerja. Baris 1 dipakai judul, sehingga data dimulai pada baris 2.
     *
     * @param  list<string>  $values
     * @return list<array{0: int, 1: int}>
     */
    private function runs(array $values): array
    {
        $runs = [];
        $start = 0;
        $count = count($values);

        for ($i = 1; $i <= $count; $i++) {
            if ($i === $count || $values[$i] !== $values[$start]) {
                $runs[] = [$start + 2, $i + 1];
                $start = $i;
            }
        }

        return $runs;
    }
}
