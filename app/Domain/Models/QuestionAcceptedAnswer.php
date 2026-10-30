<?php

namespace VistiqCore\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_bank_item_id', 'answer_text', 'normalized_answer', 'sort_order'])]
class QuestionAcceptedAnswer extends Model
{
    /** @return BelongsTo<QuestionBankItem, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuestionBankItem::class, 'question_bank_item_id');
    }
}
