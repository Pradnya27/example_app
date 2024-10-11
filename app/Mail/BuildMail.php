<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BuildMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data; // You can pass data to the view

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->view('sendmail') // View for the email
                    ->with('data', $this->data) // Pass data to the view
                    ->subject('Image Scan done');
    }
}
