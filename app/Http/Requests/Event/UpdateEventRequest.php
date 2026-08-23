<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'venue_name' => ['required', 'string', 'max:100'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'days' => ['required', 'array', 'min:1', 'max:10'],
            // MySQL の DATETIME が扱える範囲に収める（現実的な開催日の範囲でもある）
            'days.*.event_date' => ['required', 'date', 'date_format:Y-m-d', 'after:2000-01-01', 'before:2100-01-01'],
            'days.*.starts_at' => ['nullable', 'date_format:H:i'],
            'days.*.ends_at' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'イベント名',
            'venue_name' => '会場名',
            'venue_address' => '会場の住所',
            'description' => '説明',
            'days' => '開催日',
            'days.*.event_date' => '開催日',
            'days.*.starts_at' => '開始時刻',
            'days.*.ends_at' => '終了時刻',
        ];
    }

    /**
     * 空行を除いた開催日の配列。
     *
     * @return array<int, array<string, mixed>>
     */
    public function days(): array
    {
        $days = $this->input('days');

        // 配列以外が送られてきた場合はバリデーションに任せる（ここで落とさない）
        if (! is_array($days)) {
            return [];
        }

        return array_values(array_filter(
            $days,
            fn ($day) => is_array($day) && isset($day['event_date']) && $day['event_date'] !== ''
        ));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['days' => $this->days()]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $days = $this->input('days', []);
            $dates = array_column(is_array($days) ? $days : [], 'event_date');

            if (count($dates) !== count(array_unique($dates))) {
                $validator->errors()->add('days', '開催日が重複しています。');
            }

            foreach ($days as $index => $day) {
                $start = $day['starts_at'] ?? null;
                $end = $day['ends_at'] ?? null;

                if ($start !== null && $start !== '' && $end !== null && $end !== '' && $end <= $start) {
                    $validator->errors()->add("days.{$index}.ends_at", '終了時刻は開始時刻より後にしてください。');
                }
            }
        });
    }
}
