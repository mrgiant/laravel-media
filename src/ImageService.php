<?php

namespace Mrgiant\LaravelMedia;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class ImageService
{
    protected $manager;

    protected $diskName;

    protected $image;

    public function __construct($driverName = 'gd', $diskName = 'public')
    {
        // Determine which driver to use
        switch (strtolower($driverName)) {
            case 'imagick':
                $this->manager = new ImageManager(new ImagickDriver);
                break;
            case 'gd':
            default:
                $this->manager = new ImageManager(new GdDriver);
                break;
        }

        $this->diskName = $diskName;
    }

    public function readImage($path)
    {

        $this->image = $this->manager->read($path);

    }


    

    public function resizeDown($width, $height)
    {
        if(empty($width))
        {

            return $this->image->resizeDown(height: $height);

        }


        if(empty($height))
        {

            return $this->image->resizeDown(width: $width);

        }

        return $this->image->resizeDown($width, $height);
    }

    public function resize($width, $height)
    {

        if(empty($width))
        {

            return $this->image->resize(height: $height);

        }


        if(empty($height))
        {

            return $this->image->resize(width: $width);

        }

        return $this->image->resize($width, $height);
    }



    public function scale($width, $height)
    {
        if(empty($width))
        {

            return $this->image->scale(height: $height);

        }


        if(empty($height))
        {

            return $this->image->scale(width: $width);

        }

        return $this->image->scale($width, $height);
    }




    public function scaleDown($width, $height)
    {
        if(empty($width))
        {

            return $this->image->scaleDown(height: $height);

        }


        if(empty($height))
        {

            return $this->image->scaleDown(width: $width);

        }

        return $this->image->scaleDown($width, $height);
    }







    public function crop($width, $height, $x = 0, $y = 0)
    {
        return $this->image->crop($width, $height, $x, $y);
    }

    public function rotate($degrees)
    {
        return $this->image->rotate($degrees);
    }

    public function applyFilter($filter)
    {
        switch ($filter) {
            case 'grayscale':
                return $this->image->greyscale();
            case 'blur':
                return $this->image->blur(15);
            case 'invert':
                return $this->image->invert();
            default:
                throw new \Exception('Unsupported filter: '.$filter);
        }
    }

    public function resizeCanvas($width, $height, $bgColor = 'transparent')
    {
        return $this->image->resizeCanvas($width, $height, 'center', false, $bgColor);
    }

    public function flip($mode)
    {
        return ($mode === 'h') ? $this->image->flip('h') : $this->image->flip('v');
    }

    public function saveImage($image_type,$outputPath)
    {

        if(empty($image_type))
        {
            
            Storage::disk($this->diskName)->put($outputPath, $this->image->encode());
        }
        else
        {

            Storage::disk($this->diskName)->put($outputPath, $this->image->encode());
            Storage::disk($this->diskName)->put($outputPath, $this->image->encodeByMediaType('image/'.$image_type));

        }

       



       //  Storage::disk('public')->put('pwa_images/webp/logo.webp', $image_resize->encode(new WebpEncoder(quality: 100)));


        return true;
    }

    public function saveEncode($outputPath)
    {
        Storage::disk($this->diskName)->put($outputPath, $this->image->encode());

        return true;
    }
}
