<?php

namespace App\Http\Requests\Profile;

use App\Support\AccountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => AccountRules::name(),
            'email' => AccountRules::email($this->user()->id),
            // メールアドレスを変えるとパスワード再設定でアカウントを奪えてしまうため、
            // 変更する場合だけ現在のパスワードを求める
            'email_current_password' => [
                Rule::requiredIf(fn () => $this->input('email') !== $this->user()->email),
                'nullable',
                'current_password',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '表示名',
            'email' => 'メールアドレス',
            'email_current_password' => '現在のパスワード',
        ];
    }
}
