<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $courses = Course::query()
            ->withCount('practicums', 'asdos')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->string('semester')->toString(), fn ($q, $s) => $q->where('semester', $s))
            ->orderBy('semester')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
            'filters' => $request->only('q', 'semester'),
            'semesters' => Course::query()->distinct()->orderBy('semester')->pluck('semester'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Course::query()->create($data);

        return back()->with('success', 'Mata kuliah berhasil ditambahkan.');
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->update($this->validated($request, $course));

        return back()->with('success', 'Mata kuliah berhasil diperbarui.');
    }

    public function destroy(Course $course): RedirectResponse
    {
        // Kelas dan penugasan ikut terhapus lewat cascade pada foreign key.
        $course->delete();

        return back()->with('success', 'Mata kuliah berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Course $course = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('courses', 'code')->ignore($course)],
            'semester' => ['required', 'string', 'max:10'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [], [
            'name' => 'nama mata kuliah',
            'code' => 'kode',
            'semester' => 'semester',
            'status' => 'status',
        ]);
    }
}
