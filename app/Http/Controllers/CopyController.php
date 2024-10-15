<?php

namespace App\Http\Controllers;

use App\Mail\BuildMail;
use Illuminate\Http\Request;
use App\Models\FileUpload;
use App\Http\Controllers\Session;
use Illuminate\Contracts\View\View as ViewView;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FileUploadController extends Controller
{
    /**
     * Access token
     */
    public $token =  '';

    /**
     * Refresh token
     */
    public $refreshToken = '';

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
     * File uploads
     */
    public $fileUploads = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->token = config('services.access_token') ?? "Bearer";
        $this->refreshToken = config('services.refresh_token') ?? "";
        $this->scandep = config('services.scandep') ?? "https://debricked.com/api/1.0/open/uploads/dependencies/files";
        $this->scandoc = config('services.scandoc') ?? "https://debricked.com/api/1.0/open/finishes/dependencies/files/uploads";
        $this->status = config('services.status') ?? "https://debricked.com/api/1.0/open/ci/upload/status";
        $this->fileUploads = FileUpload::get() ?? [];
        Log::info($this->token);
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

        $data = ['fileUploads' => $this->fileUploads];
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
                $return[] = $this->callDep($file);
            }
        } else {
            Log::info('No files uploaded');
            $return = [
                'code' => '404',
                'fileUploads' => $this->fileUploads,
                'message' => "NO FILES UPLOADED",
                'errors' => "NO FILES UPLOADED"
            ];
        }
        return view('file-upload', $return);
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
            'name' => 'File uploades to Debricks',
            'message' => $message ?? 'This is a test email from Laravel'
        ];

        try {
            Mail::to(env('MAIL_TO'))->send(new BuildMail($data));
            Log::info('Email sent successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to send email: ' . $e->getMessage());
        }
    }
    /**
     * Call the dependency API
     *
     * @param UploadedFile $file
     *
     * @return view
     */
    private function callDep($file): View
    {
        $data = [
            'ciUploadId' => 6491234,
            'repositoryName' => 'Pradnya27/example_app',
            'version' => '1.0',
            'ciUploadName' => 'test',
            'commitName' => 'Upload image',

        ];

        $response = Http::withHeaders([
            'Authorization' => $this->token, // Use an environment variable for the token
            'refreshToken' => $this->refreshToken, // Use an environment variable for the refresh token
            'Accept' => 'application/x-www-form-urlencoded',
        ])->attach('fileData', fopen($file->getPathname(), 'r'), $file->getClientOriginalName())
            ->post($this->scandep, $data);
        if ($response->successful()) {
            $res = $response->json();
            if (empty($res['ciUploadId'])) {
                Log::error('ciUploadId is not set.');
                return response()->json(['error' => 'ciUploadId is required'], 400);
            }
            $return = $this->callDoc($res['ciUploadId']);
            Log::info('API request successful: ' . $response->getBody()->getContents());
        } else {
            Log::error('API request failed: ' . $response->getBody()->getContents());
            $this->sendEmail("API request failed: " . $response->getBody()->getContents());
            $return = [
                'fileUploads' => [],
                'code' => '404',
                'message' => "API called failed",
                'errors' => "API CALL FAILED"
            ];
        }

        return view('file-upload', $return);
    }

    /**
     * Call the document API
     *
     * @param int $ciUploadId
     *
     *  @return View
     */
    private function callDoc($ciUploadId): ViewView
    {
        $dataV2 = [
            "ciUploadId" => $ciUploadId,
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
        $url =  config('services.scandoc');; //"https://debricked.com/api/1.0/open/finishes/dependencies/files/uploads";
        $fullUrl = $url . '?' . http_build_query(['ciUploadId' => $ciUploadId]);
        $response = Http::withHeaders([
            'Authorization' => $this->token, // Use an environment variable for the token
            'refreshToken' => $this->refreshToken, // Use an environment variable for the refresh token
            'Content-Type' => 'multipart/form-data',
            'accept' => 'application/json',
        ])->post($url, $dataV2);
        if ($response->successful()) {
            // call third API
            $this->callStatus($ciUploadId);
            Log::info("2nd API response: " . $response->getBody()->getContents());
        } else {
            $return = [
                'fileUploads' => [],
                'code' => $response->getStatusCode(),
                'message' => "2nd API failed",
                'errors' => $response->getBody()->getContents()
            ];
            return view('file-upload', $return);
        }
    }

    /**
     * Call Status API
     *
     * @param int $ciUploadId
     *
     * @return void
     */
    public function callStatus($ciUploadId)
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token, // Use an environment variable for the token
            'refreshToken' => $this->refreshToken, // Use an environment variable for the refresh token
            'Content-Type' => 'application/json',
            'accept' => 'application/json',
        ])->get($this->status, ['ciUploadId' => $ciUploadId]);
        if ($response->successful()) {
            $resp = $response->json();
            $return = [
                'code' => '200',
                'message' => "API called successfully",
                'errors' => ''
            ];
            Log::info("3nd API response: " . $response->getBody()->getContents());
            return $return;
        } else {
            $return = [
                'fileUploads' => [],
                'code' => $response->getStatusCode(),
                'message' => "2nd API failed",
                'errors' => $response->getBody()->getContents()
            ];
            return view('file-upload', $return);
        }
    }
}
