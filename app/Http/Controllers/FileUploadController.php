<?php

namespace App\Http\Controllers;

use App\Mail\BuildMail;
use Illuminate\Http\Request;
use App\Models\FileUpload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;



class FileUploadController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     *  @param array $data
     * 
     * @return \Illuminate\Http\Response
     */
    public function index($data = null)
    {
        Log::info($data);
        $success = $data["success"] ?? null;
        $errors = $data["errors"] ?? null;
        Log::alert('Success: ' . $success);
        Log::alert('Errors: ' . $errors);
        $fileUplaods = FileUpload::get();
        return view('file-upload', ['fileUploads' => $fileUplaods, 'success' => $success, 'errors' => $errors]);
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
        // Validation
        $this->validate($request, [
            'fileuploads' => 'required',
            'fileuploads.*' => 'mimes:doc,pdf,docx,pptx,txt,zip'
        ]);

        if ($request->hasfile('fileuploads')) {
            foreach ($request->file('fileuploads') as $file) {
                $fileUpload = new FileUpload;
                $fileUpload->filename = $file->getClientOriginalName();
                $fileUpload->filepath = $file->store('fileuploads');
                Log::info('File path: ' . $fileUpload->filepath);
                $fileUpload->type = $file->getClientOriginalExtension();
                $fileUpload->save(); // Save file upload record

                // Prepare the data
                $data = [
                    'ciUploadId' => 1,
                    'filename' => $file->getClientOriginalName(),
                    'name' => 'file',
                    'version' => '1.0.0',
                    'ciUploadName' => 'test',
                    'commitName' => 'commit',
                    'repositoryUrl' => 'https://github.com/debricked/backend-home-task'
                ];

                // Make the API call
                $token = 'Bearer ' . env('ACCESS_TOKEN');

                $response = Http::withHeaders([
                    'Authorization' => $token, // Use an environment variable for the token
                    'refreshToken' => env('REFRESH_TOKEN'), // Use an environment variable for the refresh token
                    'Accept' => 'application/x-www-form-urlencoded',
                ])->attach('file', fopen($file->getPathname(), 'r'), $file->getClientOriginalName())
                    ->post('https://debricked.com/api/1.0/open/finishes/dependencies/files/uploads', $data);


                print($response->getBody()->getContents());
                return response()->json([
                    'error' => 'Failed to upload file',
                    'message' => $response->json(),
                ], $response->status());
                if ($response->successful()) {
                    Log::info('API request successful: ' . $response->getBody()->getContents());
                } else {
                    Log::error('API request failed: ' . $response->getBody()->getContents());
                    $this->sendEmail("API request failed: " . $response->getBody()->getContents());
                    return response()->json([
                        'success' => false,
                        'data' => [
                            'code' => $response->getStatusCode(),
                            'message' => $response->getBody()->getContents(),
                            'errors' => $response->json()
                        ]
                    ], $response->getStatusCode());
                    route('fileupload.index', ['errors' => 'API request failed: ' . $response->getBody()->getContents()]);
                    //throw ValidationException::withMessages(['errors' => 'API request failed: ' . $response->getBody()->getContents()]);
                }
            }
        } else {
            Log::info('No files uploaded');
            return route('fileupload.index', ['errors' => 'No files uploaded']);
        }
         return route('fileupload.index',['success', 'Files uploaded successfully!']);
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
