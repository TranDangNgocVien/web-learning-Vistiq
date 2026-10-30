<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use VistiqCore\Models\ExamAttempt;
use VistiqCore\Models\ExamRevision;
use VistiqCore\Models\ExamTemplate;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app('vistiq.surface') === 'learner'
            ? $this->learnerData($this->resource)
            : $this->resource;
    }

    private function learnerData(mixed $value): mixed
    {
        if ($value instanceof Model) {
            $data = $value->toArray();
            if ($value instanceof ExamTemplate) {
                unset($data['draft_payload'], $data['review_note']);
            } elseif ($value instanceof ExamAttempt) {
                unset($data['snapshot'], $data['client_session']);
            } elseif ($value instanceof ExamRevision) {
                unset($data['payload']);
            }
            foreach ($value->getRelations() as $name => $relation) {
                $key = $value::$snakeAttributes ? Str::snake($name) : $name;
                if (array_key_exists($key, $data)) {
                    $data[$key] = $this->learnerData($relation);
                }
            }

            return $data;
        }
        if ($value instanceof AbstractPaginator) {
            $paginator = clone $value;
            $paginator->setCollection($value->getCollection()->map(fn (mixed $item): mixed => $this->learnerData($item)));

            return $paginator->toArray();
        }
        if ($value instanceof Collection) {
            return $value->map(fn (mixed $item): mixed => $this->learnerData($item))->all();
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->learnerData($item), $value);
        }

        return $value;
    }
}
