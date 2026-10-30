<?php

namespace Database\Admin\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqCore\Models\QuestionBankItem;

/** @extends Factory<QuestionBankItem> */
class QuestionBankItemFactory extends Factory
{
    protected $model = QuestionBankItem::class;

    public function definition(): array
    {
        return ['learning_item_id' => LearningItemFactory::new()->ofType('question')->draft(), 'exam_part_id' => DB::table('exam_parts')->where('code', 'part-5')->value('id'), 'question_type_id' => DB::table('question_types')->where('code', 'multiple_choice')->value('id'), 'question_difficulty_id' => DB::table('question_difficulties')->value('id'), 'prompt' => 'Choose the correct answer.'];
    }
}
