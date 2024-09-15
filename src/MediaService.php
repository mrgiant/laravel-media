<?php

namespace App\Http\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

//use Intervention\Image\Drivers\Gd\Driver;

class MediaService
{
    protected $mediaModelClass;

    protected $mediaCollection;

    protected $permissions = [];

    protected $maxSize;
    protected $driverName;

    protected $modelDownloadRoute;
    protected $modelColumnName;
    protected $acceptsFileTypes;

    protected $diskName;

    public function __construct() {}

    public function toMediaCollection($mediaCollection)
    {
        $this->mediaCollection = $mediaCollection;

        return $this;
    }




    public function setModelColumnName($modelColumnName)
    {
        $this->modelColumnName = $modelColumnName;

        return $this;
    }

    public function useDisk($diskName)
    {
        $this->diskName = $diskName;

        return $this;
    }

    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function acceptsFileTypes($acceptsFileTypes)
    {
        $this->acceptsFileTypes = $acceptsFileTypes;

        return $this;
    }

    public function toMediaModelClass($mediaModelClass)
    {
        $this->mediaModelClass = $mediaModelClass;

        return $this;
    }

    public function setMaxSize($maxSize)
    {
        $this->maxSize = $maxSize;

        return $this;
    }


    public function setDriverName($driverName)
    {
        $this->driverName = $driverName;

        return $this;
    }




    // to set model_media_download_route

    public function setModelDownloadRoute($modelDownloadRoute)
    {
        $this->modelDownloadRoute = $modelDownloadRoute;

        return $this;
    }

