<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RuleSort extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ids' => 'required|array'
        ];
    }

    public function messages()
    {
        return [
            'ids.required' => '规则计划ID不能为空',
            'ids.array' => '规则计划ID格式有误'
        ];
    }
}
