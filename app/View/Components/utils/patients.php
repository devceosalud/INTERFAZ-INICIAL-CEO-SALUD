<?php

namespace App\View\Components\utils;

use Illuminate\View\Component;

class patients extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */

    public $patients;

    public function __construct($patients)
    {
        //
        $this->patients = $patients;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.utils.patients');
    }
}