    public function storeMedia(Request $request)
    {
        abort_if(collect($this->permissions)->contains(fn($perm) => Gate::denies($perm)), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $fileValidationRules = [
            $this->modelColumnName => 'required|file|max:' . $this->maxSize,
        ];

        switch ($this->acceptsFileTypes) {
            case 'image':
                $fileValidationRules[$this->modelColumnName] .= '|mimetypes:image/jpeg,image/png,image/bmp';
                $mimeTypesMessage = 'Please upload a valid image file (JPEG, PNG, BMP).';
                break;
            case 'video':
                $fileValidationRules[$this->modelColumnName] .= '|mimetypes:video/x-flv,video/mp4,application/x-mpegURL,video/MP2T,video/3gpp,video/quicktime,video/x-msvideo,video/x-ms-wmv,video/x-ms-asf,video/avi';
                $mimeTypesMessage = 'Please upload a valid video file (FLV, MP4, MPEGURL, MP2T, 3GPP, QuickTime, AVI, WMV, ASF).';
                break;
            case 'audio':
                $fileValidationRules[$this->modelColumnName] .= '|mimetypes:audio/mpeg,audio/mp4,audio/x-aac,audio/aac,audio/ogg,audio/x-wav,audio/x-ms-wma,audio/wave,audio/x-flac,audio/x-m4a';
                $mimeTypesMessage = 'Please upload a valid audio file (MP3, MP4, AAC, OGG, WAV, WMA, FLAC, M4A).';
                break;
            case 'document':
                $fileValidationRules[$this->modelColumnName] .= '|mimetypes:application/pdf,application/vnd.ms-excel,application/msword,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,application/vnd.openxmlformats-officedocument.presentationml.presentation';
                $mimeTypesMessage = 'Please upload a valid document file (PDF, Excel, Word, Plain Text, PowerPoint).';
                break;
            default:
                $fileValidationRules[$this->modelColumnName] .= '|mimetypes:image/jpeg,image/png,image/bmp,application/pdf,application/vnd.ms-excel,application/msword,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip,video/x-flv,video/mp4,application/x-mpegURL,video/MP2T,video/3gpp,video/quicktime,video/x-msvideo,video/x-ms-wmv,video/x-ms-asf,video/avi,text/plain,application/vnd.openxmlformats-officedocument.presentationml.presentation,image/x-icon,audio/x-wav';
                $mimeTypesMessage = '
             Please ensure that the file you upload is one of the following types:
            Image: JPEG, PNG, BMP
            Document: PDF, Excel (XLS, XLSX), Word (DOC, DOCX)
            Archive: ZIP
            Video: FLV, MP4, MPEGURL, MP2T, 3GPP, QuickTime, AVI, WMV, ASF
            Text: Plain text
            Presentation: PowerPoint (PPTX)
            Icon: ICO
            Audio: WAV
            ';
        }

        $validator = Validator::make($request->all(), $fileValidationRules, [
            $this->modelColumnName.'.mimetypes' => $mimeTypesMessage,
            $this->modelColumnName.'.max' => 'The file size must not exceed ' . $this->maxSize . ' kilobytes.',
        ]);

        if ($validator->fails()) {

            return ['errors' => $validator->errors()];

        }

        $file = $request->file($this->modelColumnName);

        $FileOriginalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $file_name = Str::random(8) . '_' . Str::random(8) . '_' . trim($file->getClientOriginalName());

        $disk = Storage::disk($this->diskName);

        $path = $disk->putFileAs($this->mediaCollection, $file, $file_name);

        // Get the file size (in bytes)
        $fileSize = $disk->size($path);

        // Get the full file path
        $filePath = $disk->path($path);
        $directoryPath = ! empty($this->mediaCollection) ? $this->mediaCollection : '';

        // Get the MIME type
        $mimeType = $disk->mimeType($path);
        $extension = $file->extension();

        $fileUrl = $disk->url($path);

        $fileNameWithoutExtension = pathinfo($filePath, PATHINFO_FILENAME);

        $model = new $this->mediaModelClass;
        $model->size = $fileSize;
        $model->mime_type = $mimeType;
        $model->file_path = $directoryPath;
        $model->disk = $this->diskName;
        $model->collection_name = $this->mediaCollection;
        $model->name = $FileOriginalName;
        $model->file_name = $file_name;
        $model->extension = $extension;

        $model->save();

        $conversions = $model->conversions ?? [];



        if (Str::contains($mimeType, 'image')) {
            $generated_conversions = $this->processImage($filePath, $fileNameWithoutExtension, $directoryPath, $extension, $conversions);
        } else {
            $generated_conversions = [];
        }



        $model->generated_conversions = $generated_conversions;

        $model->save();

        if ($this->modelDownloadRoute) {
            $file_path = Crypt::encrypt($model->file_path);
            $file_url = $this->modelDownloadRoute . '/' . $file_path;
        } else {
            $file_url = $fileUrl;
        }


        return ['id' => $model->id, 'url' => $file_url, 'file_name' => $model->file_name, 'size' => $model->size];


    }

    public function destroyMedia($media_id)
    {
        abort_if(collect($this->permissions)->contains(fn($perm) => Gate::denies($perm)), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $mediaToDelete = $this->mediaModelClass::find($media_id);
        if ($mediaToDelete) {

            $mediaToDelete->delete();
        }

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function destroyModelMedia($modelId, $modelColumnName)
    {
        abort_if(collect($this->permissions)->contains(fn($perm) => Gate::denies($perm)), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $mediaToDelete = $this->mediaModelClass::where($modelColumnName, $modelId)->get();
        if ($mediaToDelete) {
            foreach ($mediaToDelete as $media) {
                $media->delete();
            }
        }

        return true;
    }

    public function updatModelMedia($media, $modelId, $ModelcolumnName)
    {
        abort_if(collect($this->permissions)->contains(fn($perm) => Gate::denies($perm)), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $mediaIds = array_column($media, 'id');



        $this->mediaModelClass::whereIn('id', $mediaIds)
            ->update([$ModelcolumnName => $modelId]);

        $mediaToDelete = $this->mediaModelClass::whereNotIn('id', $mediaIds)->where($ModelcolumnName, $modelId)->get();
        if ($mediaToDelete) {
            foreach ($mediaToDelete as $media_value) {
                $media_value->delete();
            }
        }

        return true;
    }

    public function storeModelMedia($media, $modelId, $ModelcolumnName)
    {
        abort_if(collect($this->permissions)->contains(fn($perm) => Gate::denies($perm)), Response::HTTP_FORBIDDEN, '403 Forbidden');



        $mediaIds = array_column($media, 'id');

        $this->mediaModelClass::whereIn('id', $mediaIds)
            ->update([$ModelcolumnName => $modelId]);

        return true;
    }

    public function downloadMedia($media_id)
    {

        abort_if(collect($this->permissions)->contains(fn($perm) => Gate::denies($perm)), Response::HTTP_FORBIDDEN, '403 Forbidden');

        try {
            $media_id = Crypt::decrypt($media_id);
        } catch (DecryptException $e) {

            return response()->json(['error' => 'File not found'], 404);
        }

        $media = $this->mediaModelClass::find($media_id);

        if (! $media) {

            return response()->json(['error' => 'File not found'], 404);
        }

        $file_path = $media->getPath();

        $disk = Storage::disk($media->disk);

        if ($disk->exists($file_path)) {

            return $disk->download($file_path);
        }

        return response()->json(['error' => 'File not found'], 404);
    }

    public function processImage($filePath, $fileNameWithoutExtension, $directoryPath, $extension, array $conversions = [])
    {

        $generated_conversions = [];

        $imageService = new ImageService($this->driverName, $this->diskName);

        $imageService->readImage($filePath);

        foreach ($conversions as $conversionName => $conversion) {

            $model_column_name = !empty($conversion['model_column_name']) ? $conversion['model_column_name'] : "";

            if ($model_column_name != $this->modelColumnName && $model_column_name != "") {
                continue;
            }

            $type = $conversion['type'];

            $conversionName = $conversionName;

            switch ($type) {
                case 'resize':
                    $imageService->resize($conversion['width'], $conversion['height']);

                    break;

                case 'crop':
                    $imageService->crop($conversion['width'], $conversion['height'], $conversion['x'] ?? 0, $conversion['y'] ?? 0);
                    break;
                case 'rotate':
                    $imageService->rotate($conversion['degrees']);
                    break;
                case 'filter':
                    $imageService->applyFilter($conversion['filter']);
                    break;
                case 'canvas':
                    $imageService->resizeCanvas($conversion['width'], $conversion['height'], $conversion['bgColor'] ?? 'transparent');
                    break;
                case 'flip':
                    $imageService->flip($conversion['mode']);
                    break;
                default:
                    throw new \Exception('Unsupported action type: ' . $type);
            }

            $new_file_name = $fileNameWithoutExtension . '-' . $conversionName . '.' . $extension;

            $imageService->saveImage($directoryPath . '/' . $new_file_name);

            $generated_conversions[$conversionName] = $new_file_name;
        }

        return $generated_conversions;
    }
}
