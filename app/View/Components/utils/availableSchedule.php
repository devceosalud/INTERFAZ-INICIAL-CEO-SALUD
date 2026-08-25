<?php

namespace App\View\Components\utils;

use Illuminate\View\Component;

class availableSchedule extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */

    public $specialties;
    
    public function __construct($specialties)
    {
        //
        $this->specialties = $specialties;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.utils.available-schedule');
    }
}
