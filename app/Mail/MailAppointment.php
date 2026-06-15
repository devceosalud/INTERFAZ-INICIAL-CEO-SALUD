<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MailAppointment extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $appointment;


    public function __construct(Appointment $appointment)
    {
        //
        $this->appointment = $appointment;
    }


    public function build()
    {
        return $this->from('dev.ceosalud@gmail.com', 'CEO-SALUD - ADMISIÓN')->subject('Cita creada')->markdown('emails.appointment.record');
    }
}
