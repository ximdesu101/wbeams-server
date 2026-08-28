<?php

namespace App\Imports;

use App\Models\Admin\Masterlist;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class MasterlistImport implements ToCollection, WithHeadingRow, WithValidation, SkipsOnFailure, SkipsOnError
{
    use SkipsFailures, SkipsErrors;

    public int $importedCount = 0;

    /** @param Collection<int, array<string, mixed>> $rows */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            Masterlist::create([
                'id_number' => $row['id_number'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'role' => strtolower(trim($row['role'])),
                'student_program' => $row['student_program'] ?: null,
                'student_year' => $row['student_year'] ? strtolower(trim($row['student_year'])) : null,
            ]);

            $this->importedCount++;
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id_number' => ['required', 'string', 'unique:masterlists,id_number'],
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'role' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! in_array(strtolower(trim($value)), ['student', 'faculty', 'staff'])) {
                    $fail('The selected role is invalid.');
                }
            }],
            'student_program' => [
                'nullable',
                'required_if:role,student,Student,STUDENT',
                'in:BSIT,BSCrim,BEED,BTLED,BSABE,BSA,BSF,BAT',
            ],
            'student_year' => [
                'nullable',
                'required_if:role,student,Student,STUDENT',
                function ($attribute, $value, $fail) {
                    if ($value && ! in_array(strtolower(trim($value)), ['1st year', '2nd year', '3rd year', '4th year'])) {
                        $fail('The selected student year is invalid.');
                    }
                },
            ],
        ];
    }
}