<?php

declare(strict_types=1);

namespace App\Extensions\ChatbotEcommerce\System\Http\Requests;

class AddCartLineRequest extends PutCartLineRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['quantity'] = ['required', 'integer', 'min:1', 'max:999'];
        return $rules;
    }
}
