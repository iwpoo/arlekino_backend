<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WarehouseAddress>
 */
class WarehouseAddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $streets = [
            'ул. Ленина',
            'ул. Советская',
            'ул. Мира',
            'ул. Гагарина',
            'ул. Пушкина',
            'ул. Кирова',
            'ул. Марата',
            'ул. Владимирская',
        ];
        
        $cities = [
            'Москва',
            'Санкт-Петербург',
            'Новосибирск',
            'Екатеринбург',
            'Казань',
            'Нижний Новгород',
            'Челябинск',
            'Самара',
            'Омск',
            'Ростов-на-Дону',
            'Бишкек',
            'Ош',
        ];
        
        $countries = [
            'Россия',
            'Кыргызстан',
        ];
        
        $address = sprintf(
            '%s, дом %d, %s, %s',
            $streets[array_rand($streets)],
            rand(1, 100),
            $cities[array_rand($cities)],
            $countries[array_rand($countries)]
        );
        
        return [
            'address' => $address,
            'is_default' => false,
        ];
    }
}
