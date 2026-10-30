<?php

namespace Database\Admin\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqCore\Models\ExamTemplate;

/** @extends Factory<ExamTemplate> */
class ExamTemplateFactory extends Factory
{
    protected $model = ExamTemplate::class;

    public function definition(): array
    {
        return ['title' => fake()->sentence(3), 'slug' => fake()->unique()->slug(), 'exam_type_id' => DB::table('exam_types')->where('code', 'toeic')->value('id'), 'practice_mode_id' => DB::table('practice_modes')->value('id'), 'mode' => 'mini', 'status' => 'draft', 'draft_status' => 'draft'];
    }
}
