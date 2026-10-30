<?php

namespace Database\Learner\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Str;
use VistiqLearner\Models\LearningItem;

/** @extends Factory<LearningItem> */
class LearningItemFactory extends Factory
{
    protected $model = LearningItem::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $term = fake()->unique()->words(2, true);

        return ['learning_item_type_id' => $this->typeId('vocabulary'), 'canonical_text' => $term, 'slug' => Str::slug($term), 'status' => 'published', 'published_at' => now()];
    }

    public function ofType(string $code): static
    {
        return $this->state(fn (array $attributes): array => ['learning_item_type_id' => $this->typeId($code)]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'draft', 'published_at' => null]);
    }

    private function typeId(string $code): int
    {
        DB::table('learning_item_types')->insertOrIgnore(['code' => $code, 'label' => $code]);

        return (int) DB::table('learning_item_types')->where('code', $code)->value('id');
    }
}
