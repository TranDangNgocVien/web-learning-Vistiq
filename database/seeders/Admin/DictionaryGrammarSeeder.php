<?php

namespace Database\Admin\Seeders;

use Illuminate\Database\Seeder;
use VistiqCore\Services\Shared\CompactDatabase as DB;

class DictionaryGrammarSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            DB::table('learning_item_types')->insertOrIgnore(['code' => 'grammar', 'label' => 'Ngữ pháp', 'sort_order' => 5]);
            $typeId = DB::table('learning_item_types')->where('code', 'grammar')->value('id');
            DB::table('grammar_categories')->updateOrInsert(['slug' => 'word-usage'], ['name' => 'Ngữ pháp và cách dùng từ', 'description' => 'Cấu trúc cơ bản để dùng từ trong câu.', 'updated_at' => now()]);
            $categoryId = DB::table('grammar_categories')->where('slug', 'word-usage')->value('id');

            foreach ($this->topics() as $slug => $topic) {
                DB::table('learning_items')->updateOrInsert(['slug' => $slug], [
                    'learning_item_type_id' => $typeId, 'canonical_text' => $topic['title'],
                    'status' => 'published', 'published_at' => now(), 'updated_at' => now(),
                ]);
                $itemId = DB::table('learning_items')->where('slug', $slug)->value('id');
                DB::table('grammar_topics')->updateOrInsert(['learning_item_id' => $itemId], [
                    'grammar_category_id' => $categoryId, 'summary' => $topic['summary'], 'updated_at' => now(),
                ]);
                $topicId = DB::table('grammar_topics')->where('learning_item_id', $itemId)->value('id');
                DB::table('grammar_rules')->updateOrInsert(['grammar_topic_id' => $topicId, 'sort_order' => 1], [
                    'title' => $topic['title'], 'formula' => $topic['formula'], 'explanation' => $topic['explanation'], 'updated_at' => now(),
                ]);
                $ruleId = DB::table('grammar_rules')->where('grammar_topic_id', $topicId)->where('sort_order', 1)->value('id');
                DB::table('grammar_examples')->updateOrInsert(['grammar_rule_id' => $ruleId, 'sort_order' => 1], [
                    'english' => $topic['example'][0], 'vietnamese' => $topic['example'][1], 'updated_at' => now(),
                ]);

                foreach ($topic['tags'] as $tag) {
                    DB::table('tags')->updateOrInsert(['slug' => $tag], ['name' => $tag, 'updated_at' => now()]);
                    $tagId = DB::table('tags')->where('slug', $tag)->value('id');
                    DB::table('learning_item_tags')->insertOrIgnore(['learning_item_id' => $itemId, 'tag_id' => $tagId]);
                }

                $wordIds = DB::table('learning_items')->where('learning_item_type_id', '!=', $typeId)->whereIn('canonical_text', $topic['words'])->pluck('id');
                foreach ($wordIds as $wordId) {
                    DB::table('learning_item_relations')->updateOrInsert([
                        'source_learning_item_id' => $wordId, 'target_learning_item_id' => $itemId, 'relation_type' => 'related_grammar',
                    ], ['updated_at' => now()]);
                }
            }
        });
        DB::fillClassifications();
    }

    /** @return array<string, array{title: string, summary: string, formula: string, explanation: string, example: array{string, string}, tags: array<int, string>, words: array<int, string>}> */
    private function topics(): array
    {
        return [
            'present-perfect' => [
                'title' => 'Thì hiện tại hoàn thành · Present Perfect',
                'summary' => 'Diễn tả trải nghiệm, kết quả hiện tại hoặc hành động bắt đầu trong quá khứ còn tiếp diễn.',
                'formula' => 'S + have / has + V3 (past participle)',
                'explanation' => 'Dùng have với I, you, we, they; dùng has với he, she, it. Động từ thường dùng dạng quá khứ phân từ (achieve → achieved); động từ bất quy tắc cần tra dạng V3. Không dùng thì này với thời điểm quá khứ đã kết thúc như yesterday.',
                'example' => ['She has achieved her goal.', 'Cô ấy đã đạt được mục tiêu của mình.'],
                'tags' => ['pos-verb'], 'words' => ['achieve'],
            ],
            'simple-past' => [
                'title' => 'Thì quá khứ đơn · Simple Past',
                'summary' => 'Diễn tả sự việc đã xảy ra và kết thúc ở một thời điểm trong quá khứ.',
                'formula' => 'S + V2 / V-ed\nS + did not + V\nDid + S + V?',
                'explanation' => 'Động từ có quy tắc thêm -ed; động từ bất quy tắc dùng dạng V2. Trong câu phủ định và câu hỏi với did, động từ chính trở về dạng nguyên mẫu. Riêng be dùng was / were.',
                'example' => ['They achieved excellent results last year.', 'Họ đã đạt được kết quả xuất sắc vào năm ngoái.'],
                'tags' => ['pos-verb'], 'words' => ['achieve'],
            ],
            'verb-object' => [
                'title' => 'Động từ và tân ngữ · Verb + Object',
                'summary' => 'Dùng ngoại động từ với tân ngữ để nói rõ hành động tác động đến điều gì.',
                'formula' => 'S + transitive verb + object',
                'explanation' => 'Achieve là ngoại động từ: achieve a goal, achieve success. Không thêm giới từ to trước tân ngữ của achieve. Không phải mọi động từ đều là ngoại động từ; hãy đọc ví dụ và ghi chú của từng mục từ.',
                'example' => ['We can achieve our goals with regular practice.', 'Chúng ta có thể đạt được mục tiêu bằng việc luyện tập đều đặn.'],
                'tags' => [], 'words' => ['achieve'],
            ],
            'countable-uncountable-nouns' => [
                'title' => 'Danh từ đếm được và không đếm được · Nouns',
                'summary' => 'Chọn mạo từ, dạng số nhiều và lượng từ phù hợp với danh từ.',
                'formula' => 'a / an + singular countable noun\nmany / a few + plural countable noun\nmuch / a little + uncountable noun',
                'explanation' => 'Danh từ đếm được có dạng số ít và số nhiều. Danh từ không đếm được thường không dùng a / an hoặc thêm -s. Một danh từ có thể đổi cách dùng theo nghĩa, vì vậy cần xem ngữ cảnh.',
                'example' => ['She has a book and some useful information.', 'Cô ấy có một cuốn sách và một số thông tin hữu ích.'],
                'tags' => ['pos-noun'], 'words' => ['book', 'information', 'advantage'],
            ],
            'adjective-comparison' => [
                'title' => 'So sánh tính từ · Comparatives and Superlatives',
                'summary' => 'Dùng tính từ để so sánh hai đối tượng hoặc diễn tả mức độ cao nhất.',
                'formula' => 'short adjective + -er + than\nmore + long adjective + than\nthe + -est / most + adjective',
                'explanation' => 'Tính từ ngắn thường thêm -er / -est; tính từ dài thường dùng more / most. Beautiful dùng more beautiful và most beautiful. Một số tính từ bất quy tắc như good → better → best. Chỉ dùng cấu trúc so sánh khi nghĩa của tính từ cho phép diễn tả mức độ.',
                'example' => ['This garden is more beautiful than that one.', 'Khu vườn này đẹp hơn khu vườn kia.'],
                'tags' => ['pos-adjective'], 'words' => ['beautiful'],
            ],
            'adverb-position' => [
                'title' => 'Vị trí trạng từ · Adverb Position',
                'summary' => 'Đặt trạng từ đúng vị trí để diễn tả tần suất hoặc cách thức của hành động.',
                'formula' => 'S + frequency adverb + main verb\nS + be + frequency adverb\nverb + object + manner adverb',
                'explanation' => 'Trạng từ tần suất như often thường đứng trước động từ chính và sau be. Trạng từ cách thức như carefully thường đứng sau động từ hoặc sau tân ngữ. Vị trí có thể thay đổi để nhấn mạnh.',
                'example' => ['She often studies English and checks her answers carefully.', 'Cô ấy thường học tiếng Anh và kiểm tra đáp án cẩn thận.'],
                'tags' => ['pos-adverb'], 'words' => ['often', 'carefully'],
            ],
            'phrasal-verbs' => [
                'title' => 'Cách dùng cụm động từ · Phrasal Verbs',
                'summary' => 'Hiểu động từ kết hợp với tiểu từ và vị trí của tân ngữ.',
                'formula' => 'verb + particle\nseparable verb + pronoun + particle',
                'explanation' => 'Cụm động từ có thể mang nghĩa khác với động từ gốc. Với cụm có thể tách như turn off, đại từ tân ngữ đứng giữa: turn it off. Cụm không thể tách như look after giữ tân ngữ ở sau: look after her. Không áp dụng quy tắc tách cho tất cả cụm động từ.',
                'example' => ['Please turn it off before you leave.', 'Vui lòng tắt nó trước khi bạn rời đi.'],
                'tags' => ['type-phrasal_verb'], 'words' => ['turn off', 'look after', 'make up'],
            ],
            'look-forward-to-gerund' => [
                'title' => 'Look forward to + V-ing',
                'summary' => 'Sau look forward to, dùng danh từ hoặc động từ thêm -ing.',
                'formula' => 'look forward to + noun / V-ing',
                'explanation' => 'To trong look forward to là giới từ, vì vậy động từ đứng sau dùng dạng V-ing. Không dùng look forward to meet. Có thể dùng với danh từ: look forward to the holiday.',
                'example' => ['I look forward to meeting you.', 'Tôi mong được gặp bạn.'],
                'tags' => [], 'words' => ['look forward to'],
            ],
            'although-clauses' => [
                'title' => 'Mệnh đề nhượng bộ · Although / Even though',
                'summary' => 'Nối hai ý tương phản bằng một mệnh đề có chủ ngữ và động từ.',
                'formula' => 'Although / Even though + S + V, S + V',
                'explanation' => 'Although và even though đứng trước mệnh đề. Không dùng thêm but để nối mệnh đề chính trong cùng cấu trúc. Despite và in spite of dùng danh từ hoặc V-ing, nên không thay trực tiếp cho although trước một mệnh đề.',
                'example' => ['Although it was difficult, she achieved her goal.', 'Mặc dù khó khăn, cô ấy đã đạt được mục tiêu.'],
                'tags' => [], 'words' => ['although', 'even though'],
            ],
            'depend-on' => [
                'title' => 'Depend on + danh từ / mệnh đề',
                'summary' => 'Dùng depend on để diễn tả sự phụ thuộc hoặc điều kiện quyết định kết quả.',
                'formula' => 'depend on + noun / pronoun\ndepend on + whether / what / how + clause',
                'explanation' => 'Depend thường đi với on (hoặc upon trong văn phong trang trọng). On là giới từ, theo sau bởi danh từ, đại từ hoặc mệnh đề phù hợp. Không dùng depend of.',
                'example' => ['Success depends on how regularly you practice.', 'Thành công phụ thuộc vào mức độ luyện tập đều đặn của bạn.'],
                'tags' => [], 'words' => ['depend', 'depend on'],
            ],
        ];
    }
}
