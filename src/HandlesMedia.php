<?php

namespace Mrgiant\LaravelMedia;

use Illuminate\Support\Facades\Storage;

trait HandlesMedia
{
    // Ensure generated_conversions is always cast to an array
    public function initializeHandlesMedia()
    {
        if (! array_key_exists('generated_conversions', $this->casts)) {
            $this->casts['generated_conversions'] = 'array';
        }
    }

    public function deleteMedia()
    {
        $generated_conversions = $this->generated_conversions ?? [];

        $disk = Storage::disk($this->disk);

        // Delete generated conversions
        foreach ($generated_conversions as $conversionName => $fileName) {
            $disk->delete($this->collection_name.'/'.$fileName);
        }

        // Delete the original file
        $disk->delete($this->collection_name.'/'.$this->file_name);
    }

    public function getUrl($conversionName = '')
    {
        $collection_name = ! empty($this->collection_name) ? $this->collection_name.'/' : '';

        if ($conversionName && isset($this->generated_conversions[$conversionName])) {
            return Storage::disk($this->disk)->url($collection_name.$this->generated_conversions[$conversionName]);
        }

        return Storage::disk($this->disk)->url($collection_name.$this->file_name);
    }

    public function getPath()
    {

        $collection_name = ! empty($this->collection_name) ? $this->collection_name.'/' : '';

        $path = $collection_name.$this->file_name;

        return $path;

    }

    // Override the delete method of the model if needed
    public function delete()
    {
        $this->deleteMedia();

        return parent::delete();
    }
}
