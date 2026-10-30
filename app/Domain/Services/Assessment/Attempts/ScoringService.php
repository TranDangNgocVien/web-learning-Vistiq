<?php

namespace VistiqCore\Services\Assessment\Attempts;

use VistiqCore\Services\Shared\CompactDatabase as DB;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Services\Assessment\QuestionBank\QuestionRegistry;

class ScoringService
{
    public const RESULT_VERSION = 2;

    public function __construct(private QuestionRegistry $registry) {}

    public function normalize(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }

    public function gradeObjective(array $item, array $response): array
    {
        $family = QuestionRegistry::TYPES[$item['type']];
        $units = $this->registry->units($item);
        if (in_array($family, ['writing', 'speaking'], true)) {
            return ['correct' => null, 'points' => null, 'units' => 1, 'pending' => true];
        }
        $points = 0;
        if ($family === 'choice') {
            $selected = array_values(array_unique($response['options'] ?? []));
            $correct = $item['correct_options'];
            sort($selected);
            sort($correct);
            $points = $item['type'] === 'multiple_response' ? (count($selected) <= count($correct) ? count(array_intersect($selected, $correct)) : 0) : ($selected === $correct ? 1 : 0);
        } elseif ($family === 'matching') {
            foreach ($item['metadata']['answer_map'] as $key => $expected) {
                if (($response['matching'][$key] ?? null) === $expected) {
                    $points++;
                }
            }
        } elseif (! empty($item['metadata']['blanks'])) {
            foreach ($item['metadata']['blanks'] as $key => $blank) {
                $grade = $this->gradeObjective(array_replace($item, ['accepted_answers' => $blank['accepted_answers'], 'metadata' => array_replace(array_diff_key($item['metadata'], ['blanks' => true]), array_diff_key($blank, ['accepted_answers' => true]))]), ['text' => $response['blanks'][$key] ?? '']);
                $points += $grade['points'];
            }
        } else {
            $text = $this->normalize($response['text'] ?? '');
            $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
            $numbers = count(array_filter($tokens, fn ($token) => preg_match('/^\d+(?:[.,]\d+)?$/', $token)));
            $words = count($tokens) - $numbers;
            $valid = $text !== '' && (! isset($item['metadata']['max_words']) || $words <= $item['metadata']['max_words']) && (! isset($item['metadata']['max_numbers']) || $numbers <= $item['metadata']['max_numbers']);
            $accepted = array_map(fn ($value) => $this->normalize($value), $item['accepted_answers']);
            $points = $valid && in_array($text, $accepted, true) ? 1 : 0;
        }
        $answeredUnits = match ($family) {
            'choice' => min($units, count($response['options'] ?? [])),'matching' => count(array_filter($response['matching'] ?? [], fn ($value) => filled($value))),default => ! empty($item['metadata']['blanks']) ? count(array_filter($response['blanks'] ?? [], fn ($value) => filled($value))) : (filled($response['text'] ?? null) ? 1 : 0)
        };

        return ['correct' => $points === $units, 'points' => $points, 'units' => $units, 'answered_units' => $answeredUnits, 'pending' => false];
    }

