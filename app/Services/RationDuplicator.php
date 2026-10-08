<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Aliment;
use App\Models\Ration;
use Illuminate\Support\Facades\DB;

class RationDuplicator
{
    public function duplicate(Ration $ration): Ration
    {
        return DB::transaction(function () use ($ration): Ration {
            $ration->load(['planRationnement', 'rationAliments.aliment', 'melanges.melangeAliments.aliment']);

            $copy = $ration->replicate()->unsetRelations();
            $copy->save();

            foreach ($ration->rationAliments as $rationAliment) {
                $aliment = $this->duplicateAliment($rationAliment->aliment, $ration->planRationnement->user_id);
                $component = $rationAliment->replicate()->unsetRelations();
                $component->aliment_id = $aliment->id;
                $copy->rationAliments()->save($component);
            }

            foreach ($ration->melanges as $melange) {
                $melangeCopy = $melange->replicate()->unsetRelations();
                $copy->melanges()->save($melangeCopy);

                foreach ($melange->melangeAliments as $melangeAliment) {
                    $aliment = $this->duplicateAliment($melangeAliment->aliment, $ration->planRationnement->user_id);
                    $ingredient = $melangeAliment->replicate()->unsetRelations();
                    $ingredient->aliment_id = $aliment->id;
                    $melangeCopy->melangeAliments()->save($ingredient);
                }
            }

            return $copy;
        });
    }

    private function duplicateAliment(Aliment $aliment, int $userId): Aliment
    {
        $copy = $aliment->replicate()->unsetRelations();
        $copy->usage_aliment = 2;
        $copy->user_id = $userId;
        $copy->save();

        return $copy;
    }
}
