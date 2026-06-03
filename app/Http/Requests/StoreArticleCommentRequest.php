<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreArticleCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'min:2', 'max:80'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'author_name.required' => 'Атыңызды енгізіңіз.',
            'author_name.min' => 'Аты кемінде 2 таңба болуы керек.',
            'body.required' => 'Пікір мәтінін жазыңыз.',
            'body.min' => 'Пікір тым қысқа.',
            'body.max' => 'Пікір 2000 таңбадан аспауы керек.',
        ];
    }
}
