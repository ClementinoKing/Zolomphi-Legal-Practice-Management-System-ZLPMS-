<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyDriveDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('company-files.upload') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folder_id' => ['required', 'integer', 'exists:company_drive_folders,id'],
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*' => ['file', 'max:25600', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png'],
        ];
    }
}