    public function calculate(ExamAttempt $attempt): array
    {
        $answers = $attempt->answers()->get()->keyBy('item_key');
        $details = ['scoring_version' => self::RESULT_VERSION, 'correct' => 0, 'wrong' => 0, 'unanswered' => 0, 'pending_tasks' => 0, 'by_skill' => [], 'bands' => [], 'estimated' => true];
        $raw = 0;
        $writing = [];
        $speaking = [];
        foreach ($attempt->snapshot['items'] as $item) {
            $answer = $answers->get($item['key']);
            $family = QuestionRegistry::TYPES[$item['type']];
            $skill = $item['skill'];
            if (in_array($family, ['writing', 'speaking'], true)) {
                if (! $answer?->grade) {
                    $details['pending_tasks']++;

                    continue;
                }
                $band = array_sum($answer->grade['criteria']) / count($answer->grade['criteria']);
                if ($family === 'writing') {
                    $writing[] = [$band, $item['weight'] ?? 1];
                } else {
                    $speaking[] = $band;
                }

                continue;
            }
            $grade = $this->gradeObjective($item, $answer?->response ?? []);
            $responded = $answer && (filled($answer->response['text'] ?? null) || ! empty($answer->response['options']) || ! empty(array_filter($answer->response['matching'] ?? [])) || ! empty(array_filter($answer->response['blanks'] ?? [])));
            $details['correct'] += $grade['points'];
            $raw += $grade['points'];
            $details['wrong'] += max(0, $grade['answered_units'] - $grade['points']);
            $details['unanswered'] += $grade['units'] - $grade['answered_units'];
            $details['by_skill'][$skill] ??= ['raw' => 0, 'total' => 0];
            $details['by_skill'][$skill]['raw'] += $grade['points'];
            $details['by_skill'][$skill]['total'] += $grade['units'];
            if ($answer) {
                $answer->update(['is_correct' => $grade['correct'], 'awarded_points' => $grade['points']]);
            }
        }
        $tables = $attempt->snapshot['scoring']['configuration']['tables'] ?? [];
        foreach ($details['by_skill'] as $skill => $score) {
            if (isset($tables[$skill][$score['raw']])) {
                $details['bands'][$skill] = (float) $tables[$skill][$score['raw']];
            } elseif (($attempt->snapshot['exam_type'] ?? null) === 'ielts' && $skill === 'listening' && $score['total'] === 40) {
                $details['bands']['listening'] = $this->estimatedIeltsListeningBand($score['raw'], $details['unanswered']);
            }
        }
        if ($writing !== [] && count($writing) === collect($attempt->snapshot['items'])->where('skill', 'writing')->count()) {
            $details['bands']['writing'] = round((array_sum(array_map(fn ($pair) => $pair[0] * $pair[1], $writing)) / array_sum(array_column($writing, 1))) * 2) / 2;
        }
        if ($speaking !== [] && count($speaking) === collect($attempt->snapshot['items'])->where('skill', 'speaking')->count()) {
            $details['bands']['speaking'] = round((array_sum($speaking) / count($speaking)) * 2) / 2;
        }
        $scaled = null;
        if ($attempt->snapshot['exam_type'] === 'toeic' && isset($details['bands']['listening'],$details['bands']['reading'])) {
            $scaled = $details['bands']['listening'] + $details['bands']['reading'];
        }
        if ($attempt->snapshot['exam_type'] === 'ielts' && count($details['bands']) === 4 && $details['pending_tasks'] === 0) {
            $scaled = round(array_sum($details['bands']) / 4 * 2) / 2;
        }
        if ($attempt->snapshot['exam_type'] === 'thpt' && isset($details['bands']['thpt'])) {
            $scaled = $details['bands']['thpt'];
        }
        if ($scaled === null && $details['pending_tasks'] === 0 && $attempt->snapshot['mode'] !== 'full') {
            $total = array_sum(array_column($details['by_skill'], 'total'));
            if (($attempt->snapshot['exam_type'] ?? null) === 'ielts') {
                // Mini IELTS exams can contain any subset of the four skills.
                // Once every task is graded, report the band for the skills present.
                $scaled = $details['bands'] !== []
                    ? round(array_sum($details['bands']) / count($details['bands']) * 2) / 2
                    : null;
            } else {
                $scaled = $total > 0 ? round($raw / $total * 100, 2) : (count($details['bands']) > 0 ? round(array_sum($details['bands']) / count($details['bands']) * 2) / 2 : null);
            }
        }
        $details['duration_seconds'] = max(0, ($attempt->submitted_at ?? now())->timestamp - $attempt->started_at->timestamp - $attempt->paused_seconds);
        $status = $details['pending_tasks'] > 0 ? 'pending' : 'completed';
        DB::table('attempt_results')->updateOrInsert(['exam_attempt_id' => $attempt->id], ['raw_score' => $raw, 'scaled_score' => $scaled, 'details' => json_encode($details, JSON_UNESCAPED_UNICODE), 'grading_status' => $status, 'calculated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $attempt->update(['status' => $status === 'pending' ? 'pending_grading' : 'completed']);

        return [...$details, 'raw_score' => $raw, 'scaled_score' => $scaled, 'grading_status' => $status];
    }

    private function estimatedIeltsListeningBand(int $rawScore, int $unanswered): float
    {
        foreach ([
            [39, 40, 9.0], [37, 38, 8.5], [35, 36, 8.0], [32, 34, 7.5],
            [30, 31, 7.0], [26, 29, 6.5], [23, 25, 6.0], [18, 22, 5.5],
            [16, 17, 5.0], [13, 15, 4.5], [11, 12, 4.0], [8, 10, 3.5],
            [6, 7, 3.0], [4, 5, 2.5], [2, 3, 2.0], [1, 1, 1.0],
        ] as [$minimum, $maximum, $band]) {
            if ($rawScore >= $minimum && $rawScore <= $maximum) {
                return $band;
            }
        }

        return $unanswered === 40 ? 0.0 : 1.0;
    }
}
