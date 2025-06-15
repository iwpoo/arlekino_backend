<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        if ($user->isClient()) {
            return [
                'phone' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'unique:users,email,'.$user->id],
                'gender' => ['nullable', 'in:male,female,other'],
                'currency' => ['nullable', 'string', 'size:3'],
                'payment_methods' => ['nullable', 'array', 'in:card,paypal,apple_pay,gpay'],
                'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            ];
        } else {
            return [
                'name' => ['nullable', 'string', 'max:255'],
                'username' => ['nullable', 'string', 'max:255', 'unique:users,username,'.$user->id],
                'description' => ['nullable', 'string'],
                'phone' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'unique:users,email,'.$user->id],
                'website' => ['nullable', 'url'],
                'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
                'shop_cover' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
                'warehouse_addresses' => ['nullable', 'array'],
                'warehouse_addresses.*.address' => ['required_with:warehouse_addresses', 'string'],
                'warehouse_addresses.*.is_default' => ['boolean'],
            ];
        }
    }

//    public function validated($key = null, $default = null): array
//    {
//        $data = parent::validated();
//
//        unset($data['avatar'], $data['shop_cover']);
//
//        return $data;
//    }
}
