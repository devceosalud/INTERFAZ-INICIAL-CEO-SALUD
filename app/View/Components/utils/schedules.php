<?php

namespace App\View\Components\utils;

use Illuminate\View\Component;

class schedules extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */

    public $doctors;
    public $specialties;

    public function __construct($doctors, $specialties)
    {
        //
        $this->specialties = $specialties;
        $this->doctors = $doctors;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.utils.schedules');
    }
}
