<?php

namespace VistiqCore\Services\Knowledge;

class KnowledgeInput
{
    public static function rules(): array
    {
        return [
            'type' => 'required|in:vocabulary,collocation,phrasal_verb,idiom,grammar',
            'english' => 'required|string|max:255',
            'meaning_vi' => 'required_unless:type,grammar|nullable|string|max:10000',
            'definition_en' => 'nullable|string|max:10000',
            'ipa' => 'nullable|string|max:100',
            'grammatical_pattern' => 'nullable|string|max:255',
            'dictionary_entry_ids' => 'nullable|array|max:30',
            'dictionary_entry_ids.*' => 'integer|exists:dictionary_entries,id',
            'dictionary_entry_ids_present' => 'sometimes|boolean',
            'base_dictionary_entry_id' => 'required_if:type,phrasal_verb|nullable|integer|exists:dictionary_entries,id',
            'particle' => 'required_if:type,phrasal_verb|nullable|string|max:80',
            'register' => 'nullable|in:formal,informal,neutral',
            'usage_note' => 'nullable|string|max:10000',
            'part_of_speech_id' => 'nullable|integer|exists:parts_of_speech,id',
            'proficiency_level_id' => 'nullable|integer|exists:proficiency_levels,id',
            'tags' => 'nullable|string|max:500',
            'content' => 'nullable|string|max:100000',
            'summary' => 'nullable|string|max:10000',
            'grammar_category_id' => 'required_if:type,grammar|nullable|integer|exists:grammar_categories,id',
            'examples' => 'nullable|array|max:50',
            'examples_present' => 'sometimes|boolean',
            'examples.*.english' => 'required|string|max:2000',
            'examples.*.vietnamese' => 'nullable|string|max:2000',
            'relations' => 'nullable|array|max:50',
            'relations_present' => 'sometimes|boolean',
            'relations.*.id' => 'required|integer|exists:learning_items,id',
            'relations.*.type' => 'required|in:synonym,antonym,related,derived',
            'relevance' => 'nullable|string|max:10000',
            'grammar_rules' => 'nullable|array|max:50',
            'grammar_rules.*.title' => 'required|string|max:180',
            'grammar_rules.*.formula' => 'nullable|string|max:1000',
            'grammar_rules.*.explanation' => 'nullable|string|max:10000',
            'revision_counter' => 'nullable|integer|min:0',
        ];
    }
}
