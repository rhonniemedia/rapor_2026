<?php

namespace App\Http\Requests\Rapor;

use App\Models\Rapor\Curriculum;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rule;

class CurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sesuaikan dengan policy / permission proyekmu
    }

    protected function prepareForValidation(): void
    {
        $description = trim((string) $this->input('description'));

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => strtoupper(trim((string) $this->input('code'))),
            'description' => $description !== '' ? $description : null,
        ]);
    }

    public function rules(): array
    {
        $bound = $this->route('curriculum');
        $ignoreId = $bound instanceof Curriculum ? $bound->id : null;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('rapor_curricula', 'name')->ignore($ignoreId)],
            'code' => ['required', 'string', 'max:50', Rule::unique('rapor_curricula', 'code')->ignore($ignoreId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in([Curriculum::STATUS_ACTIVE, Curriculum::STATUS_ARCHIVED])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama kurikulum',
            'code' => 'kode kurikulum',
            'description' => 'deskripsi',
            'status' => 'status',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kurikulum wajib diisi.',
            'name.unique' => 'Nama kurikulum sudah digunakan.',
            'code.required' => 'Kode kurikulum wajib diisi.',
            'code.unique' => 'Kode kurikulum sudah digunakan.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
        ];
    }

    /**
     * Untuk request HTMX: render ulang modal form lengkap dengan pesan error
     * (HTMX tidak menukar konten pada respons 4xx), lalu arahkan ke #modal-container.
     */
    protected function failedValidation(Validator $validator): void
    {
        if (! $this->header('HX-Request')) {
            parent::failedValidation($validator);

            return;
        }

        $bound = $this->route('curriculum');
        $curriculum = $bound instanceof Curriculum ? $bound : new Curriculum();
        $curriculum->fill($this->only(['name', 'code', 'description', 'status']));

        $errors = (new ViewErrorBag())->put('default', $validator->errors());

        throw new HttpResponseException(
            response()
                ->view('pages.academics.relations.curriculum._modal-form', [
                    'curriculum' => $curriculum,
                    'statusOptions' => Curriculum::statusOptions(),
                    'errors' => $errors,
                ])
                ->withHeaders(['HX-Retarget' => '#modal-container', 'HX-Reswap' => 'innerHTML'])
        );
    }
}
