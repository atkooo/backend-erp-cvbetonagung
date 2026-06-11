<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait GeneratesDocumentNumber
{
    /**
     * Boot the trait for the model.
     */
    protected static function bootGeneratesDocumentNumber()
    {
        static::creating(function ($model) {
            $field = method_exists($model, 'documentNumberField') 
                ? $model->documentNumberField() 
                : 'document_number';

            if (empty($model->{$field}) || $model->{$field} === 'AUTO GENERATED') {
                $model->{$field} = static::generateDocumentNumber($model);
            }
        });
    }

    /**
     * Generate the next document number.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return string
     */
    public static function generateDocumentNumber($model): string
    {
        $prefix = method_exists($model, 'documentNumberPrefix') 
            ? $model->documentNumberPrefix() 
            : 'DOC';

        $field = method_exists($model, 'documentNumberField') 
            ? $model->documentNumberField() 
            : 'document_number';

        $datePrefix = date('Ym'); // e.g., 202606
        $searchPrefix = "{$prefix}-{$datePrefix}-";

        // Find the latest document number matching the prefix
        $latestDoc = DB::table($model->getTable())
            ->where($field, 'LIKE', "{$searchPrefix}%")
            ->orderBy($field, 'desc')
            ->first();

        if (! $latestDoc) {
            return "{$searchPrefix}0001";
        }

        $latestNumber = $latestDoc->{$field};
        
        // Extract the sequence part and increment
        $sequence = (int) substr($latestNumber, strlen($searchPrefix));
        $nextSequence = str_pad((string)($sequence + 1), 4, '0', STR_PAD_LEFT);

        return "{$searchPrefix}{$nextSequence}";
    }
}
