<?php

namespace App\View\Components\utils;
use Illuminate\View\Component;

class appointments extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */

    public $appointments;

    public function __construct($appointments)
    {
        //
        $this->appointments = $appointments;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.utils.appointments');
    }
}
