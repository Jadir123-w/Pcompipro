<?php

namespace Modules\Sale\Services;

use Illuminate\Support\Facades\Storage;

class SaleOpportunityFileService
{

    public function getFile($filename)
    {
        $path = 'sale_opportunity_files' . DIRECTORY_SEPARATOR . $filename;
    
        if (!Storage::disk('tenant')->exists($path)) {
            return null;
        }
    
        $file = Storage::disk('tenant')->get($path);
        $temp = tempnam(sys_get_temp_dir(), 'tmp_sale_opportunity_files');
        file_put_contents($temp, $file);
        $mime = mime_content_type($temp);
        $data = file_get_contents($temp);
    
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
    

    public function isImage($filename)
    {

        $image_types = [
            'jpeg',
            'jpg',
            'png',
            'svg',
            'bmp',
            'tiff',
        ];

        $array_filename = explode('.', $filename);
         
        return (in_array($array_filename[1], $image_types)) ?? false;
    }

}