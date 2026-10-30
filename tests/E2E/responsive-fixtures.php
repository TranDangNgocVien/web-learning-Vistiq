<?php

// Reuse the existing isolated browser fixture; never touch the running application's data.
require __DIR__.'/bootstrap.php';

auth()->loginUsingId($actors['admin']);
$revisions = app(\VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService::class);
$subjective = [];
foreach (['writing', 'speaking'] as $skill) {
    $parts = \VistiqCore\Models\ExamPart::where('code', 'like', $skill === 'writing' ? 'writing-task-%' : 'speaking-part-%')->orderBy('sort_order')->get();
    foreach ($parts as $part) {
        $question = \Database\Admin\Factories\QuestionBankItemFactory::new()->create([
            'exam_part_id' => $part->id,
            'question_type_id' => \VistiqCore\Models\QuestionType::where('code', $skill === 'writing' ? 'essay' : 'speaking')->value('id'),
            'prompt' => $skill === 'writing' ? 'Some people believe that schools should teach practical skills alongside academic subjects. Discuss both views and give your own opinion.' : 'Describe a place you enjoy visiting. Explain where it is, how often you visit, and what makes the place special to you.',
        ]);
        $payload = $revisions->payload('question', $question);
        $payload['metadata'] = ['rubric' => \VistiqCore\Services\Assessment\QuestionBank\SubjectiveQuestionWorkbookService::defaultRubric($skill), 'variant' => 'academic'];
        if ($skill === 'writing') $payload['metadata']['min_words'] = str_ends_with($part->code, '2') ? 250 : 150;
        else $payload['metadata'] += ['cue_card' => 'Say where the place is, what you do there, and why you like it.', 'follow_ups' => ['Why do people enjoy visiting new places?', 'How has travel changed in recent years?']];
        $revisions->save('question', $question, $payload, 0);
        $subjective[$skill][] = $question->id;
    }
}
$draftExam = \Database\Admin\Factories\ExamTemplateFactory::new()->create(['title' => 'Responsive IELTS Writing and Speaking draft', 'status' => 'draft']);
$fixture = json_decode(file_get_contents(storage_path('fixture.json')), true);
$fixture['subjective'] = $subjective;
$fixture['draft_exam'] = $draftExam->id;
$fixture['knowledge'] = \VistiqCore\Services\Shared\CompactDatabase::table('learning_items')->where('slug', 'e2e-library-0')->value('id');
$image = file_get_contents(public_path('images/vistiq-logo.png'));
\Illuminate\Support\Facades\Storage::disk('public')->put('media/responsive-fixture.png', $image);
$fixture['resource'] = \VistiqCore\Models\MediaAsset::create(['title'=>'Responsive image resource', 'disk'=>'public', 'path'=>'media/responsive-fixture.png', 'mime_type'=>'image/png', 'size'=>strlen($image), 'created_by'=>$actors['admin'], 'metadata'=>['tags'=>['IELTS', 'Writing']]])->id;
$source = \VistiqCore\Models\ExamTemplate::findOrFail($fixture['exams']['reading']);
$speakingExam = $source->replicate(['published_revision_id']);
$speakingExam->title = 'E2E Speaking exam';
$speakingExam->slug = 'responsive-speaking-'.\Illuminate\Support\Str::uuid();
$speakingExam->save();
$snapshot = $source->publishedRevision->payload;
$part = \VistiqCore\Models\ExamPart::where('code', 'speaking-part-2')->firstOrFail();
$snapshot['exam_id'] = $speakingExam->id;
$snapshot['title'] = $speakingExam->title;
$snapshot['exam_type'] = 'ielts';
$snapshot['items'][0] = array_replace($snapshot['items'][0], ['id'=>$subjective['speaking'][1], 'type'=>'speaking', 'skill'=>'speaking', 'exam_type'=>'ielts', 'part'=>'speaking-part-2', 'prompt'=>'Describe a place you enjoy visiting.', 'metadata'=>['cue_card'=>'Say where the place is and why you enjoy visiting it.', 'rubric'=>\VistiqCore\Services\Assessment\QuestionBank\SubjectiveQuestionWorkbookService::defaultRubric('speaking')]]);
$snapshot['sections'][0] = array_replace($snapshot['sections'][0], ['title'=>'Speaking', 'skill'=>'speaking', 'part'=>$part->code, 'exam_part_id'=>$part->id]);
$revision = \VistiqCore\Models\ExamRevision::create(['exam_template_id'=>$speakingExam->id, 'number'=>1, 'payload'=>$snapshot]);
$speakingExam->update(['published_revision_id'=>$revision->id]);
$fixture['exams']['speaking'] = $speakingExam->id;
file_put_contents(storage_path('fixture.json'), json_encode($fixture, JSON_THROW_ON_ERROR));
