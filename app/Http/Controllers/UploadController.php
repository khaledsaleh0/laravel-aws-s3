<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use Aws\S3\S3Client;
use Storage;

class UploadController extends Controller
{
    
    /* This function opens zipped file of issue folder 
       unzips it locally then
       calls S3Upload function to upload it to Amazon S3 
       then delete the local copy */
    public function store(Request $request)
    {
        $zip = Zip::open($request->file('pdf'));
        $year = Carbon::create($request['date'])->year;
        $month = Carbon::create($request['date'])->month;
        $pdfPath = 'pdf/' . $year . '/' . $month . '/' . Carbon::create($request['date'])->format('Y-m-d') . '_' . rand(100, 999);
        $dist = storage_path().'/app/public/issues/' . $pdfPath ;
        $index = file_get_contents($dist.'/index.html');
        $request['path']  =  $pdfPath;
        $temp =  storage_path() . '/app/public/issues/temp/indexx.html';

        $zip->extract($dist);

        if (!is_dir(storage_path('app/public/pdf/'))) {
                \File::makeDirectory(storage_path('app/public/pdf/'), 0777, true, true);
            }
        if (!is_dir(storage_path('app/public/pdf/' . $year))) {
                \File::makeDirectory(storage_path('app/public/pdf/' . $year), 0777, true, true);
            }
        if (!is_dir(storage_path('app/public/pdf/' . $year . '/' . $month))) {
            \File::makeDirectory(storage_path('app/public/pdf/' . $year . '/' . $month), 0777, true, true);
        }
        if (!is_dir(storage_path('app/public/issues/temp'))) {
            \File::makeDirectory(storage_path('app/public/issues/temp'), 0777, true, true);
        }
        
        file_put_contents($temp,$index);
        unlink($dist.'/index.html');

        $this->s3Upload($dist, $pdfPath, $temp);

        return redirect()->back()->with('success', 'uploaded successfully');
    }

    public function s3Client(Type $var = null)
    {
       return S3Client::factory(array(
            'endpoint' => env('AWS_ENDPOINT'),
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => 'latest',
        ));
    }

    public function s3Upload($dist, $source,$temp)
    { 
        // dd(env('AWS_DEFAULT_REGION'));
        $client = $this->s3Client();
        $client->uploadDirectory($dist, env('AWS_BUCKET'),$source, array(
            'concurrency' => 20,
            'before' => function (\Aws\Command $command) {
            $command['ACL'] = strpos($command['Key'], 'CONFIDENTIAL') === false
                ? 'public-read'
                : 'private';
        }
        ));

        Storage::disk('s3')->put($source.'/index.html',file_get_contents($temp),'private');
        Storage::delete($temp);
        Storage::deleteDirectory($source);
    }
   
}
