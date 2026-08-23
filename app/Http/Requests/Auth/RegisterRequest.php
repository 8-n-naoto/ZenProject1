<?php

namespace App\Http\Requests\Auth;

use App\Support\AccountRules;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => AccountRules::userId(),
            'name' => AccountRules::name(),
            'email' => AccountRules::email(),
            'password' => AccountRules::password(confirmed: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'ログインID',
            'name' => '表示名',
            'email' => 'メールアドレス',
            'password' => 'パスワード',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return AccountRules::messages();
    }
}
