<?php

namespace Database\Learner\Seeders;

use Illuminate\Database\Seeder;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqCore\Services\Assessment\Exams\ExamGenerationService;

class AssessmentCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach ([
                ['code' => 'toeic', 'name' => 'TOEIC', 'description' => 'Bộ câu hỏi TOEIC Listening & Reading.'],
                ['code' => 'ielts', 'name' => 'IELTS', 'description' => 'Bộ câu hỏi IELTS Academic và General Training.'],
                ['code' => 'thpt', 'name' => 'Tốt nghiệp THPT', 'description' => 'Ngân hàng câu hỏi trắc nghiệm luyện thi tốt nghiệp THPT.'],
            ] as $examType) {
                DB::table('exam_types')->updateOrInsert(
                    ['code' => $examType['code']],
                    [...$examType, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                );
            }

            foreach ([
                ['code' => 'listening', 'name' => 'Listening'],
                ['code' => 'reading', 'name' => 'Reading'],
                ['code' => 'writing', 'name' => 'Writing'],
                ['code' => 'speaking', 'name' => 'Speaking'],
                ['code' => 'thpt', 'name' => 'Môn thi THPT'],
            ] as $sortOrder => $skill) {
                DB::table('skills')->updateOrInsert(
                    ['code' => $skill['code']],
                    [...$skill, 'sort_order' => $sortOrder + 1, 'created_at' => $now, 'updated_at' => $now],
                );
            }

            foreach ([
                ['code' => 'practice', 'name' => 'Luyện tập'],
                ['code' => 'exam', 'name' => 'Đề thi'],
            ] as $practiceMode) {
                DB::table('practice_modes')->updateOrInsert($practiceMode, $practiceMode);
            }

            foreach ([
                ['code' => 'multiple_choice', 'name' => 'Multiple Choice', 'supports_options' => true],
                ['code' => 'sentence_completion', 'name' => 'Sentence Completion', 'supports_options' => true],
                ['code' => 'note_completion', 'name' => 'Note Completion', 'supports_options' => true],
                ['code' => 'matching', 'name' => 'Matching', 'supports_options' => false],
                ['code' => 'short_answer', 'name' => 'Short Answer', 'supports_options' => false],
                ['code' => 'essay', 'name' => 'Essay', 'supports_options' => false],
            ] as $questionType) {
                DB::table('question_types')->updateOrInsert($questionType, $questionType);
            }

            foreach ([
                ['code' => 'easy', 'name' => 'Easy', 'sort_order' => 1],
                ['code' => 'medium', 'name' => 'Medium', 'sort_order' => 2],
                ['code' => 'hard', 'name' => 'Hard', 'sort_order' => 3],
            ] as $difficulty) {
                DB::table('question_difficulties')->updateOrInsert($difficulty, $difficulty);
            }

            $this->seedParts($now);
            $this->seedThptScoreScales($now);
        });
    }

    private function seedParts(object $now): void
    {
        $toeicId = DB::table('exam_types')->where('code', 'toeic')->value('id');
        $ieltsId = DB::table('exam_types')->where('code', 'ielts')->value('id');
        $thptId = DB::table('exam_types')->where('code', 'thpt')->value('id');
        $skills = DB::table('skills')->pluck('id', 'code');

        foreach ([
            ['exam_type_id' => $toeicId, 'skill_id' => $skills['listening'], 'code' => 'part-1', 'name' => 'Part 1 · Photographs', 'sort_order' => 1],
            ['exam_type_id' => $toeicId, 'skill_id' => $skills['listening'], 'code' => 'part-2', 'name' => 'Part 2 · Question-Response', 'sort_order' => 2],
            ['exam_type_id' => $toeicId, 'skill_id' => $skills['listening'], 'code' => 'part-3', 'name' => 'Part 3 · Conversations', 'sort_order' => 3],
            ['exam_type_id' => $toeicId, 'skill_id' => $skills['listening'], 'code' => 'part-4', 'name' => 'Part 4 · Talks', 'sort_order' => 4],
            ['exam_type_id' => $toeicId, 'skill_id' => $skills['reading'], 'code' => 'part-5', 'name' => 'Part 5 · Incomplete Sentences', 'sort_order' => 5],
            ['exam_type_id' => $toeicId, 'skill_id' => $skills['reading'], 'code' => 'part-6', 'name' => 'Part 6 · Text Completion', 'sort_order' => 6],
            ['exam_type_id' => $toeicId, 'skill_id' => $skills['reading'], 'code' => 'part-7', 'name' => 'Part 7 · Reading Comprehension', 'sort_order' => 7],
            ['exam_type_id' => $ieltsId, 'skill_id' => $skills['listening'], 'code' => 'listening', 'name' => 'Listening', 'sort_order' => 1],
            ['exam_type_id' => $ieltsId, 'skill_id' => $skills['reading'], 'code' => 'reading', 'name' => 'Reading', 'sort_order' => 2],
            ['exam_type_id' => $ieltsId, 'skill_id' => $skills['writing'], 'code' => 'writing-task-1', 'name' => 'Writing Task 1', 'sort_order' => 3],
            ['exam_type_id' => $ieltsId, 'skill_id' => $skills['writing'], 'code' => 'writing-task-2', 'name' => 'Writing Task 2', 'sort_order' => 4],
            ['exam_type_id' => $ieltsId, 'skill_id' => $skills['speaking'], 'code' => 'speaking', 'name' => 'Speaking', 'sort_order' => 5],
            ...array_map(fn (array $subject, int $index) => ['exam_type_id' => $thptId, 'skill_id' => $skills['thpt'], 'code' => $subject['code'], 'name' => $subject['name'], 'sort_order' => 1 + $index], ExamGenerationService::THPT_SUBJECTS, array_keys(ExamGenerationService::THPT_SUBJECTS)),
            ...array_map(fn (array $part, int $index) => ['exam_type_id' => $thptId, 'skill_id' => $skills['thpt'], 'code' => $part['code'], 'name' => $part['name'], 'sort_order' => 100 + $index], ExamGenerationService::THPT_ENGLISH_PARTS, array_keys(ExamGenerationService::THPT_ENGLISH_PARTS)),
        ] as $part) {
            DB::table('exam_parts')->updateOrInsert(
                ['exam_type_id' => $part['exam_type_id'], 'code' => $part['code']],
                [...$part, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    private function seedThptScoreScales(object $now): void
    {
        foreach ([34, 40] as $count) {
            $table = array_combine(range(0, $count), array_map(fn (int $raw) => round($raw * 10 / $count, 2), range(0, $count)));
            DB::table('score_scales')->updateOrInsert(
                ['name' => 'THPT luyện tập '.$count.' câu · thang 10'],
                ['version' => 1, 'configuration' => json_encode(['tables' => ['thpt' => $table]], JSON_UNESCAPED_UNICODE), 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
