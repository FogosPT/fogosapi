<?php

namespace Database\Factories;

use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        $dateTime = $this->faker->dateTime;

        return [
            'id' => (string) $this->faker->randomDigit,
            'coords' => $this->faker->boolean,
            'dateTime' => $dateTime,
            'date' => $dateTime->format('d-m-Y'),
            'hour' => $dateTime->format('H:i'),
            'location' => $this->faker->state,
            'aerial' => $this->faker->randomDigit,
            'terrain' => $this->faker->randomDigit,
            'man' => $this->faker->randomDigit,
            'district' => $this->faker->state,
            'concelho' => $this->faker->state,
            'dico' => (string) $this->faker->randomDigit,
            'freguesia' => $this->faker->state,
            'lat' => $this->faker->latitude,
            'lng' => $this->faker->longitude,
            'naturezaCode' => (string) $this->faker->randomElement(Incident::NATUREZA_CODE_FIRE),
            'natureza' => $this->faker->word,
            'statusCode' => $this->faker->randomElement(Incident::ACTIVE_STATUS_CODES),
            'statusColor' => static fn (array $attributes) => Incident::STATUS_COLORS[$attributes['statusCode']],
            'status' => $this->faker->sentence,
            'important' => $this->faker->boolean,
            'localidade' => $this->faker->state,
            'active' => false,
            'sadoId' => (string) $this->faker->randomDigit,
            'sharepointId' => (string) $this->faker->randomDigit,
            'extra' => '',
            'disappear' => $this->faker->boolean,
            'detailLocation' => $this->faker->state,
            'isFire' => false,
            'isUrbanFire' => false,
            'isTransporteFire' => false,
            'isOtherFire' => false,
            'isOtherIncident' => true,
            'created' => static fn (array $attributes) => $attributes['dateTime'],
            'updated' => static fn (array $attributes) => $attributes['dateTime'],
        ];
    }

    public function active(): self
    {
        return $this->state([
            'active' => true,
        ]);
    }

    public function fire(): self
    {
        return $this->state([
            'isFire' => true,
            'isUrbanFire' => false,
            'isTransporteFire' => false,
            'isOtherFire' => false,
            'isOtherIncident' => false,
        ]);
    }

    public function urbanFire(): self
    {
        return $this->state([
            'isFire' => false,
            'isUrbanFire' => true,
            'isTransporteFire' => false,
            'isOtherFire' => false,
            'isOtherIncident' => false,
        ]);
    }

    public function transportFire(): self
    {
        return $this->state([
            'isFire' => false,
            'isUrbanFire' => false,
            'isTransporteFire' => true,
            'isOtherFire' => false,
            'isOtherIncident' => false,
        ]);
    }

    public function otherFire(): self
    {
        return $this->state([
            'isFire' => false,
            'isUrbanFire' => false,
            'isTransporteFire' => false,
            'isOtherFire' => true,
            'isOtherIncident' => false,
        ]);
    }

    public function other(): self
    {
        return $this->state([
            'isFire' => false,
            'isUrbanFire' => false,
            'isTransporteFire' => false,
            'isOtherFire' => false,
            'isOtherIncident' => true,
        ]);
    }
}
