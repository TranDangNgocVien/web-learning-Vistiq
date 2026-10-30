<?php

namespace VistiqCore\Services\Knowledge;

use Illuminate\Support\Collection;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class KnowledgeTaxonomyService
{
    private ?HtmlSanitizer $sanitizer = null;

    /**
     * Get non-question learning item types.
     *
     * @return Collection<int, object>
     */
    public function types(): Collection
    {
        return DB::table('learning_item_types')
            ->where('code', '!=', 'question')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get proficiency levels.
     *
     * @return Collection<int, object>
     */
    public function levels(): Collection
    {
        return DB::table('proficiency_levels')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get parts of speech.
     *
     * @return Collection<int, object>
     */
    public function partsOfSpeech(): Collection
    {
        return DB::table('parts_of_speech')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get grammar categories.
     *
     * @return Collection<int, object>
     */
    public function grammarCategories(): Collection
    {
        return DB::table('grammar_categories')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Sanitize rich-text HTML content.
     */
    public function sanitizeHtml(string $html): string
    {
        if ($this->sanitizer === null) {
            $config = (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeMedias()
                ->allowRelativeLinks()
                ->allowMediaSchemes(['https'])
                ->allowLinkSchemes(['https', 'http', 'mailto']);

            $this->sanitizer = new HtmlSanitizer($config);
        }

        return $this->sanitizer->sanitize($html);
    }
}
