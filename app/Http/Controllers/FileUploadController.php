<?php

namespace App\Http\Controllers;

use App\Mail\BuildMail;
use Illuminate\Http\Request;
use App\Models\FileUpload;
use App\Http\Controllers\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;



class FileUploadController extends Controller
{
     /**
     * Scan dependency URL
     */
    public $scandep = '';

    /**
     * Scan document URL
     */
    public $scandoc = '';

    /**
     * document status url
     */
    public $status = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->scandep = config('services.scandep') ?? "https://debricked.com/api/1.0/open/uploads/dependencies/files";
        $this->scandoc = config('services.scandoc') ?? "https://debricked.com/api/1.0/open/finishes/dependencies/files/uploads";
        $this->status = config('services.status') ?? "https://debricked.com/api/1.0/open/ci/upload/status";
    }
    /**
     * Display a listing of the resource.
     * 
     *  @param array $data
     * 
     * @return \Illuminate\Http\Response
     */ 
    public function index()
    { 
        $fileUplaods = FileUpload::get();
        $data = ['fileUploads' => $fileUplaods];
       // Log::info(Session::get('message'));
        return view('file-upload', $data);
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @param Request $request
     * 
     * @return \Illuminate\Http\Response
     */
    public function multipleUpload(Request $request)
    {
        $fileUplaods = FileUpload::get();
        $return = [];
    
        if ($request->hasfile('fileuploads')) {
            $token =  config('services.access_token');
            $refreshToken = config('services.refresh_token');
            $scandep = config('services.scandep');
            $scandoc = config('services.scandoc');
            //"Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzUxMiJ9.eyJpYXQiOjE3Mjg5MzM0ODAsImV4cCI6MTcyODkzNzA4MCwicm9sZXMiOlsiUk9MRV9VU0VSIiwiUk9MRV9DT01QQU5ZX0FETUlOIiwiUk9MRV9SRVBPU0lUT1JZX0FETUlOIl0sImVtYWlsIjoiamFpbnByYWRueWEyN0BnbWFpbC5jb20ifQ.baK25aV-7vJ4-CyKQOANiFcSzh7GTd0vNmQjx1aoGbCuTO9rGsxVm9q105T7NEBAksKvnR-VT0Z4lPTy84J9oggEZQ05crv1tx5-gvDJ9kdYy9b-WYauYdym48FMjNJ8hAl80sLUpOiipyJX5jD94trYrkhaZ3syMPPz8iaZgWp7YTjlURs98z5pKNRaSinH9hg-9gN38_Oyn24g8a84HmNRgOq8fBW3c_GmyclFh2rtI3ScuKYwVG_uptsTGKNaET7BdfbDzn_8ygM2Iqon_7mE_y3H8vR8afK_CnmUwXIi6shTldHZMST9hbpyehtbYCsT3z2Ys5ylKSWbBcN7wSJf0U_2hKU-V7mg0OLR_EwkAk-T68eubhNWyGdX2quSCMCH-gRUFoviYbnUYsMZkCoNdB_MlY81JX8f6HuoqcgkhogvsCOAQAfpsdDe85tdlBffateDeDSrO93cYQmSmvE5576DxkDCqxTVHVkp1dGdxtncdCmPyT3rqWf0-fBhXC-888w1rCbl9vpC034UEGVYKfotplwglP3aWNxaCdRY8m2c2_sBXVD-RSMLDSe6fZv01C7__z8PrtSXNvY5friRp5CiYFsBXJ-m083KehXoVPnmTyzrOiRgC1mGF2PkFgqMKsaMRjvfSu2kpVcO0agNGuZoFMt-PBMPMr9oAPc"; // Use an environment variable for the token;
            //$refreshToken = "915eab4926a034dec14a3deef882cd83d8f412f3f69694a173f715ca64a4e0736702e590125b303c8d916489589f9f2a6971a08f23725d9c2633f2bc39e5c45a"; // Use an environment variable for the refresh token

            foreach ($request->file('fileuploads') as $file) {
                $fileUpload = new FileUpload;
                $fileUpload->filename = $file->getClientOriginalName();
                $fileUpload->filepath = $file->store('fileuploads');
                Log::info('File path: ' . $fileUpload->filepath);
                $fileUpload->type = $file->getClientOriginalExtension();
                $fileUpload->save(); // Save file upload record

                // Prepare the data
                $data = [
                    'ciUploadId' => 6491234,
                    'repositoryName' => 'Pradnya27/example_app',
                    'version' => '1.0',
                    'ciUploadName' => 'test',
                    'commitName' => 'Upload image',
                    
                ];

                // Make the API call

                 Log::info("TOKEN".$token);
                $response = Http::withHeaders([
                    'Authorization' => $token, // Use an environment variable for the token
                    'refreshToken' => $refreshToken, // Use an environment variable for the refresh token
                    'Accept' => 'application/x-www-form-urlencoded',
                ])->attach('fileData', fopen($file->getPathname(), 'r'), $file->getClientOriginalName())
                    ->post($this->scandep, $data);

                if ($response->successful()) {
                    $res = $response->json();
                    if (empty($res['ciUploadId'])) {
                        Log::error('ciUploadId is not set.');
                        return response()->json(['error' => 'ciUploadId is required'], 400);
                    }
                    Log::info('API request successful: ' . $response->getBody()->getContents());
                    // call second API
                    $dataV2 = [
                        "ciUploadId" => $res['ciUploadId'],
                        'debrickedConfig' => json_encode([
                            'overrides' => [
                                'pURL' => '',
                                'version' => '1.0',
                                'fileRegexes' => [],
                            ],
                        ]),
                        'commitName' => 'null',
                        'author' => 'null',
                        'returnCommitData' => 'false',
                        'repositoryName' => 'null',
                        'debrickedIntegration' => 'null',
                        'integrationName' => 'null',
                        'versionHint' => 'false',
                    ];
                    
                    $fullUrl = $this->scandoc . '?' . http_build_query(['ciUploadId' => $res['ciUploadId']]);
                    $response2 = Http::withHeaders([
                        'Authorization' => $token, // Use an environment variable for the token
                        'refreshToken' => $refreshToken, // Use an environment variable for the refresh token
                        'Content-Type' => 'multipart/form-data',
                        'accept' => 'application/json',
                    ])->post($fullUrl, $dataV2);
                    if ($response2->successful()) {
                        $response = Http::withHeaders([
                            'Authorization' => $token, // Use an environment variable for the token
                            'refreshToken' => $refreshToken, // Use an environment variable for the refresh token
                            'Content-Type' => 'application/json',
                            'accept' => 'application/json',
                        ])->get($this->status. '?ciUploadId=' . $res['ciUploadId']);
                        if ($response->successful()) {
                            $res = $response->json();
                            Log::info('Status API request successful: ' . $response->getBody()->getContents());
                           
                        } else {
                            Log::error('Status request failed: ' . $response->getBody()->getContents());
                            $this->sendEmail("2ndAPI request failed: " . $response->getBody()->getContents());
                            
                        }
                    } else {
                        Log::error('Scan request failed: ' . $response->getBody()->getContents());
                        $this->sendEmail("2ndAPI request failed: " . $response->getBody()->getContents());
                        
                    }
                    Log::info("Sacn API:"  . $response2->getBody()->getContents());
                    return redirect()->route('fileupload.index')->with('message', 'Files uploaded successfully');
                } else {
                    Log::error('2ndAPI request failed: ' . $response->getBody()->getContents());
                    $this->sendEmail("2ndAPI request failed: " . $response->getBody()->getContents());
                    return redirect()->route('fileupload.index')->with('errors', ['No files Attached']);
                }
            }
        } else {
            
            Log::info('No files uploaded');
            
           
        }
        
    }

    /**
     * Send email
     * 
     * @param string $message
     * 
     * @return void
     */
    public function sendEmail($message = null): void
    {
        $data = [
            'name' => 'Pradnya Jain',
            'message' => $message ?? 'This is a test email from Laravel'
        ];

        try {
            Mail::to(env('MAIL_TO'))->send(new BuildMail($data));
            Log::info('Email sent successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());
        }
    }
}
