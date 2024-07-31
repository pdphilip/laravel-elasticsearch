<?php

namespace PDPhilip\Elasticsearch\Eloquent;

trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /**
     * @inheritdoc
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }
    /**
     * TODO: Update without refresh to speed up mass deleting
     * Could have unintended consequences
     * Requires more testing
     */


//    protected function runSoftDelete()
//    {
//        $query = $this->setKeysForSaveQuery($this->newModelQuery());
//        $time = $this->freshTimestamp();
//        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];
//        $this->{$this->getDeletedAtColumn()} = $time;
//        if ($this->usesTimestamps() && !is_null($this->getUpdatedAtColumn())) {
//            $this->{$this->getUpdatedAtColumn()} = $time;
//            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
//        }
//        $query->updateWithoutRefresh($columns);
//        $this->syncOriginalAttributes(array_keys($columns));
//        $this->fireModelEvent('trashed', false);
//    }

}
