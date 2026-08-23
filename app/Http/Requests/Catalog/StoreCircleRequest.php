<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircleRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:100'],
            'booth' => ['nullable', 'string', 'max:50'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'force' => ['nullable', 'boolean'],
            'map_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_map_image' => ['nullable', 'boolean'],
            'sellout_risk' => ['nullable', 'string', 'in:'.implode(',', \App\Enums\SelloutRisk::values())],
            'map_x' => ['nullable', 'integer', 'min:0', 'max:100'],
            'map_y' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'display_name' => 'サークル名',
            'booth' => '配置',
            'website_url' => 'WebサイトURL',
            'description' => 'メモ',
            'map_image' => '配置マップの画像',
            'sellout_risk' => '完売リスク',
            'map_x' => 'マップ上の位置',
            'map_y' => 'マップ上の位置',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_merge($this->validated(), [
            'force' => $this->boolean('force'),
            'map_image' => $this->file('map_image'),
            'remove_map_image' => $this->boolean('remove_map_image'),
            'sellout_risk' => $this->input('sellout_risk') ?: null,
            'map_x' => $this->filled('map_x') ? (int) $this->input('map_x') : null,
            'map_y' => $this->filled('map_y') ? (int) $this->input('map_y') : null,
        ]);
    }
}
