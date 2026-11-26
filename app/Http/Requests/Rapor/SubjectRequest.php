<?php

namespace App\Http\Requests\Rapor;

use App\Models\Rapor\Subject;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isReligion = filter_var($this->input('is_religion_subject', false), FILTER_VALIDATE_BOOLEAN);
        $religion = trim((string) $this->input('religion'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'is_religion_subject' => $isReligion,
            'religion' => $isReligion && $religion !== '' ? $religion : null,
        ]);
    }

    public function rules(): array
    {
        $bound = $this->route('subject');
        $ignoreId = $bound instanceof Subject ? $bound->id : null;
        $religionValue = $this->input('religion');

        $uniqueCodeReligion = Rule::unique('rapor_subjects', 'code')
            ->where(function ($query) use ($religionValue) {
                if ($religionValue === null || $religionValue === '') {
                    $query->whereNull('religion');
                } else {
                    $query->where('religion', $religionValue);
                }
            })
            ->ignore($ignoreId);

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', $uniqueCodeReligion],
            'is_religion_subject' => ['required', 'boolean'],
            'religion' => [
                Rule::requiredIf(fn() => (bool) $this->boolean('is_religion_subject')),
                'nullable',
                'string',
                'max:100',
            ],
            'status' => ['required', Rule::in([Subject::STATUS_ACTIVE, Subject::STATUS_ARCHIVED])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama mata pelajaran',
            'code' => 'kode mata pelajaran',
            'is_religion_subject' => 'kategori mata pelajaran',
            'religion' => 'agama terkait',
            'status' => 'status',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama mata pelajaran wajib diisi.',
            'code.required' => 'Kode mata pelajaran wajib diisi.',
            'code.unique' => 'Kombinasi kode dan agama mata pelajaran sudah digunakan.',
            'is_religion_subject.required' => 'Kategori mata pelajaran wajib dipilih.',
            'religion.required' => 'Agama terkait wajib diisi untuk mata pelajaran agama.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if (! $this->header('HX-Request')) {
            parent::failedValidation($validator);

            return;
        }

        $bound = $this->route('subject');
        $subject = $bound instanceof Subject ? $bound : new Subject();
        $subject->fill($this->only(['name', 'code', 'is_religion_subject', 'religion', 'status']));

        $errors = (new ViewErrorBag())->put('default', $validator->errors());

        throw new HttpResponseException(
            response()
                ->view('pages.academics.subjects.subject._modal-form', [
                    'subject' => $subject,
                    'statusOptions' => Subject::statusOptions(),
                    'errors' => $errors,
                ])
                ->withHeaders(['HX-Retarget' => '#modal-container', 'HX-Reswap' => 'innerHTML'])
        );
    }
}
