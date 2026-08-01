<?php

namespace App\Actions\Administration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateReferenceDataCode
{
    /**
     * @param  class-string<Model>  $model
     */
    public function handle(string $model, string $name, ?int $ignoredRecordId = null): string
    {
        $baseCode = Str::of($name)->ascii()->slug()->limit(100, '')->toString();

        if ($baseCode === '') {
            $baseCode = 'item';
        }

        $code = $baseCode;
        $suffix = 2;

        while ($this->codeExists($model, $code, $ignoredRecordId)) {
            $suffixValue = '-'.$suffix;
            $code = Str::substr($baseCode, 0, 100 - Str::length($suffixValue)).$suffixValue;
            $suffix++;
        }

        return $code;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function codeExists(string $model, string $code, ?int $ignoredRecordId): bool
    {
        $modelInstance = new $model;
        $query = DB::table($modelInstance->getTable())->where('code', $code);

        if ($ignoredRecordId !== null) {
            $query->where($modelInstance->getKeyName(), '!=', $ignoredRecordId);
        }

        return $query->exists();
    }
}
